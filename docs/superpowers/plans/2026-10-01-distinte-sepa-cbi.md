# Distinte SEPA (XML CBI 04.01) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generare da admin il file XML SEPA CBI 04.01 dei bonifici ai venditori (rimborsi con preferenza bonifico), tracciare le distinte e segnarle come pagate.

**Architecture:** Una classe pura `SepaCbiExport` (helper IBAN/charset/causale + costruzione XML con DOMDocument, nessun DB), un `SepaBatchManager` (DBManager) che legge gli idonei e scrive batch/stati/pagamenti **sulla propria connessione PDO in transazione**, un endpoint standalone `api/admin/sepa-batch-download.php` che restituisce l'XML (nessun output prima degli header), e una pagina admin `seller-refund-sepa` che mette insieme dati ordinante, checklist, esclusi e storico. Il nuovo stato `xmlsaved` viene integrato nelle pagine rimborsi esistenti.

**Tech Stack:** PHP senza framework (estensioni DOM e libxml), MySQL/PDO, Bootstrap 4 + Font Awesome (UI admin esistente), jQuery già caricato dal layout.

**Spec:** `docs/superpowers/specs/2026-10-01-distinte-sepa-cbi-design.md`

## Global Constraints

- **Dual-tree:** ogni file creato/modificato sotto `web/htdocs/<path>` va copiato identico in `web/htdocs/staging/<path>`; SQL in entrambi i `sql/`. Tutti i file esistenti toccati da questo piano sono oggi identici fra i due tree (verificato con `diff -q --strip-trailing-cr`), quindi dopo ogni modifica: `cp web/htdocs/<path> web/htdocs/staging/<path>`.
- **Sync attivo:** `tools/sync-watch.sh --target staging` è in esecuzione: ogni salvataggio sotto `web/htdocs/staging/` è subito online su staging. Scrivere **prima** il file in `web/htdocs/<path>`, poi copiarlo in staging. Per i form con CSRF copiare controller e form **nello stesso task** (trappola deploy parziale CSRF, `context/06`).
- **Commit:** messaggi in italiano, **nessuna menzione di Claude/AI, nessun trailer `Co-Authored-By`**.
- **Migrazioni:** il file `.sql` va applicato **a mano** su staging (e poi prod) dall'utente; finché non lo è, la pagina deve mostrare un errore leggibile invece di andare in eccezione.
- **UI:** italiano, "tu". Output con `esc_html()`; id castati a `(int)`.
- **CSRF:** form → `csrf_field()`; controller di pagina → `CSRF::validateToken()` (pattern di `seller-refund-view.php`); endpoint standalone → `CSRF::validateOrDie($urlPagina)`.
- **IBAN:** mai in chiaro in DB (oltre a `user.iban` cifrato già esistente), nei log, nell'HTML. Solo `SepaCbiExport::ibanMask()`.
- **Pratica 100** esclusa: ogni query sui rimborsi usa `SellerRefundManager::sqlIsRealSeller('sr')`.
- **Causale default:** `Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}`, max **140** caratteri; nome beneficiario max **70**; `MsgId`/`EndToEndId` max **35**.
- **Namespace XML:** `urn:CBI:xsd:CBIPaymentRequest.00.04.01`; `BtchBookg = true`; `ChrgBr = SLEV`; `SvcLvl/Cd = SEPA`.
- **CUC obbligatorio** (senza CUC la generazione è bloccata).
- **Test:** non c'è suite e `php` non è nel PATH locale. I test sono la pagina self-test `admin/?page=sepa-selftest` (pattern di `import-libri-selftest.php`), eseguita **su staging** dall'utente (o in locale con `php` se installato: vedi Task 2 step "Esecuzione"). "Run test" negli step = aprire `https://<staging>/admin/?page=sepa-selftest` da admin e leggere PASS/FAIL. Non dichiarare "testato" se non è stato eseguito.

---

## File Structure

| File | Responsabilità |
|---|---|
| `sql/202610010001_sepa_distinte.sql` (crea) | enum `xmlsaved`, tabelle `sepa_batch`/`sepa_batch_item`, chiavi `site_settings` |
| `classes/SepaCbiExport.php` (crea) | helper puri (IBAN, paesi SEPA, maschera, charset, causale, giorno lavorativo) + `buildXml()` + `validate()` |
| `classes/SepaBatch.php` (crea) | `SepaBatchManager`: impostazioni ordinante, idonei/esclusi, crea/scarta/paga batch, storico |
| `classes/xsd/README.txt` (crea) | dove mettere l'XSD CBI ufficiale |
| `api/admin/sepa-batch-download.php` (crea) | POST → crea batch e scarica XML |
| `admin/pages/seller-refund-sepa.php` (crea) | pagina "Distinte SEPA" |
| `admin/pages/sepa-selftest.php` (crea) | self-test delle parti pure |
| `inc/include-classes.php` (modifica) | require delle due nuove classi |
| `admin/index.php` (modifica) | `$allowedPages` += `seller-refund-sepa`, `sepa-selftest` |
| `classes/SellerRefund.php` (modifica) | `sqlIsRealSeller()` public; `xmlsaved` nei filtri e nel riepilogo |
| `admin/pages/seller-refunds.php` (modifica) | link "Distinte SEPA", badge/filtro/card `xmlsaved` |
| `admin/pages/seller-refund-view.php` (modifica) | badge `xmlsaved`, sezione distinte del rimborso |
| `admin/pages/seller-refund-report.php` (modifica) | opzione filtro Stato `xmlsaved` |
| `context/*.md` (modifica, solo repo root) | knowledge base |

Tutti i percorsi sopra sono relativi a `web/htdocs/` (tranne `context/`) e vanno duplicati in `web/htdocs/staging/`.

---

### Task 1: Migrazione DB

**Files:**
- Create: `web/htdocs/sql/202610010001_sepa_distinte.sql`
- Create: `web/htdocs/staging/sql/202610010001_sepa_distinte.sql` (copia)

**Interfaces:**
- Produces: colonna `seller_refund.status` con valore `xmlsaved`; tabelle `sepa_batch`, `sepa_batch_item`; chiavi `site_settings` `sepa_debtor_name`, `sepa_debtor_iban`, `sepa_debtor_cuc`, `sepa_debtor_country`, `sepa_remittance_template`.

- [ ] **Step 1: Scrivere la migrazione**

```sql
-- Distinte SEPA (XML CBI 04.01) per i rimborsi venditori con bonifico.
-- Spec: docs/superpowers/specs/2026-10-01-distinte-sepa-cbi-design.md
-- DA APPLICARE A MANO su ogni ambiente (staging, produzione).

-- 1. Nuovo stato: rimborso incluso in una distinta generata, non ancora pagato.
ALTER TABLE `seller_refund`
  MODIFY `status` ENUM('pending','partial','xmlsaved','completed','cancelled')
  NOT NULL DEFAULT 'pending';

-- 2. Una riga per file XML generato (il file NON viene salvato).
CREATE TABLE IF NOT EXISTS `sepa_batch` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `year` SMALLINT(4) NOT NULL,
  `msg_id` VARCHAR(35) NOT NULL COMMENT 'MsgId/PmtInfId inviati alla banca',
  `execution_date` DATE NOT NULL,
  `remittance_template` VARCHAR(140) NOT NULL,
  `debtor_iban_masked` VARCHAR(20) NOT NULL,
  `tx_count` INT(11) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('generated','paid','discarded') NOT NULL DEFAULT 'generated',
  `created_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME NULL,
  `paid_by` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_msg_id` (`msg_id`),
  INDEX `idx_year_status` (`year`, `status`),
  CONSTRAINT `fk_sepa_batch_created_by` FOREIGN KEY (`created_by`) REFERENCES `user`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sepa_batch_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `user`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Una riga per bonifico. Solo IBAN mascherato: quello completo resta cifrato in user.iban.
CREATE TABLE IF NOT EXISTS `sepa_batch_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL,
  `seller_refund_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `end_to_end_id` VARCHAR(35) NOT NULL,
  `remittance` VARCHAR(140) NOT NULL,
  `beneficiary_name` VARCHAR(70) NOT NULL,
  `iban_masked` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_refund` (`seller_refund_id`),
  CONSTRAINT `fk_sbi_batch` FOREIGN KEY (`batch_id`) REFERENCES `sepa_batch`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbi_refund` FOREIGN KEY (`seller_refund_id`) REFERENCES `seller_refund`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dati ordinante e causale (si compilano dalla pagina Distinte SEPA).
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('sepa_debtor_name', '', 'Distinte SEPA: intestatario conto del Comitato'),
  ('sepa_debtor_iban', '', 'Distinte SEPA: IBAN del Comitato'),
  ('sepa_debtor_cuc', '', 'Distinte SEPA: Codice Univoco CBI (CUC) assegnato dalla banca'),
  ('sepa_debtor_country', 'IT', 'Distinte SEPA: paese ordinante'),
  ('sepa_remittance_template', 'Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}', 'Distinte SEPA: causale (max 140 caratteri)');
```

