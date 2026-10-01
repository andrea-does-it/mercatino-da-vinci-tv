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
