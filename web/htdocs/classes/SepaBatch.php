<?php

/** Errore mostrabile all'utente (testo in italiano). */
class SepaException extends Exception {}

/**
 * Distinte SEPA dei rimborsi venditori: dati ordinante, rimborsi idonei,
 * creazione / pagamento / scarto delle distinte.
 *
 * Tutte le scritture passano da $this->db->pdo dentro una transazione: ogni
 * DBManager apre la propria connessione PDO, quindi NON chiamare qui metodi
 * di SellerRefundManager che scrivono (es. recordPayment) — finirebbero fuori
 * dalla transazione.
 */
class SepaBatchManager extends DBManager {

    const DEFAULT_TEMPLATE = 'Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}';

    public function __construct() {
        parent::__construct();
        $this->tableName = 'sepa_batch';
        $this->columns = ['id', 'year', 'msg_id', 'execution_date', 'remittance_template', 'debtor_iban_masked',
                          'tx_count', 'total_amount', 'status', 'created_by', 'created_at', 'paid_at', 'paid_by'];
    }

    /** true se la migrazione 202610010001 è stata applicata su questo DB. */
    public function isInstalled() {
        try {
            return count($this->db->prepare("SHOW TABLES LIKE 'sepa_batch_item'")) === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    // ---------------------------------------------------------------- ordinante

    public function getDebtorSettings() {
        return [
            'name' => (string)SiteSettings::get('sepa_debtor_name', ''),
            'iban' => (string)SiteSettings::get('sepa_debtor_iban', ''),
            'cuc' => (string)SiteSettings::get('sepa_debtor_cuc', ''),
            'country' => (string)SiteSettings::get('sepa_debtor_country', 'IT'),
            'template' => (string)SiteSettings::get('sepa_remittance_template', self::DEFAULT_TEMPLATE),
        ];
    }

    /** Elenco dei problemi che impediscono di generare (vuoto = tutto ok). */
    public function debtorProblems(array $s) {
        $problems = [];
        if (trim($s['name']) === '') {
            $problems[] = "Manca l'intestatario del conto del Comitato.";
        }
        $iban = SepaCbiExport::normalizeIban($s['iban']);
        if (!SepaCbiExport::ibanIsValid($iban) || substr($iban, 0, 2) !== 'IT') {
            $problems[] = "L'IBAN del Comitato manca o non è un IBAN italiano valido.";
        }
        if (!preg_match('/^[A-Z0-9]{1,8}$/', strtoupper(trim($s['cuc'])))) {
            $problems[] = 'Manca il CUC (Codice Univoco CBI) o non è valido (fino a 8 lettere/cifre).';
        }
        if (!preg_match('/^[A-Z]{2}$/', strtoupper(trim($s['country'])))) {
            $problems[] = 'Il paese ordinante deve essere di 2 lettere (es. IT).';
        }
        return $problems;
    }

    public function saveDebtorSettings(array $data) {
        $s = [
            'name' => trim((string)($data['name'] ?? '')),
            'iban' => SepaCbiExport::normalizeIban($data['iban'] ?? ''),
            'cuc' => strtoupper(trim((string)($data['cuc'] ?? ''))),
            'country' => strtoupper(trim((string)($data['country'] ?? 'IT'))),
            'template' => 'x',
        ];
        $problems = $this->debtorProblems($s);
        if ($problems) {
            return $problems;
        }
        SiteSettings::set('sepa_debtor_name', $s['name']);
        SiteSettings::set('sepa_debtor_iban', $s['iban']);
        SiteSettings::set('sepa_debtor_cuc', $s['cuc']);
        SiteSettings::set('sepa_debtor_country', $s['country']);
        return [];
    }

    public function saveTemplate($template) {
        $template = trim((string)$template);
        if ($template === '' || mb_strlen($template) > 140) {
            return ['La causale deve avere da 1 a 140 caratteri.'];
        }
        SiteSettings::set('sepa_remittance_template', $template);
        return [];
    }

    // ---------------------------------------------------------------- idonei

    /**
     * Rimborsi bonifico dell'anno con residuo > 0, divisi fra includibili
     * ed esclusi (con motivo). L'IBAN in chiaro viene restituito solo se
     * $withIban è true (uso interno di createBatch).
     */
    public function getCandidates($year, $withIban = false) {
        $srm = new SellerRefundManager();
        $query = "
            SELECT sr.id, sr.user_id, sr.status, sr.amount_owed, sr.amount_paid,
                   u.first_name, u.last_name, u.iban, u.iban_owner_name,
                   (SELECT GROUP_CONCAT(DISTINCT o.numPratica ORDER BY o.numPratica SEPARATOR ', ')
                      FROM orders o
                     WHERE o.user_id = sr.user_id AND o.numPratica > 0
                       AND o.numPratica <> " . SellerRefundManager::BOOKSHOP_PRATICA . ") AS pratica_numbers,
                   (SELECT MAX(sbi.batch_id)
                      FROM sepa_batch_item sbi
                      JOIN sepa_batch sb ON sb.id = sbi.batch_id
                     WHERE sbi.seller_refund_id = sr.id AND sb.status <> 'discarded') AS last_batch_id
            FROM seller_refund sr
            INNER JOIN user u ON u.id = sr.user_id
            WHERE sr.year = ?
              AND sr.payment_preference = 'wire_transfer'
              AND sr.status IN ('pending', 'partial', 'xmlsaved')
              AND (sr.amount_owed - sr.amount_paid) > 0
              AND " . $srm->sqlIsRealSeller('sr') . "
            ORDER BY u.last_name, u.first_name
        ";
        $rows = $this->db->prepare($query, [(int)$year]);

        $batchDates = [];
        foreach ($this->db->prepare("SELECT id, created_at FROM sepa_batch WHERE year = ?", [(int)$year]) as $b) {
            $batchDates[(int)$b['id']] = $b['created_at'];
        }

        $eligible = [];
        $excluded = [];
        foreach ($rows as $r) {
            $iban = $this->decryptIban($r['iban']);
            $name = trim((string)$r['iban_owner_name']) !== ''
                ? $r['iban_owner_name']
                : trim($r['first_name'] . ' ' . $r['last_name']);

            $row = (object)[
                'id' => (int)$r['id'],
                'user_id' => (int)$r['user_id'],
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'status' => $r['status'],
                'amount_owed' => (float)$r['amount_owed'],
                'amount_paid' => (float)$r['amount_paid'],
                'due' => round((float)$r['amount_owed'] - (float)$r['amount_paid'], 2),
                'pratica_numbers' => (string)$r['pratica_numbers'],
                'beneficiary_name' => SepaCbiExport::toSepaCharset($name, SepaCbiExport::MAX_NAME),
                'iban_masked' => $iban === '' ? '' : SepaCbiExport::ibanMask($iban),
                'last_batch_id' => $r['last_batch_id'] !== null ? (int)$r['last_batch_id'] : null,
                'last_batch_date' => $r['last_batch_id'] !== null ? ($batchDates[(int)$r['last_batch_id']] ?? null) : null,
            ];

            $reason = null;
            if ($iban === '') {
                $reason = 'IBAN mancante';
            } elseif (!SepaCbiExport::ibanIsValid($iban)) {
                $reason = 'IBAN non valido o non decifrabile';
            } elseif (!SepaCbiExport::isSepaCountry(substr($iban, 0, 2))) {
                $reason = 'IBAN di un paese fuori area SEPA';
            } elseif (!SepaCbiExport::isEeaCountry(substr($iban, 0, 2))) {
                $reason = 'IBAN extra-SEE (serve BIC e indirizzo): paga a mano';
            } elseif ($row->beneficiary_name === '') {
                $reason = 'Intestatario mancante';
            }

            if ($reason !== null) {
                $row->reason = $reason;
                $excluded[] = $row;
            } else {
                if ($withIban) {
                    $row->iban = $iban;
                }
                $eligible[] = $row;
            }
        }
        return ['eligible' => $eligible, 'excluded' => $excluded];
    }

    // ---------------------------------------------------------------- scrittura

    /**
     * Crea la distinta e restituisce l'XML. Tutto in una transazione: se
     * qualcosa fallisce (anche la validazione XSD) non resta traccia.
     */
    public function createBatch($year, array $refundIds, $executionDate, $template, $operatorId) {
        $year = (int)$year;
        $refundIds = array_values(array_unique(array_filter(array_map('intval', $refundIds))));
        if (!$refundIds) {
            throw new SepaException('Seleziona almeno un rimborso.');
        }
        $d = DateTime::createFromFormat('!Y-m-d', (string)$executionDate);
        if (!$d || $d->format('Y-m-d') !== $executionDate) {
            throw new SepaException('Data di esecuzione non valida.');
        }
        if ($executionDate < date('Y-m-d')) {
            throw new SepaException('La data di esecuzione non può essere nel passato.');
        }
        $debtor = $this->getDebtorSettings();
        $problems = $this->debtorProblems($debtor);
        if ($problems) {
            throw new SepaException(implode(' ', $problems));
        }
        $template = trim((string)$template) !== '' ? trim((string)$template) : $debtor['template'];

        // Mai fidarsi degli id del POST: si ricontrollano sugli idonei di adesso.
        $byId = [];
        foreach ($this->getCandidates($year, true)['eligible'] as $c) {
            $byId[$c->id] = $c;
        }
        $selected = [];
        foreach ($refundIds as $id) {
            if (!isset($byId[$id])) {
                throw new SepaException("Il rimborso #$id non è più includibile (pagato, cambiato o IBAN non valido). Ricarica la pagina.");
            }
            $selected[] = $byId[$id];
        }

        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            // msg_id provvisorio: serve l'id della riga per costruire quello vero.
            $pdo->prepare("INSERT INTO sepa_batch (year, msg_id, execution_date, remittance_template, debtor_iban_masked, created_by)
                           VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$year, 'TMP-' . bin2hex(random_bytes(8)), $executionDate,
                           mb_substr($template, 0, 140), SepaCbiExport::ibanMask($debtor['iban']), (int)$operatorId]);
            $batchId = (int)$pdo->lastInsertId();
            $msgId = sprintf('MDV-%d-%04d-%s', $year, $batchId, date('YmdHis'));

            // Rilocka e ricontrolla ogni rimborso: getCandidates() sopra non blocca,
            // quindi qualcosa può essere cambiato (pagato a mano, importo rivisto)
            // tra la lettura e qui.
            $lockRefund = $pdo->prepare("SELECT status, amount_owed, amount_paid FROM seller_refund WHERE id = ? FOR UPDATE");
            $txs = [];
            $total = 0;
            foreach ($selected as $c) {
                $lockRefund->execute([$c->id]);
                $r = $lockRefund->fetch(PDO::FETCH_ASSOC);
                $freshDue = $r ? round((float)$r['amount_owed'] - (float)$r['amount_paid'], 2) : null;
                if (!$r || !in_array($r['status'], ['pending', 'partial', 'xmlsaved'], true) || abs($freshDue - $c->due) > 0.001) {
                    throw new SepaException("Il rimborso #{$c->id} è cambiato nel frattempo (pagamento o importo). Ricarica la pagina.");
                }
                $praticas = $c->pratica_numbers === '' ? [] : array_map('intval', explode(',', $c->pratica_numbers));
                $tx = [
                    'refund_id' => $c->id,
                    'end_to_end_id' => sprintf('MDV%d-R%d-B%d', $year, $c->id, $batchId),
                    'amount' => $c->due,
                    'name' => $c->beneficiary_name,
                    'iban' => $c->iban,
                    'iban_masked' => $c->iban_masked,
                    'remittance' => SepaCbiExport::buildRemittance($template, $year, $praticas),
                ];
                $txs[] = $tx;
                $total += (int)round($c->due * 100);
            }

            try {
                $xml = SepaCbiExport::buildXml($debtor, $msgId, $executionDate, $txs);
            } catch (InvalidArgumentException $e) {
                throw new SepaException($e->getMessage());
            }
            $check = SepaCbiExport::validate($xml);
            if (!$check['skipped'] && $check['errors']) {
                throw new SepaException('Il file non rispetta lo schema CBI: ' . implode(' | ', array_slice($check['errors'], 0, 5)));
            }

            $pdo->prepare("UPDATE sepa_batch SET msg_id = ?, tx_count = ?, total_amount = ? WHERE id = ?")
                ->execute([$msgId, count($txs), $total / 100, $batchId]);
            $insItem = $pdo->prepare("INSERT INTO sepa_batch_item
                (batch_id, seller_refund_id, amount, end_to_end_id, remittance, beneficiary_name, iban_masked)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $setStatus = $pdo->prepare("UPDATE seller_refund SET status = 'xmlsaved' WHERE id = ?");
            foreach ($txs as $tx) {
                $insItem->execute([$batchId, $tx['refund_id'], $tx['amount'], $tx['end_to_end_id'],
                                   $tx['remittance'], $tx['name'], $tx['iban_masked']]);
                $setStatus->execute([$tx['refund_id']]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof SepaException) {
                throw $e;
            }
            error_log('SepaBatchManager::createBatch: ' . $e->getMessage());
            throw new SepaException('Errore nel salvataggio della distinta: nessuna modifica è stata fatta.');
        }

        return [
            'batch_id' => $batchId,
            'msg_id' => $msgId,
            'xml' => $xml,
            'filename' => sprintf('distinta_sepa_%d_%03d.xml', $year, $batchId),
            'tx_count' => count($txs),
            'total' => $total / 100,
        ];
    }

    /**
     * Scarta una distinta non ancora pagata. I suoi rimborsi ancora 'xmlsaved'
     * tornano pending/partial, salvo che siano in un'altra distinta 'generated'.
     */
    public function discardBatch($batchId, $operatorId) {
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch->status !== 'generated') {
            throw new SepaException('Si possono scartare solo le distinte generate e non ancora pagate.');
        }
        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            // Rilocka e ricontrolla lo stato: tra il controllo sopra e qui la
            // distinta potrebbe essere già stata pagata o scartata da un'altra richiesta.
            $lock = $pdo->prepare("SELECT status FROM sepa_batch WHERE id = ? FOR UPDATE");
            $lock->execute([(int)$batchId]);
            if ($lock->fetchColumn() !== 'generated') {
                throw new SepaException('Si possono scartare solo le distinte generate e non ancora pagate.');
            }
            $pdo->prepare("UPDATE sepa_batch SET status = 'discarded' WHERE id = ? AND status = 'generated'")
                ->execute([(int)$batchId]);
            $pdo->prepare("
                UPDATE seller_refund sr
                   JOIN sepa_batch_item sbi ON sbi.seller_refund_id = sr.id AND sbi.batch_id = ?
                   SET sr.status = IF(sr.amount_paid > 0, 'partial', 'pending')
                 WHERE sr.status = 'xmlsaved'
                   AND NOT EXISTS (
                       SELECT 1 FROM sepa_batch_item o
                         JOIN sepa_batch ob ON ob.id = o.batch_id
                        WHERE o.seller_refund_id = sr.id AND ob.status = 'generated' AND ob.id <> ?)
            ")->execute([(int)$batchId, (int)$batchId]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof SepaException) {
                throw $e;
            }
            error_log('SepaBatchManager::discardBatch: ' . $e->getMessage());
            throw new SepaException('Errore nello scarto della distinta: nessuna modifica è stata fatta.');
        }
    }