- [ ] **Step 2: Copia in staging**

Run: `cp web/htdocs/sql/202610010001_sepa_distinte.sql web/htdocs/staging/sql/`

- [ ] **Step 3: Chiedere all'utente di applicarla su staging** e verificare con:

```sql
SHOW COLUMNS FROM seller_refund LIKE 'status';
SHOW TABLES LIKE 'sepa_batch%';
SELECT setting_key FROM site_settings WHERE setting_key LIKE 'sepa_%';
```
Expected: enum con `xmlsaved`; 2 tabelle; 5 chiavi.

- [ ] **Step 4: Commit**

```bash
git add web/htdocs/sql/202610010001_sepa_distinte.sql web/htdocs/staging/sql/202610010001_sepa_distinte.sql
git commit -m "Distinte SEPA: migrazione (stato xmlsaved, tabelle sepa_batch, impostazioni ordinante)"
```

---

### Task 2: `SepaCbiExport` — helper puri + pagina self-test

**Files:**
- Create: `web/htdocs/classes/SepaCbiExport.php`
- Create: `web/htdocs/admin/pages/sepa-selftest.php`
- Modify: `web/htdocs/inc/include-classes.php` (dopo `require_once ROOT_PATH . 'classes/OrderEmail.php';`)
- Modify: `web/htdocs/admin/index.php` (`$allowedPages`)
- Copie identiche in `web/htdocs/staging/`

**Interfaces:**
- Produces (tutti `public static`):
  - `normalizeIban(string $iban): string` — maiuscolo, senza spazi
  - `ibanIsValid(string $iban): bool`
  - `isSepaCountry(string $cc): bool`
  - `ibanMask(string $iban): string` — `IT60…3456`
  - `toSepaCharset(string $text, int $maxLen): string`
  - `buildRemittance(string $template, int $year, array $praticas, int $maxLen = 140): string`
  - `nextBusinessDay(DateTimeInterface $from): string` — `Y-m-d`
  - `abiFromIban(string $iban): string` — 5 cifre ABI di un IBAN IT

- [ ] **Step 1: Scrivere la pagina self-test (test che falliscono)**

`web/htdocs/admin/pages/sepa-selftest.php`:

```php
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
```

Nota sul test "tronca a pratica intera": le pratiche sono separate da `", "`; tagliando dopo l'ultima pratica intera si ottiene `"..., 1012, 1013 ..."`. Il test verifica che la causale finisca con `\d{4} \.\.\.$` e **non** con `, ...` né con un numero attaccato ai puntini.

- [ ] **Step 2: Registrare pagina e classe**

In `web/htdocs/admin/index.php`, nell'array `$allowedPages`, sostituire la riga
```php
    'seller-refund-view', 'seller-refund-newsletter', 'seller-refund-report', 'seller-orders-report',
```
con
```php
    'seller-refund-view', 'seller-refund-newsletter', 'seller-refund-report', 'seller-orders-report',
    'seller-refund-sepa', 'sepa-selftest',
```

In `web/htdocs/inc/include-classes.php`, dopo `require_once ROOT_PATH . 'classes/OrderEmail.php';` aggiungere:
```php
  require_once ROOT_PATH . 'classes/SepaCbiExport.php';
```

Creare un file `web/htdocs/classes/SepaCbiExport.php` minimo perché il require non rompa il sito:
```php
<?php

class SepaCbiExport {
}
```

- [ ] **Step 3: Copiare in staging ed eseguire i test**

```bash
for f in admin/pages/sepa-selftest.php admin/index.php inc/include-classes.php classes/SepaCbiExport.php; do cp web/htdocs/$f web/htdocs/staging/$f; done
```
Esecuzione: l'utente apre `admin/?page=sepa-selftest` su staging. Expected: FAIL su tutti i test (metodi inesistenti → fatal error "Call to undefined method"). Se l'utente ha `php` in locale, alternativa equivalente: nessuna (la pagina richiede il bootstrap); accettare l'esecuzione su staging.

- [ ] **Step 4: Implementare gli helper**

`web/htdocs/classes/SepaCbiExport.php`:

```php
<?php

/**
 * Distinte SEPA in formato XML CBI (CBIPaymentRequest.00.04.01).
 *
 * Classe pura: nessun accesso al DB. Riceve dati già pronti e restituisce
 * stringhe. Gli IBAN in chiaro esistono solo nei parametri e nell'XML
 * restituito: non vanno loggati né salvati.
 */
class SepaCbiExport {

    const XML_NAMESPACE = 'urn:CBI:xsd:CBIPaymentRequest.00.04.01';
    const XSD_FILE = 'CBIPaymentRequest.00.04.01.xsd';
    const MAX_REMITTANCE = 140;
    const MAX_NAME = 70;

    /** Lunghezza IBAN per i paesi dell'area SEPA (EPC409-09). */
    private static $sepaIbanLengths = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18,
        'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28,
        'IE' => 22, 'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20,
        'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
        'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27,
        'VA' => 22,
    ];

    public static function normalizeIban($iban) {
        return strtoupper(preg_replace('/\s+/', '', (string)$iban));
    }

    public static function isSepaCountry($cc) {
        return isset(self::$sepaIbanLengths[strtoupper((string)$cc)]);
    }

    /**
     * Formato, lunghezza per paese (solo paesi SEPA) e controllo mod 97.
     */
    public static function ibanIsValid($iban) {
        $iban = self::normalizeIban($iban);
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $cc = substr($iban, 0, 2);
        if (isset(self::$sepaIbanLengths[$cc]) && strlen($iban) !== self::$sepaIbanLengths[$cc]) {
            return false;
        }
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
        }
        // mod 97 a blocchi: il numero è troppo lungo per un intero
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int)($remainder . $chunk) % 97;
        }
        return $remainder === 1;
    }

    public static function ibanMask($iban) {
        $iban = self::normalizeIban($iban);
        if (strlen($iban) < 8) {
            return str_repeat('*', strlen($iban));
        }
        return substr($iban, 0, 4) . '…' . substr($iban, -4);
    }

    /** Codice ABI (5 cifre) di un IBAN italiano: IT + 2 check + CIN + ABI. */
    public static function abiFromIban($iban) {
        return substr(self::normalizeIban($iban), 5, 5);
    }

    /**
     * Riduce il testo al set di caratteri SEPA:
     * a-z A-Z 0-9 / - ? : ( ) . , ' + spazio.
     * Gli accenti diventano la lettera base, il resto diventa spazio;
     * gli spazi multipli si riducono a uno.
     */
    public static function toSepaCharset($text, $maxLen) {
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','À'=>'A','Á'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','Ò'=>'O','Ó'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','Ø'=>'O',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N','ß'=>'ss','ý'=>'y','ÿ'=>'y','Ý'=>'Y',
            'š'=>'s','Š'=>'S','ž'=>'z','Ž'=>'Z','č'=>'c','Č'=>'C','ć'=>'c','Ć'=>'C',
            '’'=>"'",'‘'=>"'",'`'=>"'",'“'=>' ','”'=>' ','–'=>'-','—'=>'-','…'=>'...',
        ];
        $text = strtr((string)$text, $map);
        $text = preg_replace("~[^A-Za-z0-9/\-?:().,'+ ]~", ' ', $text);
        $text = trim(preg_replace('/ {2,}/', ' ', $text));
        if (strlen($text) > $maxLen) {
            $text = rtrim(substr($text, 0, $maxLen));
        }
        return $text;
    }

    /**
     * Causale da template con {anno} e {pratiche}. Se supera $maxLen, l'elenco
     * pratiche viene troncato all'ultima pratica intera e chiuso con " ...".
     */
    public static function buildRemittance($template, $year, array $praticas, $maxLen = self::MAX_REMITTANCE) {
        $praticas = array_values(array_map('intval', $praticas));
        $fill = function ($list) use ($template, $year) {
            return self::toSepaCharset(
                str_replace(['{anno}', '{pratiche}'], [(string)(int)$year, $list], $template),
                10000
            );
        };

        $text = $fill(implode(', ', $praticas));
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        for ($n = count($praticas) - 1; $n >= 1; $n--) {
            $text = $fill(implode(', ', array_slice($praticas, 0, $n)) . ' ...');
            if (strlen($text) <= $maxLen) {
                return $text;
            }
        }
        // Template da solo già troppo lungo: taglio secco.
        return self::toSepaCharset($fill('...'), $maxLen);
    }

    /** Prossimo giorno lavorativo (salta sabato e domenica) dopo $from. */
    public static function nextBusinessDay(DateTimeInterface $from) {
        $d = new DateTime($from->format('Y-m-d'));
        do {
            $d->modify('+1 day');
        } while ((int)$d->format('N') >= 6);
        return $d->format('Y-m-d');
    }
}
```

- [ ] **Step 5: Copiare in staging ed eseguire i test**

Run: `cp web/htdocs/classes/SepaCbiExport.php web/htdocs/staging/classes/SepaCbiExport.php`
Esecuzione: `admin/?page=sepa-selftest` su staging. Expected: "TUTTI PASS". Se un test fallisce, il "Dettaglio" mostra il valore ottenuto: correggere l'implementazione (non il test) salvo errore evidente nel test.

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/classes/SepaCbiExport.php web/htdocs/admin/pages/sepa-selftest.php web/htdocs/admin/index.php web/htdocs/inc/include-classes.php \
        web/htdocs/staging/classes/SepaCbiExport.php web/htdocs/staging/admin/pages/sepa-selftest.php web/htdocs/staging/admin/index.php web/htdocs/staging/inc/include-classes.php
git commit -m "Distinte SEPA: helper IBAN, charset e causale con pagina di self-test"
```

