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