    /**
     * Registra i pagamenti di una distinta eseguita dalla banca. Rifiuta se
     * uno dei suoi rimborsi è anche in una distinta più recente non ancora
     * pagata né scartata ('generated'): va scartata, oppure pagata, prima
     * quella. Salta invece le righe già superate da una distinta più recente
     * ormai pagata ('skipped'), e i rimborsi già completati/annullati nel
     * frattempo, es. pagati a mano ('skipped_settled': l'operatore va
     * avvisato di controllarli con l'estratto conto, possibile doppio
     * pagamento). Stessa logica di SellerRefundManager::recordPayment(), ma
     * sulla connessione di questa transazione.
     */
    public function markBatchPaid($batchId, $paymentDate, $operatorId) {
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch->status !== 'generated') {
            throw new SepaException('Si possono segnare come pagate solo le distinte generate.');
        }
        $d = DateTime::createFromFormat('!Y-m-d', (string)$paymentDate);
        if (!$d || $d->format('Y-m-d') !== $paymentDate) {
            throw new SepaException('Data di pagamento non valida.');
        }

        $paid = 0;
        $skippedSuperseded = 0;
        $skippedSettled = 0;
        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            // Rilocka e ricontrolla lo stato: tra il controllo sopra e qui la
            // distinta potrebbe essere già stata pagata o scartata da un'altra richiesta.
            $lock = $pdo->prepare("SELECT status FROM sepa_batch WHERE id = ? FOR UPDATE");
            $lock->execute([(int)$batchId]);
            if ($lock->fetchColumn() !== 'generated') {
                throw new SepaException('Si possono segnare come pagate solo le distinte generate.');
            }