---

### Task 3: `SepaCbiExport::buildXml()` + `validate()`

**Files:**
- Modify: `web/htdocs/classes/SepaCbiExport.php`
- Modify: `web/htdocs/admin/pages/sepa-selftest.php`
- Create: `web/htdocs/classes/xsd/README.txt`
- Copie in staging

**Interfaces:**
- Consumes: helper del Task 2.
- Produces:
  - `SepaCbiExport::buildXml(array $debtor, string $msgId, string $executionDate, array $transactions, DateTimeInterface $createdAt = null): string`
    - `$debtor = ['name' => string, 'iban' => string, 'cuc' => string, 'country' => 'IT']`
    - ogni transazione: `['end_to_end_id' => string, 'amount' => float, 'name' => string, 'iban' => string, 'remittance' => string]`
    - lancia `InvalidArgumentException` (messaggio in italiano) se: nessuna transazione, importo <= 0, IBAN non valido.
  - `SepaCbiExport::validate(string $xml): array` → `['skipped' => bool, 'errors' => string[]]`
  - costante `SepaCbiExport::EXEC_DATE_NESTED` (bool)

- [ ] **Step 1: Aggiungere i test alla self-test page**

In `sepa-selftest.php`, subito prima della riga `    // --- Ambiente server ---`, inserire:

```php
    // --- XML ---
    $debtor = ['name' => 'Comitato Genitori Liceo Da Vinci', 'iban' => 'IT60X0542811101000000123456', 'cuc' => 'ABC12345', 'country' => 'IT'];
    $txs = [
      ['end_to_end_id' => 'MDV2026-R1-B9', 'amount' => 12.5, 'name' => 'Rossi Màrio', 'iban' => 'IT60X0542811101000000123456', 'remittance' => 'Rimb. Mercatino Da Vinci 2026 - Pratica 12'],
      ['end_to_end_id' => 'MDV2026-R2-B9', 'amount' => 7.1, 'name' => 'Müller Hans', 'iban' => 'DE89370400440532013000', 'remittance' => 'Rimb. Mercatino Da Vinci 2026 - Pratica 45'],
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
      $v = SepaCbiExport::validate($xml);
      $check('Validazione XSD', $v['skipped'] || count($v['errors']) === 0,
        $v['skipped'] ? 'XSD non presente: saltata' : implode(' | ', $v['errors']));
    }
    $threw = false;
    try { SepaCbiExport::buildXml($debtor, 'X', '2026-10-02', [['end_to_end_id' => 'A', 'amount' => 5, 'name' => 'A', 'iban' => 'IT60X0542811101000000123457', 'remittance' => 'x']]); }
    catch (InvalidArgumentException $e) { $threw = true; }
    $check('buildXml rifiuta IBAN non valido', $threw);
    $threw = false;
    try { SepaCbiExport::buildXml($debtor, 'X', '2026-10-02', []); }
    catch (InvalidArgumentException $e) { $threw = true; }
    $check('buildXml rifiuta elenco vuoto', $threw);
```

- [ ] **Step 2: Copiare in staging ed eseguire** — Expected: fatal "undefined method buildXml".

Run: `cp web/htdocs/admin/pages/sepa-selftest.php web/htdocs/staging/admin/pages/sepa-selftest.php`

- [ ] **Step 3: Implementare `buildXml()` e `validate()`**

In `SepaCbiExport`, dopo `const MAX_NAME = 70;` aggiungere:

```php
    /**
     * Forma di ReqdExctnDt. Nella 04.00 è una data semplice; la 04.01 segue
     * pain.001.001.09 dove è <ReqdExctnDt><Dt>…</Dt></ReqdExctnDt>.
     * DA CONFERMARE con l'XSD ufficiale / un XML esportato da UniCredit.
     */
    const EXEC_DATE_NESTED = true;
```

Prima della `}` finale della classe aggiungere:

```php
    /**
     * Costruisce il messaggio CBIPaymentRequest.00.04.01 (un solo PmtInf,
     * addebito cumulativo). Tutti i testi passano da toSepaCharset().
     */
    public static function buildXml(array $debtor, $msgId, $executionDate, array $transactions, DateTimeInterface $createdAt = null) {
        if (count($transactions) === 0) {
            throw new InvalidArgumentException('Nessun bonifico da inserire nella distinta.');
        }
        $debtorIban = self::normalizeIban($debtor['iban']);
        if (!self::ibanIsValid($debtorIban)) {
            throw new InvalidArgumentException("IBAN dell'ordinante non valido.");
        }

        $totalCents = 0;
        foreach ($transactions as $i => $tx) {
            $cents = (int)round(((float)$tx['amount']) * 100);
            if ($cents <= 0) {
                throw new InvalidArgumentException('Importo non valido per ' . $tx['end_to_end_id'] . '.');
            }
            if (!self::ibanIsValid($tx['iban'])) {
                throw new InvalidArgumentException('IBAN non valido per ' . $tx['end_to_end_id'] . '.');
            }
            $transactions[$i]['cents'] = $cents;
            $totalCents += $cents;
        }
        $fmt = function ($cents) { return number_format($cents / 100, 2, '.', ''); };
        $createdAt = $createdAt ?: new DateTime();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $ns = self::XML_NAMESPACE;
        $el = function ($parent, $name, $text = null) use ($dom, $ns) {
            $node = $dom->createElementNS($ns, $name);
            if ($text !== null) {
                $node->appendChild($dom->createTextNode((string)$text));
            }
            $parent->appendChild($node);
            return $node;
        };

        $root = $dom->createElementNS($ns, 'CBIPaymentRequest');
        $dom->appendChild($root);

        // --- Testata ---
        $hdr = $el($root, 'GrpHdr');
        $el($hdr, 'MsgId', $msgId);
        $el($hdr, 'CreDtTm', $createdAt->format('Y-m-d\TH:i:s'));
        $el($hdr, 'NbOfTxs', count($transactions));
        $el($hdr, 'CtrlSum', $fmt($totalCents));
        $initg = $el($hdr, 'InitgPty');
        $el($initg, 'Nm', self::toSepaCharset($debtor['name'], self::MAX_NAME));
        $othr = $el($el($el($initg, 'Id'), 'OrgId'), 'Othr');
        $el($othr, 'Id', strtoupper(trim($debtor['cuc'])));
        $el($othr, 'Issr', 'CBI');

        // --- Disposizione (unico addebito) ---
        $pmt = $el($root, 'PmtInf');
        $el($pmt, 'PmtInfId', $msgId);
        $el($pmt, 'PmtMtd', 'TRF');
        $el($pmt, 'BtchBookg', 'true');
        $tp = $el($pmt, 'PmtTpInf');
        $el($tp, 'InstrPrty', 'NORM');
        $el($el($tp, 'SvcLvl'), 'Cd', 'SEPA');
        $exec = $el($pmt, 'ReqdExctnDt', self::EXEC_DATE_NESTED ? null : $executionDate);
        if (self::EXEC_DATE_NESTED) {
            $el($exec, 'Dt', $executionDate);
        }
        $dbtr = $el($pmt, 'Dbtr');
        $el($dbtr, 'Nm', self::toSepaCharset($debtor['name'], self::MAX_NAME));
        $el($el($dbtr, 'PstlAdr'), 'Ctry', strtoupper($debtor['country']));
        $el($el($el($pmt, 'DbtrAcct'), 'Id'), 'IBAN', $debtorIban);
        $el($el($el($el($pmt, 'DbtrAgt'), 'FinInstnId'), 'ClrSysMmbId'), 'MmbId', self::abiFromIban($debtorIban));
        $el($pmt, 'ChrgBr', 'SLEV');

        // --- Bonifici ---
        foreach (array_values($transactions) as $n => $tx) {
            $iban = self::normalizeIban($tx['iban']);
            $t = $el($pmt, 'CdtTrfTxInf');
            $pid = $el($t, 'PmtId');
            $el($pid, 'InstrId', $n + 1);
            $el($pid, 'EndToEndId', $tx['end_to_end_id']);
            $amt = $el($el($t, 'Amt'), 'InstdAmt', $fmt($tx['cents']));
            $amt->setAttribute('Ccy', 'EUR');
            $cdtr = $el($t, 'Cdtr');
            $el($cdtr, 'Nm', self::toSepaCharset($tx['name'], self::MAX_NAME));
            $el($el($cdtr, 'PstlAdr'), 'Ctry', substr($iban, 0, 2));
            $el($el($el($t, 'CdtrAcct'), 'Id'), 'IBAN', $iban);
            $el($el($t, 'RmtInf'), 'Ustrd', self::toSepaCharset($tx['remittance'], self::MAX_REMITTANCE));
        }

        return $dom->saveXML();
    }

    /**
     * Valida contro l'XSD CBI se presente in classes/xsd/ (vedi README.txt).
     * @return array ['skipped' => bool, 'errors' => string[]]
     */
    public static function validate($xml) {
        $xsd = __DIR__ . '/xsd/' . self::XSD_FILE;
        if (!is_file($xsd)) {
            return ['skipped' => true, 'errors' => []];
        }
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml) && $dom->schemaValidate($xsd);
        $errors = [];
        if (!$ok) {
            foreach (libxml_get_errors() as $e) {
                $errors[] = 'riga ' . $e->line . ': ' . trim($e->message);
            }
            if (!$errors) {
                $errors[] = 'XML non valido.';
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return ['skipped' => false, 'errors' => $errors];
    }
```

