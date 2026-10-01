<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Self-test delle parti pure delle distinte SEPA (nessuna scrittura su DB).
  $results = [];
  $check = function ($name, $cond, $detail = '') use (&$results) {
    $results[] = ['name' => $name, 'ok' => (bool)$cond, 'detail' => $detail];
  };

  if (!class_exists('SepaCbiExport')) {
    $check('Classe SepaCbiExport caricata', false, 'manca classes/SepaCbiExport.php o il require');
  } else {
    // --- IBAN ---
    $check('IBAN IT valido', SepaCbiExport::ibanIsValid('IT60X0542811101000000123456'));
    $check('IBAN valido con spazi e minuscole', SepaCbiExport::ibanIsValid('it60 x054 2811 1010 0000 0123 456'));
    $check('IBAN DE valido', SepaCbiExport::ibanIsValid('DE89370400440532013000'));
    $check('IBAN checksum errato', !SepaCbiExport::ibanIsValid('IT60X0542811101000000123457'));
    $check('IBAN IT lunghezza errata', !SepaCbiExport::ibanIsValid('IT60X054281110100000012345'));
    $check('IBAN vuoto', !SepaCbiExport::ibanIsValid(''));
    $check('IBAN cifrato non decifrato', !SepaCbiExport::ibanIsValid('dGhpcyBpcyBub3QgYW4gaWJhbg=='));
    $check('normalizeIban', SepaCbiExport::normalizeIban(' it60 x054 ') === 'IT60X054');
    $check('Paese SEPA IT', SepaCbiExport::isSepaCountry('IT'));
    $check('Paese SEPA CH', SepaCbiExport::isSepaCountry('CH'));
    $check('Paese non SEPA TR', !SepaCbiExport::isSepaCountry('TR'));
    $mask = SepaCbiExport::ibanMask('IT60X0542811101000000123456');
    $check('ibanMask', $mask === 'IT60…3456', $mask);
    $check('abiFromIban', SepaCbiExport::abiFromIban('IT60X0542811101000000123456') === '05428');

    // --- Charset SEPA ---
    $t = SepaCbiExport::toSepaCharset('Società Àlfa & C. - Niccolò', 70);
    $check('toSepaCharset accenti e &', $t === 'Societa Alfa C. - Niccolo', $t);
    $t = SepaCbiExport::toSepaCharset("D'Amico  Ünal\tß", 70);
    $check('toSepaCharset apostrofo, umlaut, spazi', $t === "D'Amico Unal ss", $t);
    $t = SepaCbiExport::toSepaCharset(str_repeat('a', 80), 70);
    $check('toSepaCharset tronca', strlen($t) === 70, (string)strlen($t));

    // --- Causale ---
    $tpl = 'Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}';
    $r = SepaCbiExport::buildRemittance($tpl, 2026, [12, 45]);
    $check('Causale base', $r === 'Rimb. Mercatino Da Vinci 2026 - Pratica 12, 45', $r);
    $many = range(1000, 1040);
    $r = SepaCbiExport::buildRemittance($tpl, 2026, $many);
    $check('Causale lunga <= 140', strlen($r) <= 140, (string)strlen($r));
    $check('Causale lunga finisce con ...', substr($r, -3) === '...', $r);
    $check('Causale lunga tronca a pratica intera', (bool)preg_match('/\d{4}, \.\.\.$|\d{4}\.\.\.$/', $r) === false
      && (bool)preg_match('/\d{4} \.\.\.$/', $r), $r);

    // --- Giorno lavorativo ---
    $check('Venerdì → lunedì', SepaCbiExport::nextBusinessDay(new DateTime('2026-10-02')) === '2026-10-05');
    $check('Giovedì → venerdì', SepaCbiExport::nextBusinessDay(new DateTime('2026-10-01')) === '2026-10-02');
    $check('Sabato → lunedì', SepaCbiExport::nextBusinessDay(new DateTime('2026-10-03')) === '2026-10-05');

    // --- XML ---
    $debtor = ['name' => 'Comitato Genitori Liceo Da Vinci', 'iban' => 'IT60X0542811101000000123456', 'cuc' => 'ABC12345', 'country' => 'IT', 'town' => 'Treviso', 'category_purpose' => 'SUPP'];
    $txs = [
      ['end_to_end_id' => 'MDV2026-R1-B9', 'amount' => 12.5, 'name' => 'Rossi Màrio', 'iban' => 'IT60X0542811101000000123456', 'remittance' => 'Rimb. Mercatino Da Vinci 2026 - Pratica 12', 'town' => 'Treviso'],
      ['end_to_end_id' => 'MDV2026-R2-B9', 'amount' => 7.1, 'name' => 'Müller Hans', 'iban' => 'DE89370400440532013000', 'remittance' => 'Rimb. Mercatino Da Vinci 2026 - Pratica 45', 'town' => 'München'],
    ];
    $xml = '';
    try {
      $xml = SepaCbiExport::buildXml($debtor, 'MDV-2026-0009-20261001120000', '2026-10-02', $txs, new DateTime('2026-10-01 12:00:00'));
      $check('buildXml non lancia eccezioni', true);
    } catch (Exception $e) {
      $check('buildXml non lancia eccezioni', false, $e->getMessage());
    }
    if ($xml !== '') {
      $dom = new DOMDocument();
      $loaded = $dom->loadXML($xml);
      $check('XML ben formato', $loaded);
      $xp = new DOMXPath($dom);
      $xp->registerNamespace('c', SepaCbiExport::XML_NAMESPACE);
      $val = function ($path) use ($xp) { $n = $xp->query($path); return $n->length ? $n->item(0)->textContent : null; };
      $check('Root e namespace', $dom->documentElement->localName === 'CBIPaymentRequest' && $dom->documentElement->namespaceURI === SepaCbiExport::XML_NAMESPACE);
      $check('MsgId', $val('/c:CBIPaymentRequest/c:GrpHdr/c:MsgId') === 'MDV-2026-0009-20261001120000');
      $check('NbOfTxs = 2', $val('/c:CBIPaymentRequest/c:GrpHdr/c:NbOfTxs') === '2');
      $check('CtrlSum = 19.60', $val('/c:CBIPaymentRequest/c:GrpHdr/c:CtrlSum') === '19.60', (string)$val('/c:CBIPaymentRequest/c:GrpHdr/c:CtrlSum'));
      $check('CUC in InitgPty', $val('//c:GrpHdr/c:InitgPty/c:Id/c:OrgId/c:Othr/c:Id') === 'ABC12345'
        && $val('//c:GrpHdr/c:InitgPty/c:Id/c:OrgId/c:Othr/c:Issr') === 'CBI');
      $check('BtchBookg true', $val('//c:PmtInf/c:BtchBookg') === 'true');
      $check('Paese ordinante', $val('//c:PmtInf/c:Dbtr/c:PstlAdr/c:Ctry') === 'IT');
      $check('ABI ordinante', $val('//c:PmtInf/c:DbtrAgt/c:FinInstnId/c:ClrSysMmbId/c:MmbId') === '05428');
      $check('Data esecuzione', $val('//c:PmtInf/c:ReqdExctnDt' . (SepaCbiExport::EXEC_DATE_NESTED ? '/c:Dt' : '')) === '2026-10-02');
      $check('Importo 1 = 12.50', $val('(//c:CdtTrfTxInf)[1]/c:Amt/c:InstdAmt') === '12.50');
      $check('Valuta EUR', $xp->query('(//c:CdtTrfTxInf)[1]/c:Amt/c:InstdAmt[@Ccy="EUR"]')->length === 1);
      $check('Nome beneficiario traslitterato', $val('(//c:CdtTrfTxInf)[1]/c:Cdtr/c:Nm') === 'Rossi Mario');
      $check('Paese beneficiario da IBAN', $val('(//c:CdtTrfTxInf)[2]/c:Cdtr/c:PstlAdr/c:Ctry') === 'DE');
      $check('EndToEndId', $val('(//c:CdtTrfTxInf)[2]/c:PmtId/c:EndToEndId') === 'MDV2026-R2-B9');
      $check('Causale', $val('(//c:CdtTrfTxInf)[1]/c:RmtInf/c:Ustrd') === 'Rimb. Mercatino Da Vinci 2026 - Pratica 12');
      $check('TwnNm ordinante', $val('//c:PmtInf/c:Dbtr/c:PstlAdr/c:TwnNm') === 'Treviso');
      $t = $val('(//c:CdtTrfTxInf)[2]/c:Cdtr/c:PstlAdr/c:TwnNm');
      $check('TwnNm beneficiario 2 traslitterato', $t === 'Munchen', $t);
      $check('CtgyPurp transazione 1 = SUPP', $val('(//c:CdtTrfTxInf)[1]/c:PmtTpInf/c:CtgyPurp/c:Cd') === 'SUPP');
      $check('CtgyPurp transazione 2 = SUPP', $val('(//c:CdtTrfTxInf)[2]/c:PmtTpInf/c:CtgyPurp/c:Cd') === 'SUPP');
      $check('CtgyPurp assente da PmtInf/PmtTpInf', $xp->query('//c:PmtInf/c:PmtTpInf/c:CtgyPurp')->length === 0);
      $order = [];
      foreach ($xp->query('(//c:CdtTrfTxInf)[1]')->item(0)->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
          $order[] = $child->localName;
        }
      }
      $check('Ordine elementi CdtTrfTxInf', $order === ['PmtId', 'PmtTpInf', 'Amt', 'Cdtr', 'CdtrAcct', 'RmtInf'], implode(', ', $order));
      $v = SepaCbiExport::validate($xml);
      $check('Validazione XSD', $v['skipped'] || count($v['errors']) === 0,
        $v['skipped'] ? 'XSD non presente: saltata' : implode(' | ', $v['errors']));
    }
    $threw = false;
    try { SepaCbiExport::buildXml($debtor, 'X', '2026-10-02', [['end_to_end_id' => 'A', 'amount' => 5, 'name' => 'A', 'iban' => 'IT60X0542811101000000123457', 'remittance' => 'x', 'town' => 'Treviso']]); }
    catch (InvalidArgumentException $e) { $threw = true; }
    $check('buildXml rifiuta IBAN non valido', $threw);
    $threw = false;
    try { SepaCbiExport::buildXml($debtor, 'X', '2026-10-02', []); }
    catch (InvalidArgumentException $e) { $threw = true; }
    $check('buildXml rifiuta elenco vuoto', $threw);
    $threw = false;
    try {
      $txsBadTown = $txs;
      $txsBadTown[0]['town'] = '   ';
      SepaCbiExport::buildXml($debtor, 'X', '2026-10-02', $txsBadTown);
    } catch (InvalidArgumentException $e) { $threw = true; }
    $check('buildXml rifiuta località beneficiario vuota', $threw);

    $xmlNoCuc = '';
    try {
      $debtorNoCuc = $debtor;
      $debtorNoCuc['cuc'] = '';
      $xmlNoCuc = SepaCbiExport::buildXml($debtorNoCuc, 'MDV-2026-0010-20261001120000', '2026-10-02', $txs, new DateTime('2026-10-01 12:00:00'));
      $check('buildXml con CUC vuoto non lancia eccezioni', true);
    } catch (Exception $e) {
      $check('buildXml con CUC vuoto non lancia eccezioni', false, $e->getMessage());
    }
    if ($xmlNoCuc !== '') {
      $domNoCuc = new DOMDocument();
      $domNoCuc->loadXML($xmlNoCuc);
      $xpNoCuc = new DOMXPath($domNoCuc);
      $xpNoCuc->registerNamespace('c', SepaCbiExport::XML_NAMESPACE);
      $check('CUC vuoto: nessun InitgPty/Id', $xpNoCuc->query('//c:GrpHdr/c:InitgPty/c:Id')->length === 0);
    }

    // --- SepaBatchManager (sola lettura) ---
    if (class_exists('SepaBatchManager')) {
      $sbm = new SepaBatchManager();
      $check('Migrazione applicata (tabelle sepa_batch)', $sbm->isInstalled(), 'se FAIL: applicare sql/202610010001_sepa_distinte.sql e sql/202610020001_sepa_localita.sql');
      if ($sbm->isInstalled()) {
        $p = $sbm->debtorProblems(['name' => '', 'iban' => 'IT60X0542811101000000123457', 'cuc' => '', 'country' => 'IT', 'town' => 'Treviso', 'default_creditor_town' => 'Treviso', 'category_purpose' => 'X', 'template' => 'x']);
        $check('debtorProblems trova 3 problemi', count($p) === 3, implode(' | ', $p));
        $p = $sbm->debtorProblems(['name' => 'Comitato', 'iban' => 'IT60X0542811101000000123456', 'cuc' => 'ABC12345', 'country' => 'IT', 'town' => 'Treviso', 'default_creditor_town' => 'Treviso', 'category_purpose' => 'SUPP', 'template' => 'x']);
        $check('debtorProblems ok', count($p) === 0, implode(' | ', $p));
        $p = $sbm->debtorProblems(['name' => 'Comitato', 'iban' => 'IT60X0542811101000000123456', 'cuc' => '', 'country' => 'IT', 'town' => 'Treviso', 'default_creditor_town' => 'Treviso', 'category_purpose' => 'SUPP', 'template' => 'x']);
        $check('debtorProblems CUC vuoto ok', count($p) === 0, implode(' | ', $p));
        $c = $sbm->getCandidates((int)date('Y'));
        $check('getCandidates restituisce eligible/excluded', isset($c['eligible'], $c['excluded']));
        $leak = false;
        foreach (array_merge($c['eligible'], $c['excluded']) as $row) { if (isset($row->iban)) { $leak = true; } }
        $check('getCandidates senza IBAN in chiaro', !$leak);
      }
    } else {
      $check('Classe SepaBatchManager caricata', false);
    }

    // --- Area economica europea (SEE) ---
    $check('isEeaCountry IT', SepaCbiExport::isEeaCountry('IT'));
    $check('isEeaCountry NO', SepaCbiExport::isEeaCountry('NO'));
    $check('isEeaCountry CH', !SepaCbiExport::isEeaCountry('CH'));
    $check('isEeaCountry GB', !SepaCbiExport::isEeaCountry('GB'));
    $check('isEeaCountry SM', !SepaCbiExport::isEeaCountry('SM'));

    // --- Ambiente server ---
    $check('Estensione DOM disponibile', class_exists('DOMDocument'));
  }

  $failed = count(array_filter($results, function ($r) { return !$r['ok']; }));
?>
<h1>Self-Test: Distinte SEPA</h1>
<p class="lead">
  <?php if ($failed === 0): ?>
    <span class="badge badge-success">TUTTI PASS (<?php echo count($results); ?>)</span>
  <?php else: ?>
    <span class="badge badge-danger"><?php echo $failed; ?> FAIL su <?php echo count($results); ?></span>
  <?php endif; ?>
</p>
<table class="table table-sm">
  <thead><tr><th>Test</th><th>Esito</th><th>Dettaglio</th></tr></thead>
  <tbody>
  <?php foreach ($results as $r): ?>
    <tr>
      <td><?php echo esc_html($r['name']); ?></td>
      <td><?php echo $r['ok'] ? '<span class="text-success font-weight-bold">PASS</span>' : '<span class="text-danger font-weight-bold">FAIL</span>'; ?></td>
      <td><code><?php echo esc_html($r['detail']); ?></code></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