            // Se uno di questi rimborsi è anche in una distinta più recente
            // non ancora pagata, va risolta prima quella: altrimenti si
            // rischia di pagare qui un rimborso che la distinta più recente
            // sta per pagare (o scartare) a sua volta.
            $newer = $this->db->prepare("
                SELECT MIN(nb.id) AS min_id
                  FROM sepa_batch_item sbi
                  JOIN sepa_batch_item n ON n.seller_refund_id = sbi.seller_refund_id AND n.batch_id > sbi.batch_id
                  JOIN sepa_batch nb ON nb.id = n.batch_id
                 WHERE sbi.batch_id = ? AND nb.status = 'generated'", [(int)$batchId]);
            $newerId = isset($newer[0]['min_id']) ? $newer[0]['min_id'] : null;
            if ($newerId !== null) {
                throw new SepaException('La distinta #' . (int)$newerId . ' più recente contiene alcuni degli stessi rimborsi: scartala prima, oppure segna come pagata quella.');
            }

            $insPay = $pdo->prepare("INSERT INTO seller_refund_payment
                (seller_refund_id, amount, payment_method, payment_date, reference, notes, operator_id)
                VALUES (?, ?, 'wire_transfer', ?, ?, ?, ?)");
            $updRefund = $pdo->prepare("UPDATE seller_refund SET amount_paid = ?, payment_date = ?, status = ? WHERE id = ?");
            $getRefund = $pdo->prepare("SELECT amount_owed, amount_paid, status FROM seller_refund WHERE id = ? FOR UPDATE");

            foreach ($this->getBatchItems($batchId) as $item) {
                $getRefund->execute([$item->seller_refund_id]);
                $r = $getRefund->fetch(PDO::FETCH_ASSOC);
                if ($item->superseded || !$r) {
                    $skippedSuperseded++;
                    continue;
                }
                if (in_array($r['status'], ['completed', 'cancelled'], true)) {
                    $skippedSettled++;
                    continue;
                }
                $insPay->execute([$item->seller_refund_id, $item->amount, $paymentDate, $batch->msg_id,
                                  'Distinta SEPA #' . (int)$batchId, (int)$operatorId]);
                $newPaid = round((float)$r['amount_paid'] + (float)$item->amount, 2);
                $newStatus = $newPaid >= (float)$r['amount_owed'] ? 'completed' : 'partial';
                $updRefund->execute([$newPaid, $paymentDate, $newStatus, $item->seller_refund_id]);
                $paid++;
            }
            $pdo->prepare("UPDATE sepa_batch SET status = 'paid', paid_at = NOW(), paid_by = ? WHERE id = ? AND status = 'generated'")
                ->execute([(int)$operatorId, (int)$batchId]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof SepaException) {
                throw $e;
            }
            error_log('SepaBatchManager::markBatchPaid: ' . $e->getMessage());
            throw new SepaException('Errore nella registrazione dei pagamenti: nessuna modifica è stata fatta.');
        }
        return ['paid' => $paid, 'skipped' => $skippedSuperseded + $skippedSettled, 'skipped_settled' => $skippedSettled];
    }