`web/htdocs/classes/xsd/README.txt`:

```
Schema XSD ufficiale CBI per le distinte SEPA.

Mettere qui il file CBIPaymentRequest.00.04.01.xsd (e gli eventuali XSD che
importa, con i nomi originali). Si ottiene dal Consorzio CBI o dalla banca
(UniCredit: documentazione "XML SEPA CBI v.04.01").

Se il file c'e', SepaCbiExport::validate() controlla ogni distinta prima del
download e blocca i file non conformi. Se manca, la validazione di schema
viene saltata (restano i controlli applicativi).
```

- [ ] **Step 4: Copiare in staging ed eseguire** — Expected: TUTTI PASS (la riga "Validazione XSD" passa con dettaglio "XSD non presente: saltata" finché l'XSD non c'è).

```bash
mkdir -p web/htdocs/staging/classes/xsd
for f in classes/SepaCbiExport.php admin/pages/sepa-selftest.php classes/xsd/README.txt; do cp web/htdocs/$f web/htdocs/staging/$f; done
```

- [ ] **Step 5: Commit**

```bash
git add web/htdocs/classes/SepaCbiExport.php web/htdocs/admin/pages/sepa-selftest.php web/htdocs/classes/xsd/README.txt \
        web/htdocs/staging/classes/SepaCbiExport.php web/htdocs/staging/admin/pages/sepa-selftest.php web/htdocs/staging/classes/xsd/README.txt
git commit -m "Distinte SEPA: costruzione XML CBI 04.01 e validazione XSD opzionale"
```

---

### Task 4: `SepaBatchManager` — impostazioni ordinante e idonei

**Files:**
- Create: `web/htdocs/classes/SepaBatch.php`
- Modify: `web/htdocs/classes/SellerRefund.php:154` (`private function sqlIsRealSeller` → `public function sqlIsRealSeller`)
- Modify: `web/htdocs/inc/include-classes.php`
- Modify: `web/htdocs/admin/pages/sepa-selftest.php`
- Copie in staging

**Interfaces:**
- Consumes: `SepaCbiExport::*` helper; `SellerRefundManager::sqlIsRealSeller($alias)` (resa public); `Encryption::isConfigured()`, `Encryption::decrypt()`; `SiteSettings::get()/set()`.
- Produces:
  - `class SepaException extends Exception` (messaggio in italiano, mostrabile all'utente)
  - `SepaBatchManager::isInstalled(): bool`
  - `SepaBatchManager::getDebtorSettings(): array` → `['name','iban','cuc','country','template']`
  - `SepaBatchManager::debtorProblems(array $s): string[]` (vuoto = ok)
  - `SepaBatchManager::saveDebtorSettings(array $data): string[]` (errori; vuoto = salvato)
  - `SepaBatchManager::saveTemplate(string $template): string[]`
  - `SepaBatchManager::getCandidates(int $year): array` → `['eligible' => object[], 'excluded' => object[]]`, ogni oggetto con: `id, user_id, first_name, last_name, status, amount_owed, amount_paid, due (float), pratica_numbers (string), beneficiary_name, iban_masked, last_batch_id (int|null), last_batch_date (string|null)`; gli esclusi anche `reason`. **Il campo `iban` in chiaro è presente solo negli oggetti restituiti da `getCandidates($year, true)`** (uso interno di `createBatch`).

- [ ] **Step 1: Test (self-test, solo lettura)**

In `sepa-selftest.php`, prima di `// --- Ambiente server ---`:

```php
    // --- SepaBatchManager (sola lettura) ---
    if (class_exists('SepaBatchManager')) {
      $sbm = new SepaBatchManager();
      $check('Migrazione applicata (tabelle sepa_batch)', $sbm->isInstalled(), 'se FAIL: applicare sql/202610010001_sepa_distinte.sql');
      if ($sbm->isInstalled()) {
        $p = $sbm->debtorProblems(['name' => '', 'iban' => 'IT60X0542811101000000123457', 'cuc' => '', 'country' => 'IT', 'template' => 'x']);
        $check('debtorProblems trova 3 problemi', count($p) === 3, implode(' | ', $p));
        $p = $sbm->debtorProblems(['name' => 'Comitato', 'iban' => 'IT60X0542811101000000123456', 'cuc' => 'ABC12345', 'country' => 'IT', 'template' => 'x']);
        $check('debtorProblems ok', count($p) === 0, implode(' | ', $p));
        $c = $sbm->getCandidates((int)date('Y'));
        $check('getCandidates restituisce eligible/excluded', isset($c['eligible'], $c['excluded']));
        $leak = false;
        foreach (array_merge($c['eligible'], $c['excluded']) as $row) { if (isset($row->iban)) { $leak = true; } }
        $check('getCandidates senza IBAN in chiaro', !$leak);
      }
    } else {
      $check('Classe SepaBatchManager caricata', false);
    }
```

- [ ] **Step 2: Copiare in staging ed eseguire** — Expected: FAIL "Classe SepaBatchManager caricata".

Run: `cp web/htdocs/admin/pages/sepa-selftest.php web/htdocs/staging/admin/pages/sepa-selftest.php`

- [ ] **Step 3: Rendere pubblico `sqlIsRealSeller`**

In `web/htdocs/classes/SellerRefund.php` sostituire `    private function sqlIsRealSeller($alias = 'sr') {` con `    public function sqlIsRealSeller($alias = 'sr') {`.

- [ ] **Step 4: Implementare la parte di lettura**

`web/htdocs/classes/SepaBatch.php`:

```php
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
```

In `inc/include-classes.php`, dopo la riga `SepaCbiExport.php` aggiunta nel Task 2:
```php
  require_once ROOT_PATH . 'classes/SepaBatch.php';
```

- [ ] **Step 5: Copiare in staging ed eseguire** — Expected: TUTTI PASS (con migrazione applicata su staging).

```bash
for f in classes/SepaBatch.php classes/SellerRefund.php inc/include-classes.php admin/pages/sepa-selftest.php; do cp web/htdocs/$f web/htdocs/staging/$f; done
```

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/classes/SepaBatch.php web/htdocs/classes/SellerRefund.php web/htdocs/inc/include-classes.php web/htdocs/admin/pages/sepa-selftest.php \
        web/htdocs/staging/classes/SepaBatch.php web/htdocs/staging/classes/SellerRefund.php web/htdocs/staging/inc/include-classes.php web/htdocs/staging/admin/pages/sepa-selftest.php
git commit -m "Distinte SEPA: dati ordinante e rimborsi idonei/esclusi"
```

---

### Task 5: `SepaBatchManager` — crea, scarta, paga, storico

**Files:**
- Modify: `web/htdocs/classes/SepaBatch.php`
- Copia in staging

**Interfaces:**
- Consumes: `getCandidates($year, true)`, `getDebtorSettings()`, `debtorProblems()`, `SepaCbiExport::buildXml()/validate()/buildRemittance()/ibanMask()`.
- Produces:
  - `createBatch(int $year, int[] $refundIds, string $executionDate, string $template, int $operatorId): array` → `['batch_id' => int, 'msg_id' => string, 'xml' => string, 'filename' => string, 'tx_count' => int, 'total' => float]`; lancia `SepaException`.
  - `discardBatch(int $batchId, int $operatorId): void` — lancia `SepaException`.
  - `markBatchPaid(int $batchId, string $paymentDate, int $operatorId): array` → `['paid' => int, 'skipped' => int]`; lancia `SepaException`.
  - `getBatchesForYear(int $year): object[]` (con `created_by_name`, `paid_by_name`)
  - `getBatchItems(int $batchId): object[]` (con `first_name`, `last_name`, `superseded` bool)
  - `getBatchesForRefund(int $refundId): object[]` (`id, msg_id, status, created_at, execution_date, amount, superseded`)

Questi metodi scrivono sul DB: non vanno nella self-test (che non scrive). Si verificano nel Task 8 su staging.

- [ ] **Step 1: Implementare**

In `SepaBatchManager`, prima di `    /** Stessa logica di UserManager::getIBAN()` aggiungere:

```php
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

            $txs = [];
            $total = 0;
            foreach ($selected as $c) {
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
            $pdo->rollBack();
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
            $pdo->prepare("UPDATE sepa_batch SET status = 'discarded' WHERE id = ?")->execute([(int)$batchId]);
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
            $pdo->rollBack();
            error_log('SepaBatchManager::discardBatch: ' . $e->getMessage());
            throw new SepaException('Errore nello scarto della distinta: nessuna modifica è stata fatta.');
        }
    }

    /**
     * Registra i pagamenti di una distinta eseguita dalla banca. Salta le
     * righe superate (rimborso presente in una distinta più recente non
     * scartata) e i rimborsi già completati/annullati nel frattempo.
     * Stessa logica di SellerRefundManager::recordPayment(), ma sulla
     * connessione di questa transazione.
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
        $skipped = 0;
        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            $insPay = $pdo->prepare("INSERT INTO seller_refund_payment
                (seller_refund_id, amount, payment_method, payment_date, reference, notes, operator_id)
                VALUES (?, ?, 'wire_transfer', ?, ?, ?, ?)");
            $updRefund = $pdo->prepare("UPDATE seller_refund SET amount_paid = ?, payment_date = ?, status = ? WHERE id = ?");
            $getRefund = $pdo->prepare("SELECT amount_owed, amount_paid, status FROM seller_refund WHERE id = ? FOR UPDATE");

            foreach ($this->getBatchItems($batchId) as $item) {
                $getRefund->execute([$item->seller_refund_id]);
                $r = $getRefund->fetch(PDO::FETCH_ASSOC);
                if ($item->superseded || !$r || in_array($r['status'], ['completed', 'cancelled'], true)) {
                    $skipped++;
                    continue;
                }
                $insPay->execute([$item->seller_refund_id, $item->amount, $paymentDate, $batch->msg_id,
                                  'Distinta SEPA #' . (int)$batchId, (int)$operatorId]);
                $newPaid = round((float)$r['amount_paid'] + (float)$item->amount, 2);
                $newStatus = $newPaid >= (float)$r['amount_owed'] ? 'completed' : 'partial';
                $updRefund->execute([$newPaid, $paymentDate, $newStatus, $item->seller_refund_id]);
                $paid++;
            }
            $pdo->prepare("UPDATE sepa_batch SET status = 'paid', paid_at = NOW(), paid_by = ? WHERE id = ?")
                ->execute([(int)$operatorId, (int)$batchId]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('SepaBatchManager::markBatchPaid: ' . $e->getMessage());
            throw new SepaException('Errore nella registrazione dei pagamenti: nessuna modifica è stata fatta.');
        }
        return ['paid' => $paid, 'skipped' => $skipped];
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
              JOIN user u ON u.id = sr.user_id
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
```

- [ ] **Step 2: Rileggere** il codice contro la spec ("Regole di dominio"): idonei, importo, superate, scarta, pagati. Verifica funzionale nel Task 8.

- [ ] **Step 3: Copiare in staging, aprire la self-test** (deve restare TUTTI PASS: controlla che il file non abbia errori di sintassi).

Run: `cp web/htdocs/classes/SepaBatch.php web/htdocs/staging/classes/SepaBatch.php`

- [ ] **Step 4: Commit**

```bash
git add web/htdocs/classes/SepaBatch.php web/htdocs/staging/classes/SepaBatch.php
git commit -m "Distinte SEPA: creazione, scarto e pagamento delle distinte in transazione"
```

---

### Task 6: Pagina "Distinte SEPA" + endpoint di download

Pagina ed endpoint nello stesso task (il form di generazione e il suo handler CSRF devono andare online insieme).

**Files:**
- Create: `web/htdocs/api/admin/sepa-batch-download.php`
- Create: `web/htdocs/admin/pages/seller-refund-sepa.php`
- Modify: `web/htdocs/admin/pages/seller-refunds.php:163` (link)
- Copie in staging

**Interfaces:**
- Consumes: tutto `SepaBatchManager` (Task 4–5).
- Produces: URL `admin/?page=seller-refund-sepa&year=YYYY`; POST `api/admin/sepa-batch-download.php` con `csrf_token`, `year`, `execution_date`, `template`, `refund_ids[]`, `download_token`.

- [ ] **Step 1: Endpoint**

`web/htdocs/api/admin/sepa-batch-download.php`:

```php
<?php
// Genera una distinta SEPA (XML CBI) e la restituisce come download.
// Endpoint standalone: nessun output prima degli header. L'XML non viene salvato.

require_once '../../inc/init.php';
global $loggedInUser;

if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  header('HTTP/1.1 403 Forbidden');
  echo 'Forbidden';
  exit;
}

$year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$pageUrl = ROOT_URL . 'admin/?page=seller-refund-sepa&year=' . $year;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $pageUrl);
  exit;
}
CSRF::validateOrDie($pageUrl . '&msg=csrf_error');

$mgr = new SepaBatchManager();
try {
  if (!$mgr->isInstalled()) {
    throw new SepaException('La migrazione 202610010001_sepa_distinte.sql non è stata applicata su questo database.');
  }
  $template = isset($_POST['template']) ? (string)$_POST['template'] : '';
  $current = $mgr->getDebtorSettings();
  if ($template !== '' && $template !== $current['template']) {
    $errors = $mgr->saveTemplate($template);
    if ($errors) {
      throw new SepaException(implode(' ', $errors));
    }
  }
  $result = $mgr->createBatch(
    $year,
    isset($_POST['refund_ids']) ? (array)$_POST['refund_ids'] : [],
    isset($_POST['execution_date']) ? (string)$_POST['execution_date'] : '',
    $template,
    (int)$loggedInUser->id
  );
} catch (SepaException $e) {
  $_SESSION['sepa_error'] = $e->getMessage();
  header('Location: ' . $pageUrl . '&msg=sepa_error');
  exit;
}

log_activity($loggedInUser->id, 'admin_sepa_batch_created',
  'batch: ' . $result['batch_id'] . ', bonifici: ' . $result['tx_count'] . ', totale: ' . number_format($result['total'], 2, '.', ''));

// Il JS della pagina aspetta questo cookie per sapere che il download è partito.
$dlToken = isset($_POST['download_token']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string)$_POST['download_token']) : '';
if ($dlToken !== '') {
  // header() invece di setcookie(): l'array di opzioni di setcookie richiede PHP >= 7.3.
  header('Set-Cookie: sepa_dl=' . $dlToken . '; Max-Age=120; Path=/; SameSite=Lax', false);
}

header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($result['xml']));
echo $result['xml'];
exit;
```

- [ ] **Step 2: Pagina admin**

`web/htdocs/admin/pages/seller-refund-sepa.php`:

```php
<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $sepaMgr = new SepaBatchManager();
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
  $pageUrl = ROOT_URL . 'admin/?page=seller-refund-sepa&year=' . $selectedYear;

  $installed = $sepaMgr->isInstalled();
  $errorText = '';
  $infoText = '';

  if ($installed && isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      try {
        switch ($_POST['action']) {
          case 'save_debtor':
            $errors = $sepaMgr->saveDebtorSettings([
              'name' => $_POST['debtor_name'] ?? '',
              'iban' => $_POST['debtor_iban'] ?? '',
              'cuc' => $_POST['debtor_cuc'] ?? '',
              'country' => $_POST['debtor_country'] ?? 'IT',
            ]);
            if ($errors) {
              $errorText = implode(' ', $errors);
            } else {
              log_activity($loggedInUser->id, 'admin_sepa_debtor_updated', 'dati ordinante aggiornati');
              $infoText = 'Dati ordinante salvati.';
            }
            break;

          case 'mark_paid':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $res = $sepaMgr->markBatchPaid($batchId, (string)($_POST['payment_date'] ?? ''), (int)$loggedInUser->id);
            log_activity($loggedInUser->id, 'admin_sepa_batch_paid',
              'batch: ' . $batchId . ', pagati: ' . $res['paid'] . ', saltati: ' . $res['skipped']);
            $infoText = 'Distinta #' . $batchId . ' segnata come pagata: ' . $res['paid'] . ' pagamenti registrati'
              . ($res['skipped'] ? ', ' . $res['skipped'] . ' saltati (in una distinta più recente o già saldati).' : '.');
            break;

          case 'discard':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $sepaMgr->discardBatch($batchId, (int)$loggedInUser->id);
            log_activity($loggedInUser->id, 'admin_sepa_batch_discarded', 'batch: ' . $batchId);
            $infoText = 'Distinta #' . $batchId . ' scartata.';
            break;
        }
      } catch (SepaException $e) {
        $errorText = $e->getMessage();
      }
    }
  }

  if (isset($_GET['msg']) && $_GET['msg'] === 'sepa_error' && !empty($_SESSION['sepa_error'])) {
    $errorText = $_SESSION['sepa_error'];
    unset($_SESSION['sepa_error']);
  }
  if (isset($_GET['msg']) && $_GET['msg'] === 'sepa_generated') {
    $infoText = 'Distinta generata e scaricata. Caricala su UniCredit e, quando i bonifici sono eseguiti, usa "Segna come pagati".';
  }
  if (isset($_GET['msg']) && $_GET['msg'] === 'csrf_error') {
    $alertMsg = 'csrf_error';
  }

  $debtor = $installed ? $sepaMgr->getDebtorSettings() : null;
  $debtorProblems = $installed ? $sepaMgr->debtorProblems($debtor) : [];
  $candidates = $installed ? $sepaMgr->getCandidates($selectedYear) : ['eligible' => [], 'excluded' => []];
  $batches = $installed ? $sepaMgr->getBatchesForYear($selectedYear) : [];
  $defaultExecDate = SepaCbiExport::nextBusinessDay(new DateTime());

  $statusText = ['pending' => 'In attesa', 'partial' => 'Parziale', 'xmlsaved' => 'Distinta generata'];
  $statusBadge = ['pending' => 'badge-warning', 'partial' => 'badge-info', 'xmlsaved' => 'badge-primary'];
  $batchStatusText = ['generated' => 'Generata', 'paid' => 'Pagata', 'discarded' => 'Scartata'];
  $batchStatusBadge = ['generated' => 'badge-primary', 'paid' => 'badge-success', 'discarded' => 'badge-secondary'];
  $euro = function ($v) { return '&euro; ' . number_format((float)$v, 2, ',', '.'); };
?>

<h1>Distinte SEPA - Rimborsi <?php echo $selectedYear; ?></h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna ai rimborsi
</a>

<form method="get" class="form-inline mb-3">
  <input type="hidden" name="page" value="seller-refund-sepa">
  <label class="mr-2" for="year">Anno</label>
  <select name="year" id="year" class="form-control" onchange="this.form.submit()">
    <?php for ($y = $currentYear; $y >= $currentYear - 3; $y--): ?>
      <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
    <?php endfor; ?>
  </select>
</form>

<?php if ($alertMsg === 'csrf_error'): ?>
  <div class="alert alert-danger">Sessione scaduta o richiesta non valida: ricarica la pagina e riprova.</div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="alert alert-danger"><?php echo esc_html($errorText); ?></div>
<?php endif; ?>
<?php if ($infoText !== ''): ?>
  <div class="alert alert-success"><?php echo esc_html($infoText); ?></div>
<?php endif; ?>

<?php if (!$installed): ?>
  <div class="alert alert-warning">
    Le tabelle delle distinte non esistono su questo database: va applicata a mano la migrazione
    <code>sql/202610010001_sepa_distinte.sql</code>.
  </div>
<?php else: ?>

<!-- Dati ordinante -->
<div class="card mb-4">
  <div class="card-header" data-toggle="collapse" data-target="#debtorBox" style="cursor:pointer;">
    <i class="fas fa-university"></i> Dati ordinante (conto del Comitato)
    <?php if ($debtorProblems): ?>
      <span class="badge badge-danger ml-2">Da completare</span>
    <?php else: ?>
      <span class="ml-2 text-muted"><?php echo esc_html($debtor['name'] . ' - ' . SepaCbiExport::ibanMask($debtor['iban'])); ?></span>
    <?php endif; ?>
  </div>
  <div id="debtorBox" class="collapse <?php echo $debtorProblems ? 'show' : ''; ?>">
    <div class="card-body">
      <?php if ($debtorProblems): ?>
        <div class="alert alert-danger">
          Prima di generare una distinta completa questi dati:
          <ul class="mb-0"><?php foreach ($debtorProblems as $p): ?><li><?php echo esc_html($p); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_html($pageUrl); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_debtor">
        <div class="form-row">
          <div class="form-group col-md-5">
            <label for="debtor_name">Intestatario</label>
            <input type="text" class="form-control" id="debtor_name" name="debtor_name" maxlength="70" value="<?php echo esc_html($debtor['name']); ?>">
          </div>
          <div class="form-group col-md-4">
            <label for="debtor_iban">IBAN</label>
            <input type="text" class="form-control" id="debtor_iban" name="debtor_iban" maxlength="34" value="<?php echo esc_html($debtor['iban']); ?>">
          </div>
          <div class="form-group col-md-2">
            <label for="debtor_cuc">CUC</label>
            <input type="text" class="form-control" id="debtor_cuc" name="debtor_cuc" maxlength="8" value="<?php echo esc_html($debtor['cuc']); ?>">
          </div>
          <div class="form-group col-md-1">
            <label for="debtor_country">Paese</label>
            <input type="text" class="form-control" id="debtor_country" name="debtor_country" maxlength="2" value="<?php echo esc_html($debtor['country']); ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salva dati ordinante</button>
      </form>
    </div>
  </div>
</div>

<!-- Nuova distinta -->
<div class="card mb-4">
  <div class="card-header bg-primary text-white"><i class="fas fa-file-code"></i> Nuova distinta</div>
  <div class="card-body">
    <?php if (!$candidates['eligible']): ?>
      <p class="mb-0">Nessun rimborso con bonifico da pagare per il <?php echo $selectedYear; ?>.</p>
    <?php else: ?>
    <form method="post" action="<?php echo ROOT_URL; ?>api/admin/sepa-batch-download.php" id="sepaForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
      <input type="hidden" name="download_token" id="download_token" value="">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="execution_date">Data esecuzione</label>
          <input type="date" class="form-control" id="execution_date" name="execution_date"
                 min="<?php echo date('Y-m-d'); ?>" value="<?php echo $defaultExecDate; ?>" required>
        </div>
        <div class="form-group col-md-9">
          <label for="template">Causale <small class="text-muted">(segnaposto {anno} e {pratiche}; max 140 caratteri)</small></label>
          <input type="text" class="form-control" id="template" name="template" maxlength="140" value="<?php echo esc_html($debtor['template']); ?>">
        </div>
      </div>

      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th><input type="checkbox" id="checkAll" title="Seleziona tutti"></th>
            <th>Venditore</th><th>Pratiche</th><th>Beneficiario</th><th>IBAN</th>
            <th class="text-right">Importo</th><th>Stato</th><th>Causale</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($candidates['eligible'] as $c): ?>
          <tr>
            <td><input type="checkbox" class="refund-cb" name="refund_ids[]" value="<?php echo (int)$c->id; ?>"
                       data-amount="<?php echo number_format($c->due, 2, '.', ''); ?>"
                       <?php echo $c->last_batch_id === null ? 'checked' : ''; ?>></td>
            <td>
              <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo (int)$c->id; ?>">
                <?php echo esc_html($c->last_name . ' ' . $c->first_name); ?>
              </a>
              <?php if ($c->last_batch_id !== null): ?>
                <br><small class="text-muted">in distinta #<?php echo (int)$c->last_batch_id; ?>
                  <?php echo $c->last_batch_date ? 'del ' . date('d/m', strtotime($c->last_batch_date)) : ''; ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html($c->pratica_numbers); ?></td>
            <td><?php echo esc_html($c->beneficiary_name); ?></td>
            <td><code><?php echo esc_html($c->iban_masked); ?></code></td>
            <td class="text-right"><?php echo $euro($c->due); ?></td>
            <td><span class="badge <?php echo $statusBadge[$c->status] ?? 'badge-secondary'; ?>"><?php echo $statusText[$c->status] ?? esc_html($c->status); ?></span></td>
            <td class="remittance-preview" data-year="<?php echo $selectedYear; ?>" data-praticas="<?php echo esc_html($c->pratica_numbers); ?>"><small></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <p class="lead" id="sepaTotals"></p>
      <button type="submit" class="btn btn-success" id="sepaSubmit" <?php echo $debtorProblems ? 'disabled' : ''; ?>>
        <i class="fas fa-download"></i> Genera distinta XML
      </button>
      <?php if ($debtorProblems): ?><small class="text-danger ml-2">Completa prima i dati ordinante.</small><?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Esclusi -->
<?php if ($candidates['excluded']): ?>
<div class="card mb-4">
  <div class="card-header bg-warning"><i class="fas fa-exclamation-triangle"></i> Bonifici non includibili (<?php echo count($candidates['excluded']); ?>)</div>
  <div class="card-body">
    <table class="table table-sm">
      <thead><tr><th>Venditore</th><th>Pratiche</th><th>IBAN</th><th class="text-right">Importo</th><th>Motivo</th></tr></thead>
      <tbody>
      <?php foreach ($candidates['excluded'] as $c): ?>
        <tr>
          <td><a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo (int)$c->id; ?>"><?php echo esc_html($c->last_name . ' ' . $c->first_name); ?></a></td>
          <td><?php echo esc_html($c->pratica_numbers); ?></td>
          <td><code><?php echo esc_html($c->iban_masked); ?></code></td>
          <td class="text-right"><?php echo $euro($c->due); ?></td>
          <td><?php echo esc_html($c->reason); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Storico -->
<div class="card mb-4">
  <div class="card-header bg-dark text-white"><i class="fas fa-history"></i> Storico distinte <?php echo $selectedYear; ?></div>
  <div class="card-body">
    <?php if (!$batches): ?>
      <p class="mb-0">Nessuna distinta generata per il <?php echo $selectedYear; ?>.</p>
    <?php else: ?>
    <table class="table table-sm">
      <thead><tr><th>#</th><th>Generata</th><th>Esecuzione</th><th class="text-right">Bonifici</th><th class="text-right">Totale</th><th>Stato</th><th>Admin</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><a href="#" data-toggle="collapse" data-target="#items<?php echo (int)$b->id; ?>">#<?php echo (int)$b->id; ?></a></td>
          <td><?php echo date('d/m/Y H:i', strtotime($b->created_at)); ?></td>
          <td><?php echo date('d/m/Y', strtotime($b->execution_date)); ?></td>
          <td class="text-right"><?php echo (int)$b->tx_count; ?></td>
          <td class="text-right"><?php echo $euro($b->total_amount); ?></td>
          <td><span class="badge <?php echo $batchStatusBadge[$b->status]; ?>"><?php echo $batchStatusText[$b->status]; ?></span>
            <?php if ($b->status === 'paid' && $b->paid_at): ?><br><small><?php echo date('d/m/Y', strtotime($b->paid_at)); ?></small><?php endif; ?></td>
          <td><?php echo esc_html((string)$b->created_by_name); ?></td>
          <td class="text-nowrap">
            <?php if ($b->status === 'generated'): ?>
              <form method="post" action="<?php echo esc_html($pageUrl); ?>" class="form-inline d-inline"
                    onsubmit="return confirm('Registrare come pagati i bonifici della distinta #<?php echo (int)$b->id; ?>?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="batch_id" value="<?php echo (int)$b->id; ?>">
                <input type="date" name="payment_date" class="form-control form-control-sm mr-1" value="<?php echo esc_html($b->execution_date); ?>" required>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Segna come pagati</button>
              </form>
              <form method="post" action="<?php echo esc_html($pageUrl); ?>" class="d-inline"
                    onsubmit="return confirm('Scartare la distinta #<?php echo (int)$b->id; ?>? Usalo solo se il file non è stato caricato o è stato rifiutato.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="discard">
                <input type="hidden" name="batch_id" value="<?php echo (int)$b->id; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Scarta</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <tr class="collapse" id="items<?php echo (int)$b->id; ?>">
          <td colspan="8">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th>Venditore</th><th>Beneficiario</th><th>IBAN</th><th class="text-right">Importo</th><th>Causale</th><th>EndToEndId</th></tr></thead>
              <tbody>
              <?php foreach ($sepaMgr->getBatchItems($b->id) as $it): ?>
                <tr class="<?php echo $it->superseded ? 'text-muted' : ''; ?>">
                  <td><?php echo esc_html($it->last_name . ' ' . $it->first_name); ?>
                    <?php if ($it->superseded): ?><span class="badge badge-light">superata</span><?php endif; ?></td>
                  <td><?php echo esc_html($it->beneficiary_name); ?></td>
                  <td><code><?php echo esc_html($it->iban_masked); ?></code></td>
                  <td class="text-right"><?php echo $euro($it->amount); ?></td>
                  <td><small><?php echo esc_html($it->remittance); ?></small></td>
                  <td><small><?php echo esc_html($it->end_to_end_id); ?></small></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('sepaForm');
  if (!form) { return; }
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.refund-cb'));
  var totals = document.getElementById('sepaTotals');
  var submit = document.getElementById('sepaSubmit');
  var tpl = document.getElementById('template');
  var debtorBlocked = submit.disabled;

  function fmt(n) { return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function refresh() {
    var n = 0, sum = 0;
    boxes.forEach(function (b) { if (b.checked) { n++; sum += Math.round(parseFloat(b.dataset.amount) * 100); } });
    totals.textContent = n + ' bonifici selezionati – € ' + fmt(sum / 100);
    submit.disabled = debtorBlocked || n === 0;
  }

  // Anteprima indicativa (lato server si applicano traslitterazione e taglio a 140).
  function previews() {
    document.querySelectorAll('.remittance-preview').forEach(function (td) {
      td.firstElementChild.textContent = tpl.value.replace('{anno}', td.dataset.year).replace('{pratiche}', td.dataset.praticas).slice(0, 140);
    });
  }

  boxes.forEach(function (b) { b.addEventListener('change', refresh); });
  document.getElementById('checkAll').addEventListener('change', function () {
    var on = this.checked;
    boxes.forEach(function (b) { b.checked = on; });
    refresh();
  });
  tpl.addEventListener('input', previews);

  // Il download non ricarica la pagina: l'endpoint imposta il cookie sepa_dl
  // quando il file è pronto, e allora ricarichiamo per mostrare stati e storico.
  form.addEventListener('submit', function () {
    var token = String(Date.now());
    document.getElementById('download_token').value = token;
    submit.disabled = true;
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (document.cookie.indexOf('sepa_dl=' + token) !== -1) {
        clearInterval(timer);
        document.cookie = 'sepa_dl=; Max-Age=0; path=/';
        window.location.href = '<?php echo $pageUrl; ?>&msg=sepa_generated';
      } else if (tries > 60) {
        clearInterval(timer);
        submit.disabled = false;
      }
    }, 500);
  });

  refresh();
  previews();
})();
</script>

<?php endif; ?>
```

Nota: `$pageUrl` in JS è emesso **senza** `esc_html()` (trappola 4 di `context/06`): contiene solo `ROOT_URL` e un intero.

Nota (trappola 3 di `context/06`): il JS globale `postUnchecked()` aggiunge un input nascosto con valore `0` per ogni checkbox **non** spuntata, quindi `refund_ids[]` arriva anche con degli `0`. `createBatch()` li scarta (`array_filter(array_map('intval', …))`): non togliere quel filtro. La checkbox "Seleziona tutti" non ha `name`, quindi non viene inviata.

- [ ] **Step 3: Link dalla pagina rimborsi**

In `web/htdocs/admin/pages/seller-refunds.php`, subito dopo il blocco `<a ... seller-orders-report ...> ... Riepilogo Pratiche\n      </a>` (riga ~166) aggiungere:

```php

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-sepa&year=<?php echo $selectedYear; ?>" class="btn btn-outline-primary ml-2"
         title="Genera il file XML dei bonifici da caricare in banca">
        <i class="fas fa-university"></i> Distinte SEPA
      </a>
```

- [ ] **Step 4: Copiare in staging (tutti insieme)**

```bash
for f in api/admin/sepa-batch-download.php admin/pages/seller-refund-sepa.php admin/pages/seller-refunds.php; do cp web/htdocs/$f web/htdocs/staging/$f; done
```

- [ ] **Step 5: Verifica rapida su staging** (utente): la pagina si apre da "Distinte SEPA"; il box ordinante è aperto e rosso; salvare dati di prova (IBAN `IT60X0542811101000000123456`, CUC `ABC12345`) → messaggio "Dati ordinante salvati." e box chiuso. La verifica completa è nel Task 8.

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/api/admin/sepa-batch-download.php web/htdocs/admin/pages/seller-refund-sepa.php web/htdocs/admin/pages/seller-refunds.php \
        web/htdocs/staging/api/admin/sepa-batch-download.php web/htdocs/staging/admin/pages/seller-refund-sepa.php web/htdocs/staging/admin/pages/seller-refunds.php
git commit -m "Distinte SEPA: pagina di gestione e download dell'XML"
```

---

### Task 7: Stato `xmlsaved` nelle pagine rimborsi esistenti

**Files:**
- Modify: `web/htdocs/classes/SellerRefund.php` (`sellerFilterConditions()` riga ~137, `getYearSummary()` riga ~936)
- Modify: `web/htdocs/admin/pages/seller-refunds.php` (filtro ~137, badge ~437–447, card riepilogo ~316–360)
- Modify: `web/htdocs/admin/pages/seller-refund-view.php` (badge ~295–305, nuova card distinte)
- Modify: `web/htdocs/admin/pages/seller-refund-report.php` (filtro ~243 e mappe etichette se presenti)
- Copie in staging

**Interfaces:**
- Consumes: `SepaBatchManager::getBatchesForRefund()`, `isInstalled()`.
- Produces: `getYearSummary()->xmlsaved_count`.

- [ ] **Step 1: `SellerRefund.php`**

Riga ~137: `$allowedStatus = ['pending', 'partial', 'completed', 'cancelled'];` → `$allowedStatus = ['pending', 'partial', 'xmlsaved', 'completed', 'cancelled'];`

In `getYearSummary()`, dopo la riga `SUM(CASE WHEN sr.status = 'partial' THEN 1 ELSE 0 END) as partial_count,` aggiungere:
```sql
                SUM(CASE WHEN sr.status = 'xmlsaved' THEN 1 ELSE 0 END) as xmlsaved_count,
```

- [ ] **Step 2: Etichette e filtri**

Cercare tutte le mappe di stato: `grep -n "'partial' =>" web/htdocs/admin/pages/*.php` e in **ogni** mappa `$statusBadge` aggiungere `'xmlsaved' => 'badge-primary',` dopo la voce `partial`; in ogni mappa `$statusText` aggiungere `'xmlsaved' => 'Distinta generata',`.

Cercare tutti i filtri Stato: `grep -n 'value="partial"' web/htdocs/admin/pages/*.php` e dopo ciascuna riga aggiungere (adattando il nome della variabile del filtro usata nella riga, `$statusFilter` in entrambe le pagine note):
```php
          <option value="xmlsaved" <?php echo $statusFilter === 'xmlsaved' ? 'selected' : ''; ?>>Distinta generata</option>
```

- [ ] **Step 3: Card in `seller-refunds.php`**

Leggere le card riepilogo (righe ~310–360) e, dopo la card "Contanti da Rimborsare", aggiungere una card con la **stessa struttura di markup** delle altre (copiare la `div` della card precedente cambiando solo testo/icona/valore):
- titolo: `Distinte generate, da pagare`
- valore: `<?php echo (int)($summary->xmlsaved_count ?? 0); ?>`
- sotto: link `admin/?page=seller-refund-sepa&year=<?php echo $selectedYear; ?>` con testo `Vai alle distinte`.

- [ ] **Step 4: Sezione distinte in `seller-refund-view.php`**

Dopo `$payments = $sellerRefundMgr->getPaymentHistory($refundId);` aggiungere:
```php
  // Distinte SEPA in cui compare il rimborso (vuoto se la migrazione non c'è)
  $sepaMgr = new SepaBatchManager();
  $sepaBatches = $sepaMgr->isInstalled() ? $sepaMgr->getBatchesForRefund($refundId) : [];
```

Individuare la card dello storico pagamenti (cercare `getPaymentHistory` → la variabile `$payments` nel markup) e subito **dopo** la sua `</div>` di chiusura della card aggiungere:
```php
    <?php if ($sepaBatches): ?>
    <div class="card mb-4">
      <div class="card-header bg-primary text-white"><i class="fas fa-university"></i> Distinte SEPA</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <thead><tr><th>Distinta</th><th>Generata</th><th>Esecuzione</th><th class="text-right">Importo</th><th>Stato</th></tr></thead>
          <tbody>
          <?php foreach ($sepaBatches as $sb): ?>
            <tr class="<?php echo $sb->superseded ? 'text-muted' : ''; ?>">
              <td><a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-sepa&year=<?php echo (int)$refund->year; ?>">#<?php echo (int)$sb->id; ?></a>
                <?php if ($sb->superseded): ?><span class="badge badge-light">superata</span><?php endif; ?></td>
              <td><?php echo date('d/m/Y', strtotime($sb->created_at)); ?></td>
              <td><?php echo date('d/m/Y', strtotime($sb->execution_date)); ?></td>
              <td class="text-right">&euro; <?php echo number_format((float)$sb->amount, 2, ',', '.'); ?></td>
              <td><?php echo ['generated' => 'Generata', 'paid' => 'Pagata', 'discarded' => 'Scartata'][$sb->status] ?? esc_html($sb->status); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
```

- [ ] **Step 5: Copiare in staging, verificare** che `seller-refunds`, `seller-refund-view`, `seller-refund-report` si aprano senza errori e il filtro "Distinta generata" sia presente.

```bash
for f in classes/SellerRefund.php admin/pages/seller-refunds.php admin/pages/seller-refund-view.php admin/pages/seller-refund-report.php; do cp web/htdocs/$f web/htdocs/staging/$f; done
```

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/classes/SellerRefund.php web/htdocs/admin/pages/seller-refunds.php web/htdocs/admin/pages/seller-refund-view.php web/htdocs/admin/pages/seller-refund-report.php \
        web/htdocs/staging/classes/SellerRefund.php web/htdocs/staging/admin/pages/seller-refunds.php web/htdocs/staging/admin/pages/seller-refund-view.php web/htdocs/staging/admin/pages/seller-refund-report.php
git commit -m "Rimborsi: stato \"Distinta generata\" in filtri, badge, riepilogo e dettaglio"
```

---

### Task 8: Verifica end-to-end su staging + knowledge base

**Files:**
- Modify: `context/03-codebase-map.md`, `context/04-database.md`, `context/05-domain-workflows.md`, `context/06-conventions-and-gotchas.md`, `context/INDEX.md`

- [ ] **Step 1: Scenario su staging** (l'utente esegue, con 2–3 rimborsi bonifico di prova; annotare l'esito di ogni punto):
  1. Self-test `admin/?page=sepa-selftest`: TUTTI PASS.
  2. Distinte SEPA → selezionare 2 rimborsi → "Genera distinta XML": si scarica `distinta_sepa_<anno>_001.xml`, la pagina si ricarica con il messaggio verde, i 2 rimborsi sono "Distinta generata", lo storico mostra #1 "Generata" con totale corretto.
  3. Aprire l'XML: `NbOfTxs`, `CtrlSum`, CUC, IBAN, causali corrette; nessun carattere accentato.
  4. Rigenerare includendo uno dei due (ora non preselezionato) → #2; nello storico la riga di #1 per quel rimborso è "superata".
  5. "Segna come pagati" su #1 → 1 pagato, 1 saltato; il rimborso pagato è "Completato" e in `seller-refund-view` compare il pagamento con riferimento = MsgId e la card "Distinte SEPA".
  6. "Scarta" su #2 → il rimborso torna "In attesa".
  7. Rimborso con IBAN errato → compare negli esclusi con il motivo.
  8. `user_activity_log`: righe `admin_sepa_*` senza IBAN.
- [ ] **Step 2: Primo caricamento su UniCredit** — importare l'XML (anche di prova) **senza autorizzare** e controllare anteprima, oppure confrontare con un XML esportato da UniCredit. Se la banca rifiuta `ReqdExctnDt`, cambiare `SepaCbiExport::EXEC_DATE_NESTED` e ripetere il punto 3.
- [ ] **Step 3: Aggiornare la knowledge base**
  - `03-codebase-map.md`: righe per `SepaCbiExport.php`, `SepaBatch.php` (`SepaBatchManager`, scrive sulla propria connessione PDO in transazione), pagine `seller-refund-sepa`, `sepa-selftest`, endpoint `api/admin/sepa-batch-download.php`; `sqlIsRealSeller()` ora public.
  - `04-database.md`: `seller_refund.status` include `xmlsaved`; tabelle `sepa_batch`, `sepa_batch_item`; chiavi `sepa_*` in `site_settings`; migrazione `202610010001`.
  - `05-domain-workflows.md`: nuova sottosezione "Distinte SEPA" in C (ciclo stati, rigenerazione/superate, scarta, pagati, esclusi, causale, CUC obbligatorio, XML non salvato).
  - `06-conventions-and-gotchas.md`: (a) **ogni DBManager ha la sua connessione PDO** → una transazione che tocca più tabelle va fatta su una sola connessione, non chiamando metodi di scrittura di altri manager; (b) download da admin → endpoint standalone in `api/admin/` (il router admin emette HTML prima della pagina).
  - `INDEX.md`: snapshot 2026-10 con la feature e "migrazione `202610010001` da applicare a mano su ogni ambiente".
- [ ] **Step 4: Commit**

```bash
git add context/
git commit -m "Knowledge base: distinte SEPA CBI"
```