    // ---------------------------------------------------------------- storico

    public function getBatch($batchId) {
        $rows = $this->db->prepare("SELECT * FROM sepa_batch WHERE id = ?", [(int)$batchId]);
        return $rows ? (object)$rows[0] : null;
    }

    public function getBatchesForYear($year) {
        $rows = $this->db->prepare("
            SELECT sb.*,
                   CONCAT(cu.first_name, ' ', cu.last_name) AS created_by_name,
                   CONCAT(pu.first_name, ' ', pu.last_name) AS paid_by_name
              FROM sepa_batch sb
              LEFT JOIN user cu ON cu.id = sb.created_by
              LEFT JOIN user pu ON pu.id = sb.paid_by
             WHERE sb.year = ?
             ORDER BY sb.id DESC", [(int)$year]);
        return array_map(function ($r) { return (object)$r; }, $rows);
    }

    /** Righe di una distinta; superseded = rimborso in una distinta più recente non scartata. */
    public function getBatchItems($batchId) {
        $rows = $this->db->prepare("
            SELECT sbi.*, u.first_name, u.last_name,
                   EXISTS (SELECT 1 FROM sepa_batch_item n
                             JOIN sepa_batch nb ON nb.id = n.batch_id
                            WHERE n.seller_refund_id = sbi.seller_refund_id
                              AND n.batch_id > sbi.batch_id
                              AND nb.status <> 'discarded') AS superseded
              FROM sepa_batch_item sbi
              JOIN seller_refund sr ON sr.id = sbi.seller_refund_id
              LEFT JOIN user u ON u.id = sr.user_id
             WHERE sbi.batch_id = ?
             ORDER BY u.last_name, u.first_name", [(int)$batchId]);
        return array_map(function ($r) {
            $r['superseded'] = (bool)$r['superseded'];
            return (object)$r;
        }, $rows);
    }

    public function getBatchesForRefund($refundId) {
        $rows = $this->db->prepare("
            SELECT sb.id, sb.msg_id, sb.status, sb.created_at, sb.execution_date, sbi.amount,
                   EXISTS (SELECT 1 FROM sepa_batch_item n
                             JOIN sepa_batch nb ON nb.id = n.batch_id
                            WHERE n.seller_refund_id = sbi.seller_refund_id
                              AND n.batch_id > sbi.batch_id
                              AND nb.status <> 'discarded') AS superseded
              FROM sepa_batch_item sbi
              JOIN sepa_batch sb ON sb.id = sbi.batch_id
             WHERE sbi.seller_refund_id = ?
             ORDER BY sb.id DESC", [(int)$refundId]);
        return array_map(function ($r) {
            $r['superseded'] = (bool)$r['superseded'];
            return (object)$r;
        }, $rows);
    }

    /** Stessa logica di UserManager::getIBAN(): se non si decifra, è testo legacy. */
    private function decryptIban($stored) {
        if ($stored === null || $stored === '') {
            return '';
        }
        if (Encryption::isConfigured()) {
            $dec = Encryption::decrypt($stored);
            if ($dec !== false) {
                $stored = $dec;
            }
        }
        return SepaCbiExport::normalizeIban($stored);
    }
}
