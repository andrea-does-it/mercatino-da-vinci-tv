# Email Ordini (mail merge) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add to `admin/?page=site_utils` two tabs — "Email Ordini" (filter orders, select them, compose a mail-merge email, send one AJAX request per order with a progress bar) and "Template Email" (CRUD of reusable subject/body templates).

**Architecture:** Server-rendered PHP partials included as new tabs of the existing `site_utils.php` tabbed page. Data access via two new classes following the existing `DBManager` pattern. Sending goes through a new AJAX endpoint `api/admin/send-order-email.php` (one order per request, also serves the preview). Two new DB tables: `email_template` and `order_email_log`.

**Tech Stack:** Plain PHP (no framework), MySQL via the project's `DB`/`DBManager` classes, PHPMailer via the existing `send_mail()` helper, jQuery + Bootstrap 4 + DataTables (already loaded by the admin template), FontAwesome icons.

**Spec:** `docs/superpowers/specs/2026-07-06-order-email-merge-design.md`

## Global Constraints

- **Dual-tree:** every file change under `web/htdocs/<path>` must be mirrored to `web/htdocs/staging/<path>`. The three modified files (`admin/pages/site_utils.php`, `inc/include-classes.php`) were verified **identical** between trees at plan time, so mirroring = copy the finished file. All other files are new — copy them. If a `git diff --no-index` shows the staging file diverged in the meantime, hand-apply instead of copying.
- **Commit messages in Italian, NO mention of Claude/AI, no Co-Authored-By trailer.**
- **Migrations applied by hand per environment** — creating the `.sql` file does not create the tables anywhere.
- **CSRF:** `csrf_field()` + `CSRF::validateToken()` on every POST; the AJAX endpoint uses `CSRF::validateAjaxOrDie()` with a `csrf_token` POST field from `CSRF::getTokenForAjax()`.
- **Escaping:** every dynamic HTML output through `esc_html()`; ids cast to `(int)`; never HTML-escape URLs placed inside JS strings.
- **UI text in Italian, informal "tu".**
- **No test suite; `php` often not on PATH.** Each task ends with a `php -l` lint *attempt* (skip silently if php is unavailable) plus careful re-reading of the diff. Do not claim "tested".
- Admin access check for these features: `user_type` must be `admin` or `pwuser` (same as `site_utils.php`).

## Reference: existing code the tasks rely on

- `DB` class (`classes/DB.php`): `$db->prepare($sql, $params)` → array of assoc rows; `$db->execute($sql, $params)` → lastInsertId for INSERT; `$db->pdo` is the raw PDO.
- `DBManager` (`classes/DB.php:185`): subclasses set `$this->columns` + `$this->tableName`; inherits `get($id)`, `getAll()`, `create($obj)`, `update($obj,$id)`, `delete($id)`.
- `send_mail($to, $subject, $htmlBody, &$errorMsg = null, $debug = false)` (`inc/functions.php:74`) → bool.
- `esc_html($str)` = `htmlspecialchars($str)`.
- Schema: `orders(id, numPratica, user_id, status['inviata','accettata','chiusa','annullata','eliminato'], created_at, ...)`; `order_item(id, order_id, product_id, single_price, status['accettare','vendere','venduto','eliminato'])`; `product(id, name, autori, editore, ISBN, ...)`; `user(id, first_name, last_name, email, user_type)`.
- Admin page routing: `admin/index.php` includes `pages/<page>.php` from a whitelist. The new partials are **not** routed pages (they're `include`d by `site_utils.php`), so the whitelist is NOT modified. Each partial starts with the `if (! defined('ROOT_URL')) die;` guard.
- AJAX endpoint pattern: see `api/admin/delete.php` (`require_once '../../inc/init.php';` + auth check + `CSRF::validateAjaxOrDie()`).

---

### Task 1: Migrazione SQL — tabelle `email_template` e `order_email_log`

**Files:**
- Create: `web/htdocs/sql/202607070001_email_template_e_log.sql`
- Create: `web/htdocs/staging/sql/202607070001_email_template_e_log.sql` (identical copy)

**Interfaces:**
- Produces: tables `email_template(id, name, subject, body, created_at, updated_at)` and `order_email_log(id, order_id, template_id NULL, recipient_email, subject, sent_at, sent_by)` used by Tasks 2–5.

- [ ] **Step 1: Write the migration file**

Create `web/htdocs/sql/202607070001_email_template_e_log.sql`:

```sql
-- Email Ordini (site_utils): template riutilizzabili e log degli invii per ordine.
-- Applicare A MANO su ogni ambiente (staging e produzione).

CREATE TABLE email_template (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  template_id INT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  sent_by INT NOT NULL,
  KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Mirror to staging**

Copy the file byte-identical to `web/htdocs/staging/sql/202607070001_email_template_e_log.sql`.

- [ ] **Step 3: Verify**

Re-read both files; confirm they are identical and contain no environment-specific names. There is nothing runnable locally — the DDL will be validated when applied by hand on staging.

- [ ] **Step 4: Commit**

```bash
git add web/htdocs/sql/202607070001_email_template_e_log.sql web/htdocs/staging/sql/202607070001_email_template_e_log.sql
git commit -m "Email ordini: tabelle email_template e order_email_log"
```

---

### Task 2: Classi `EmailTemplateManager` e `OrderEmailManager`

**Files:**
- Create: `web/htdocs/classes/EmailTemplate.php`
- Create: `web/htdocs/classes/OrderEmail.php`
- Modify: `web/htdocs/inc/include-classes.php` (add two `require_once` lines at the end)
- Mirror all three to `web/htdocs/staging/...`

**Interfaces:**
- Consumes: tables from Task 1; `DB`/`DBManager`, `esc_html()`.
- Produces (used by Tasks 3–5):
  - `EmailTemplateManager::getAllOrdered(): object[]` (each: `id, name, subject, body, updated_at`)
  - `EmailTemplateManager::save(int $id, string $name, string $subject, string $body)` (`$id<=0` → insert)
  - `EmailTemplateManager::get($id)`, `::delete($id)` (inherited)
  - `OrderEmailManager::PLACEHOLDERS` — const array `'{segnaposto}' => 'descrizione'`
  - `OrderEmailManager::findIdsByStatusYear(string $status, int $year): int[]`
  - `OrderEmailManager::findIdsByBook(string $search): int[]`
  - `OrderEmailManager::findIdsByNumbers(string $numbersText): int[]`
  - `OrderEmailManager::findIdsBySql(string $sql, &$error): int[]`
  - `OrderEmailManager::getOrdersForList(int[] $ids): object[]` (each: `id, numPratica, status, created_at, first_name, last_name, email, num_libri, last_email_at, last_email_subject`)
  - `OrderEmailManager::getOrderForEmail(int $orderId): ?object` (order fields + seller fields + `books[]` each `name, single_price`)
  - `OrderEmailManager::mergeText(string $text, object $order): string`
  - `OrderEmailManager::buildHtmlBody(string $plainBody): string`
  - `OrderEmailManager::logSend(int $orderId, int $templateId, string $email, string $subject, int $sentBy): void`

- [ ] **Step 1: Create `web/htdocs/classes/EmailTemplate.php`**

```php
<?php
class EmailTemplate {

  public $id;
  public $name;
  public $subject;
  public $body;

  public function __construct($id, $name, $subject, $body) {
    $this->id = (int)$id;
    $this->name = $name;
    $this->subject = $subject;
    $this->body = $body;
  }
}

class EmailTemplateManager extends DBManager {

  public function __construct() {
    parent::__construct();
    $this->columns = array('id', 'name', 'subject', 'body');
    $this->tableName = 'email_template';
  }

  public function getAllOrdered() {
    $rows = $this->db->prepare("
      SELECT id, name, subject, body, updated_at
      FROM email_template
      ORDER BY name
    ");
    $templates = array();
    foreach ($rows as $row) {
      $templates[] = (object)$row;
    }
    return $templates;
  }

  public function save($id, $name, $subject, $body) {
    $data = array('name' => $name, 'subject' => $subject, 'body' => $body);
    if ((int)$id > 0) {
      return $this->update($data, (int)$id);
    }
    return $this->create($data);
  }
}
```

- [ ] **Step 2: Create `web/htdocs/classes/OrderEmail.php`**

```php
<?php
/**
 * Invio email mail-merge a liste di ordini (pratiche).
 * Usato dai tab "Email Ordini" / "Template Email" di admin/?page=site_utils
 * e dall'endpoint api/admin/send-order-email.php.
 */
class OrderEmailManager {

  const PLACEHOLDERS = array(
    '{nome}'         => 'Nome del venditore',
    '{cognome}'      => 'Cognome del venditore',
    '{email}'        => 'Email del venditore',
    '{num_pratica}'  => 'Numero pratica',
    '{stato}'        => 'Stato della pratica',
    '{data_pratica}' => 'Data di creazione della pratica (gg/mm/aaaa)',
    '{num_libri}'    => 'Numero di libri della pratica',
    '{elenco_libri}' => 'Elenco libri, una riga per libro: Titolo — € prezzo',
  );

  private $db;

  public function __construct() {
    $this->db = new DB();
  }

  // ── Ricerca ordini ─────────────────────────────────────────────────────

  public function findIdsByStatusYear($status, $year) {
    $sql = "SELECT o.id FROM orders o WHERE o.status <> 'eliminato'";
    $params = array();
    if ($status !== '') {
      $sql .= " AND o.status = ?";
      $params[] = $status;
    }
    if ((int)$year > 0) {
      $sql .= " AND YEAR(o.created_at) = ?";
      $params[] = (int)$year;
    }
    return $this->_idsFromRows($this->db->prepare($sql, $params));
  }

  public function findIdsByBook($search) {
    $like = '%' . $search . '%';
    $rows = $this->db->prepare("
      SELECT DISTINCT oi.order_id AS id
      FROM order_item oi
      INNER JOIN product p ON p.id = oi.product_id
      WHERE oi.status <> 'eliminato'
        AND (p.name LIKE ? OR p.ISBN LIKE ?)
    ", array($like, $like));
    return $this->_idsFromRows($rows);
  }

  public function findIdsByNumbers($numbersText) {
    // Numeri separati da virgola, punto e virgola, spazi o a-capo.
    // Ogni numero viene cercato prima come numPratica, poi come id ordine.
    $tokens = preg_split('/[\s,;]+/', $numbersText, -1, PREG_SPLIT_NO_EMPTY);
    $numbers = array();
    foreach ($tokens as $t) {
      if (ctype_digit($t)) {
        $numbers[] = (int)$t;
      }
    }
    if (empty($numbers)) {
      return array();
    }
    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $rows = $this->db->prepare("
      SELECT o.id FROM orders o
      WHERE o.numPratica IN ($placeholders) OR o.id IN ($placeholders)
    ", array_merge($numbers, $numbers));
    return $this->_idsFromRows($rows);
  }

  public function findIdsBySql($sqlQuery, &$error = null) {
    $firstWord = strtoupper(strtok(ltrim($sqlQuery), " \t\n\r"));
    if ($firstWord !== 'SELECT') {
      $error = 'Sono ammesse solo query SELECT.';
      return array();
    }
    try {
      $stmt = $this->db->pdo->query($sqlQuery);
      $ids = array();
      while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
        if (isset($row[0]) && is_numeric($row[0])) {
          $ids[] = (int)$row[0];
        }
      }
      return array_values(array_unique($ids));
    } catch (PDOException $e) {
      $error = $e->getMessage();
      return array();
    }
  }

  /** Righe per la tabella "Ordini trovati" (una per ordine). */
  public function getOrdersForList(array $orderIds) {
    if (empty($orderIds)) {
      return array();
    }
    $orderIds = array_map('intval', $orderIds);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $rows = $this->db->prepare("
      SELECT
        o.id, o.numPratica, o.status, o.created_at,
        u.first_name, u.last_name, u.email,
        (SELECT COUNT(*) FROM order_item oi
          WHERE oi.order_id = o.id AND oi.status <> 'eliminato') AS num_libri,
        (SELECT MAX(l.sent_at) FROM order_email_log l
          WHERE l.order_id = o.id) AS last_email_at,
        (SELECT l2.subject FROM order_email_log l2
          WHERE l2.order_id = o.id
          ORDER BY l2.sent_at DESC, l2.id DESC LIMIT 1) AS last_email_subject
      FROM orders o
      INNER JOIN user u ON u.id = o.user_id
      WHERE o.id IN ($placeholders)
      ORDER BY o.numPratica, o.id
    ", $orderIds);
    $orders = array();
    foreach ($rows as $row) {
      $orders[] = (object)$row;
    }
    return $orders;
  }

  // ── Merge e invio ──────────────────────────────────────────────────────

  /** Dati completi di un ordine per il merge; null se non trovato. */
  public function getOrderForEmail($orderId) {
    $rows = $this->db->prepare("
      SELECT o.id, o.numPratica, o.status, o.created_at,
             u.first_name, u.last_name, u.email
      FROM orders o
      INNER JOIN user u ON u.id = o.user_id
      WHERE o.id = ?
    ", array((int)$orderId));
    if (empty($rows)) {
      return null;
    }
    $order = (object)$rows[0];
    $order->books = array();
    $bookRows = $this->db->prepare("
      SELECT p.name, oi.single_price
      FROM order_item oi
      INNER JOIN product p ON p.id = oi.product_id
      WHERE oi.order_id = ? AND oi.status <> 'eliminato'
      ORDER BY p.name
    ", array((int)$orderId));
    foreach ($bookRows as $b) {
      $order->books[] = (object)$b;
    }
    return $order;
  }

  /** Sostituisce i segnaposto nel testo semplice (oggetto o corpo). */
  public function mergeText($text, $order) {
    $bookLines = array();
    foreach ($order->books as $b) {
      $bookLines[] = $b->name . ' — € ' . number_format((float)$b->single_price, 2, ',', '.');
    }
    $replacements = array(
      '{nome}'         => $order->first_name,
      '{cognome}'      => $order->last_name,
      '{email}'        => $order->email,
      '{num_pratica}'  => (int)$order->numPratica > 0 ? (string)$order->numPratica : '-',
      '{stato}'        => $order->status,
      '{data_pratica}' => date('d/m/Y', strtotime($order->created_at)),
      '{num_libri}'    => (string)count($order->books),
      '{elenco_libri}' => implode("\n", $bookLines),
    );
    return strtr($text, $replacements);
  }

  /**
   * Testo semplice (già merged) → HTML email.
   * Merge PRIMA, poi escape: i valori inseriti non possono iniettare HTML.
   * Stessa shell HTML della newsletter rimborsi venditori.
   */
  public function buildHtmlBody($plainBody) {
    $html = nl2br(esc_html($plainBody));
    return "<html><head><meta charset='UTF-8'></head>"
         . "<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>"
         . $html
         . "</body></html>";
  }

  public function logSend($orderId, $templateId, $recipientEmail, $subject, $sentBy) {
    $this->db->execute("
      INSERT INTO order_email_log (order_id, template_id, recipient_email, subject, sent_by)
      VALUES (?, ?, ?, ?, ?)
    ", array(
      (int)$orderId,
      (int)$templateId > 0 ? (int)$templateId : null,
      $recipientEmail,
      $subject,
      (int)$sentBy,
    ));
  }

  private function _idsFromRows($rows) {
    $ids = array();
    foreach ($rows as $row) {
      $ids[] = (int)$row['id'];
    }
    return $ids;
  }
}
```

- [ ] **Step 3: Register the classes in `web/htdocs/inc/include-classes.php`**

Append after the last `require_once` line (`classes/ActivityLog.php`):

```php
  require_once ROOT_PATH . 'classes/EmailTemplate.php';
  require_once ROOT_PATH . 'classes/OrderEmail.php';
```

- [ ] **Step 4: Lint (best-effort)**

Run: `php -l web/htdocs/classes/EmailTemplate.php && php -l web/htdocs/classes/OrderEmail.php`
Expected: `No syntax errors detected`. If `php` is not on PATH, re-read both files carefully instead (matching braces, semicolons, no PHP 7.4+ syntax like arrow functions — the code above is PHP 7.0-safe).

- [ ] **Step 5: Mirror to staging**

`web/htdocs/staging/inc/include-classes.php` was verified identical to the main tree: copy the modified `include-classes.php` over it, and copy the two new class files to `web/htdocs/staging/classes/`.

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/classes/EmailTemplate.php web/htdocs/classes/OrderEmail.php web/htdocs/inc/include-classes.php web/htdocs/staging/classes/EmailTemplate.php web/htdocs/staging/classes/OrderEmail.php web/htdocs/staging/inc/include-classes.php
git commit -m "Email ordini: classi EmailTemplateManager e OrderEmailManager"
```

---

### Task 3: Tab "Template Email" (CRUD template)

**Files:**
- Modify: `web/htdocs/admin/pages/site_utils.php` (tab whitelist ~line 17, POST→tab mapping, one nav item, one tab pane)
- Create: `web/htdocs/admin/pages/site_utils_email_templates.php`
- Mirror both to `web/htdocs/staging/admin/pages/`

**Interfaces:**
- Consumes: `EmailTemplateManager` (Task 2), `CSRF`, `esc_html()`, `OrderEmailManager::PLACEHOLDERS`.
- Produces: tab reachable at `admin/?page=site_utils&tab=email_templates`; templates data used by the "Email Ordini" tab (Task 5) via the shared table.

- [ ] **Step 1: Extend tab handling in `site_utils.php`**

Change the `$activeTab` block (currently lines 16–19) to:

```php
  // ── Determine active tab ────────────────────────────────────────────────────
  $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'email';
  if (!in_array($activeTab, ['email', 'sql', 'settings', 'email_orders', 'email_templates'], true)) {
    $activeTab = 'email';
  }
  // I POST dei tab inclusi non portano ?tab=: deducilo dal marker del form.
  if (isset($_POST['save_template']) || isset($_POST['delete_template'])) {
    $activeTab = 'email_templates';
  }
```

- [ ] **Step 2: Add the nav item in `site_utils.php`**

After the "Impostazioni" `</li>` (inside the same `<ul class="nav nav-tabs...">`):

```php
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'email_templates' ? 'active' : ''; ?>"
       data-toggle="tab" href="#tab-email-templates" role="tab">
      <i class="fas fa-file-alt mr-1"></i> Template Email
    </a>
  </li>
```

- [ ] **Step 3: Add the tab pane in `site_utils.php`**

Inside `<div class="tab-content mt-4">`, after the settings pane's closing `</div>`:

```php
  <!-- ══ TAB: TEMPLATE EMAIL ═════════════════════════════════════════════════ -->
  <div class="tab-pane fade <?php echo $activeTab === 'email_templates' ? 'show active' : ''; ?>" id="tab-email-templates" role="tabpanel">
    <?php include __DIR__ . '/site_utils_email_templates.php'; ?>
  </div>
```

- [ ] **Step 4: Create `web/htdocs/admin/pages/site_utils_email_templates.php`**

```php
<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Tab "Template Email" di site_utils: gestione dei template per "Email Ordini".

  $templateMgr = new EmailTemplateManager();

  $tplSaved   = false;
  $tplDeleted = false;
  $tplError   = '';

  if (isset($_POST['save_template'])) {
    if (!CSRF::validateToken()) {
      $tplError = 'Sessione scaduta: ricarica la pagina e riprova.';
    } else {
      $tplId      = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
      $tplName    = trim(isset($_POST['template_name']) ? $_POST['template_name'] : '');
      $tplSubject = trim(isset($_POST['template_subject']) ? $_POST['template_subject'] : '');
      $tplBody    = isset($_POST['template_body']) ? $_POST['template_body'] : '';
      if ($tplName === '' || $tplSubject === '' || trim($tplBody) === '') {
        $tplError = 'Nome, oggetto e corpo sono obbligatori.';
      } else {
        $templateMgr->save($tplId, $tplName, $tplSubject, $tplBody);
        $tplSaved = true;
      }
    }
  }

  if (isset($_POST['delete_template'])) {
    if (!CSRF::validateToken()) {
      $tplError = 'Sessione scaduta: ricarica la pagina e riprova.';
    } else {
      $templateMgr->delete((int)(isset($_POST['template_id']) ? $_POST['template_id'] : 0));
      $tplDeleted = true;
    }
  }

  // Template in modifica (?edit=<id>) — ignorato dopo un salvataggio riuscito.
  $editTpl = null;
  if (isset($_GET['edit']) && !$tplSaved) {
    $candidate = $templateMgr->get((int)$_GET['edit']);
    if (isset($candidate->id) && (int)$candidate->id > 0) {
      $editTpl = $candidate;
    }
  }

  $templates = $templateMgr->getAllOrdered();
  $tplTabUrl = ROOT_URL . 'admin/?page=site_utils&tab=email_templates';
?>

<p class="text-muted">
  Template riutilizzabili per il tab <strong>Email Ordini</strong>: oggetto e corpo
  supportano i segnaposto elencati sotto.
</p>

<?php if ($tplSaved): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> Template salvato.</div>
<?php endif; ?>
<?php if ($tplDeleted): ?>
  <div class="alert alert-info"><i class="fas fa-trash"></i> Template eliminato.</div>
<?php endif; ?>
<?php if ($tplError): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo esc_html($tplError); ?></div>
<?php endif; ?>

<div class="row">
  <!-- ── Elenco template ── -->
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-list mr-1"></i> Template esistenti (<?php echo count($templates); ?>)</div>
      <div class="card-body p-0">
        <?php if (empty($templates)): ?>
          <p class="text-muted p-3 mb-0">Nessun template: crea il primo con il modulo a fianco.</p>
        <?php else: ?>
          <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>Nome</th>
                <th>Oggetto</th>
                <th>Aggiornato</th>
                <th style="width: 90px;">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($templates as $tpl): ?>
                <tr>
                  <td class="align-middle"><strong><?php echo esc_html($tpl->name); ?></strong></td>
                  <td class="align-middle"><small><?php echo esc_html($tpl->subject); ?></small></td>
                  <td class="align-middle"><small class="text-muted"><?php echo date('d/m/Y', strtotime($tpl->updated_at)); ?></small></td>
                  <td class="align-middle">
                    <a href="<?php echo $tplTabUrl; ?>&edit=<?php echo (int)$tpl->id; ?>"
                       class="btn btn-sm btn-outline-primary" title="Modifica">
                      <i class="fas fa-pen"></i>
                    </a>
                    <?php /* Messaggio generico: il nome dentro la stringa JS romperebbe l'attributo se contiene apostrofi */ ?>
                    <form method="post" class="d-inline"
                          onsubmit="return confirm('Eliminare questo template?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="template_id" value="<?php echo (int)$tpl->id; ?>">
                      <button type="submit" name="delete_template" class="btn btn-sm btn-outline-danger" title="Elimina">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Crea / modifica ── -->
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header">
        <?php if ($editTpl): ?>
          <i class="fas fa-pen mr-1"></i> Modifica template: <?php echo esc_html($editTpl->name); ?>
        <?php else: ?>
          <i class="fas fa-plus mr-1"></i> Nuovo template
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="template_id" value="<?php echo $editTpl ? (int)$editTpl->id : 0; ?>">
          <div class="form-group">
            <label for="template_name">Nome <span class="text-danger">*</span></label>
            <input type="text" name="template_name" id="template_name" class="form-control" required
                   maxlength="100" value="<?php echo $editTpl ? esc_html($editTpl->name) : ''; ?>"
                   placeholder="Es. Sollecito consegna libri">
          </div>
          <div class="form-group">
            <label for="template_subject">Oggetto <span class="text-danger">*</span></label>
            <input type="text" name="template_subject" id="template_subject" class="form-control" required
                   maxlength="255" value="<?php echo $editTpl ? esc_html($editTpl->subject) : ''; ?>"
                   placeholder="Es. Mercatino: pratica {num_pratica} in attesa di consegna">
          </div>
          <div class="form-group">
            <label for="template_body">Corpo (testo semplice) <span class="text-danger">*</span></label>
            <textarea name="template_body" id="template_body" class="form-control" rows="10" required
                      placeholder="Ciao {nome},&#10;..."><?php echo $editTpl ? esc_html($editTpl->body) : ''; ?></textarea>
            <small class="form-text text-muted">Gli a-capo vengono convertiti automaticamente in HTML all'invio.</small>
          </div>
          <button type="submit" name="save_template" class="btn btn-primary">
            <i class="fas fa-save"></i> <?php echo $editTpl ? 'Salva modifiche' : 'Crea template'; ?>
          </button>
          <?php if ($editTpl): ?>
            <a href="<?php echo $tplTabUrl; ?>" class="btn btn-secondary">Annulla</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- ── Legenda segnaposto ── -->
    <div class="card">
      <div class="card-header bg-light"><i class="fas fa-tags mr-1"></i> Segnaposto disponibili</div>
      <div class="card-body py-2">
        <table class="table table-sm mb-0">
          <tbody>
            <?php foreach (OrderEmailManager::PLACEHOLDERS as $ph => $desc): ?>
              <tr>
                <td style="width: 140px;"><code><?php echo esc_html($ph); ?></code></td>
                <td><small><?php echo esc_html($desc); ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 5: Lint (best-effort)**

Run: `php -l web/htdocs/admin/pages/site_utils.php && php -l web/htdocs/admin/pages/site_utils_email_templates.php`
Expected: `No syntax errors detected` (or careful re-read if php unavailable).

- [ ] **Step 6: Mirror to staging**

`web/htdocs/staging/admin/pages/site_utils.php` was verified identical to the main tree at plan time: run `git diff --no-index web/htdocs/admin/pages/site_utils.php web/htdocs/staging/admin/pages/site_utils.php` — if the only differences are your Task 3 edits, copy the main file over the staging one. Copy the new partial to `web/htdocs/staging/admin/pages/site_utils_email_templates.php`.

- [ ] **Step 7: Commit**

```bash
git add web/htdocs/admin/pages/site_utils.php web/htdocs/admin/pages/site_utils_email_templates.php web/htdocs/staging/admin/pages/site_utils.php web/htdocs/staging/admin/pages/site_utils_email_templates.php
git commit -m "Utilità sito: tab Template Email con gestione template"
```

---

### Task 4: Endpoint AJAX `api/admin/send-order-email.php`

**Files:**
- Create: `web/htdocs/api/admin/send-order-email.php`
- Mirror to: `web/htdocs/staging/api/admin/send-order-email.php`

**Interfaces:**
- Consumes: `OrderEmailManager` (Task 2), `send_mail()`, `CSRF::validateAjaxOrDie()`, `$loggedInUser` from `inc/init.php`.
- Produces (consumed by Task 5's JS): POST endpoint, fields `order_id` (int), `subject` (string), `body` (string), `template_id` (int, 0 = nessun template), `csrf_token`, optional `preview=1`.
  - Send response: `{"ok": true}` or `{"ok": false, "error": "..."}`
  - Preview response: `{"ok": true, "to": "...", "subject": "...", "body": "..."}` (merged **plain text**, not HTML)

- [ ] **Step 1: Create `web/htdocs/api/admin/send-order-email.php`**

```php
<?php

// Invio (o anteprima) di una singola email mail-merge a un ordine.
// Chiamato in sequenza, un ordine per richiesta, dal tab "Email Ordini" di site_utils.

require_once '../../inc/init.php';

if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  header('Content-Type: application/json');
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Accesso negato']);
  exit;
}

CSRF::validateAjaxOrDie();

header('Content-Type: application/json');

$orderId    = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$subject    = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
$body       = isset($_POST['body']) ? $_POST['body'] : '';
$templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$isPreview  = !empty($_POST['preview']);

if ($orderId <= 0 || $subject === '' || trim($body) === '') {
  echo json_encode(['ok' => false, 'error' => 'Parametri mancanti: ordine, oggetto e corpo sono obbligatori.']);
  exit;
}

$orderEmailMgr = new OrderEmailManager();
$order = $orderEmailMgr->getOrderForEmail($orderId);

if (!$order) {
  echo json_encode(['ok' => false, 'error' => "Ordine $orderId non trovato."]);
  exit;
}

if (!filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'Email del venditore mancante o non valida.']);
  exit;
}

$mergedSubject = $orderEmailMgr->mergeText($subject, $order);
$mergedBody    = $orderEmailMgr->mergeText($body, $order);

if ($isPreview) {
  echo json_encode([
    'ok'      => true,
    'to'      => $order->email,
    'subject' => $mergedSubject,
    'body'    => $mergedBody,
  ]);
  exit;
}

$htmlBody  = $orderEmailMgr->buildHtmlBody($mergedBody);
$smtpError = '';
$sent = send_mail($order->email, $mergedSubject, $htmlBody, $smtpError);

if ($sent) {
  $orderEmailMgr->logSend($orderId, $templateId, $order->email, $mergedSubject, $loggedInUser->id);
  echo json_encode(['ok' => true]);
} else {
  echo json_encode(['ok' => false, 'error' => $smtpError !== '' && $smtpError !== null ? $smtpError : 'Invio fallito (errore SMTP).']);
}
```

- [ ] **Step 2: Lint (best-effort)**

Run: `php -l web/htdocs/api/admin/send-order-email.php`
Expected: `No syntax errors detected` (or careful re-read if php unavailable).

- [ ] **Step 3: Mirror to staging**

Copy the file to `web/htdocs/staging/api/admin/send-order-email.php`.

- [ ] **Step 4: Commit**

```bash
git add web/htdocs/api/admin/send-order-email.php web/htdocs/staging/api/admin/send-order-email.php
git commit -m "Email ordini: endpoint AJAX di invio e anteprima singolo ordine"
```

---

### Task 5: Tab "Email Ordini" (filtri, selezione, composizione, invio con progresso)

**Files:**
- Modify: `web/htdocs/admin/pages/site_utils.php` (one nav item, one tab pane — same spots as Task 3)
- Create: `web/htdocs/admin/pages/site_utils_email_orders.php`
- Mirror both to `web/htdocs/staging/admin/pages/`

**Interfaces:**
- Consumes: `OrderEmailManager` + `EmailTemplateManager` (Task 2), endpoint from Task 4 (`api/admin/send-order-email.php`, fields `order_id, subject, body, template_id, csrf_token[, preview]`; JSON `{ok, error|to/subject/body}`), `CSRF::getTokenForAjax()`.
- Produces: tab at `admin/?page=site_utils&tab=email_orders`.

- [ ] **Step 1: Add the nav item in `site_utils.php`**

Immediately BEFORE the "Template Email" nav item added in Task 3 (so the order is: Email di Test, Esecuzione SQL, Impostazioni, Email Ordini, Template Email):

```php
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'email_orders' ? 'active' : ''; ?>"
       data-toggle="tab" href="#tab-email-orders" role="tab">
      <i class="fas fa-mail-bulk mr-1"></i> Email Ordini
    </a>
  </li>
```

- [ ] **Step 2: Add the tab pane in `site_utils.php`**

Immediately BEFORE the "TAB: TEMPLATE EMAIL" pane added in Task 3:

```php
  <!-- ══ TAB: EMAIL ORDINI ═══════════════════════════════════════════════════ -->
  <div class="tab-pane fade <?php echo $activeTab === 'email_orders' ? 'show active' : ''; ?>" id="tab-email-orders" role="tabpanel">
    <?php include __DIR__ . '/site_utils_email_orders.php'; ?>
  </div>
```

- [ ] **Step 3: Create `web/htdocs/admin/pages/site_utils_email_orders.php`**

```php
<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Tab "Email Ordini" di site_utils: filtra ordini, seleziona, componi con
  // segnaposto e invia (una richiesta AJAX per ordine, con barra di progresso).

  $orderEmailMgr = new OrderEmailManager();
  $emailOrderTemplates = (new EmailTemplateManager())->getAllOrdered();

  // ── Filtri (GET, così la lista è ricaricabile/linkabile) ──
  $filterMode    = isset($_GET['filter_mode']) ? $_GET['filter_mode'] : '';
  $filterStatus  = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'inviata';
  $filterYear    = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : (int)date('Y');
  $filterBook    = isset($_GET['filter_book']) ? trim($_GET['filter_book']) : '';
  $filterSql     = isset($_GET['filter_sql']) ? trim($_GET['filter_sql']) : '';
  $filterNumbers = isset($_GET['filter_numbers']) ? trim($_GET['filter_numbers']) : '';

  $filterError = '';
  $emailOrders = null;   // null = nessuna ricerca ancora eseguita

  if ($filterMode !== '') {
    $ids = array();
    switch ($filterMode) {
      case 'status_year':
        $ids = $orderEmailMgr->findIdsByStatusYear($filterStatus, $filterYear);
        break;
      case 'book':
        if ($filterBook === '') {
          $filterError = 'Inserisci titolo o ISBN del libro.';
        } else {
          $ids = $orderEmailMgr->findIdsByBook($filterBook);
        }
        break;
      case 'sql':
        if ($filterSql === '') {
          $filterError = 'Inserisci una query SELECT che restituisca gli id ordine nella prima colonna.';
        } else {
          $ids = $orderEmailMgr->findIdsBySql($filterSql, $filterError);
        }
        break;
      case 'paste':
        if ($filterNumbers === '') {
          $filterError = 'Incolla almeno un numero di pratica o id ordine.';
        } else {
          $ids = $orderEmailMgr->findIdsByNumbers($filterNumbers);
        }
        break;
      default:
        $filterError = 'Modalità di filtro non valida.';
    }
    if ($filterError === '') {
      $emailOrders = $orderEmailMgr->getOrdersForList($ids);
    }
  }

  $orderStatuses = array('inviata', 'accettata', 'chiusa', 'annullata');
  $yearNow = (int)date('Y');

  // Template come JSON per il dropdown (i campi restano modificabili).
  $tplJson = array();
  foreach ($emailOrderTemplates as $t) {
    $tplJson[] = array(
      'id'      => (int)$t->id,
      'name'    => $t->name,
      'subject' => $t->subject,
      'body'    => $t->body,
    );
  }
?>

<p class="text-muted">
  Filtra gli ordini (pratiche), seleziona i destinatari, componi l'email con i segnaposto
  e invia: <strong>una email per ogni ordine selezionato</strong>, al venditore della pratica.
</p>

<!-- ── Card filtri ── -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter mr-1"></i> Filtro ordini</div>
  <div class="card-body">
    <?php if ($filterError): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo esc_html($filterError); ?></div>
    <?php endif; ?>
    <form method="get" action="<?php echo ROOT_URL; ?>admin/">
      <input type="hidden" name="page" value="site_utils">
      <input type="hidden" name="tab" value="email_orders">

      <div class="form-group">
        <label for="filter_mode">Modalit&agrave;</label>
        <select name="filter_mode" id="filter_mode" class="form-control" onchange="eoToggleFilterFields()">
          <option value="status_year" <?php echo $filterMode === 'status_year' || $filterMode === '' ? 'selected' : ''; ?>>Stato + anno</option>
          <option value="book" <?php echo $filterMode === 'book' ? 'selected' : ''; ?>>Ordini che contengono un libro</option>
          <option value="paste" <?php echo $filterMode === 'paste' ? 'selected' : ''; ?>>Elenco pratiche / id ordine</option>
          <option value="sql" <?php echo $filterMode === 'sql' ? 'selected' : ''; ?>>Query SQL personalizzata</option>
        </select>
      </div>

      <div id="eo-filter-status_year" class="form-row eo-filter-fields">
        <div class="form-group col-md-4">
          <label for="filter_status">Stato pratica</label>
          <select name="filter_status" id="filter_status" class="form-control">
            <option value="">Tutti (tranne eliminate)</option>
            <?php foreach ($orderStatuses as $st): ?>
              <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                <?php echo $st; ?><?php echo $st === 'inviata' ? ' (non ancora consegnata)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label for="filter_year">Anno</label>
          <select name="filter_year" id="filter_year" class="form-control">
            <option value="0" <?php echo $filterYear === 0 ? 'selected' : ''; ?>>Tutti</option>
            <?php for ($y = $yearNow; $y >= $yearNow - 5; $y--): ?>
              <option value="<?php echo $y; ?>" <?php echo $filterYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div id="eo-filter-book" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_book">Titolo o ISBN del libro</label>
        <input type="text" name="filter_book" id="filter_book" class="form-control"
               value="<?php echo esc_html($filterBook); ?>" placeholder="Es. Matematica.blu oppure 9788808...">
        <small class="form-text text-muted">Trova gli ordini con almeno un libro corrispondente (esclusi i libri eliminati dalla pratica).</small>
      </div>

      <div id="eo-filter-paste" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_numbers">Numeri pratica o id ordine</label>
        <textarea name="filter_numbers" id="filter_numbers" class="form-control" rows="3"
                  placeholder="Es. 101, 102, 103 (separati da virgola, spazio o a-capo)"><?php echo esc_html($filterNumbers); ?></textarea>
        <small class="form-text text-muted">Ogni numero viene cercato prima come numero pratica, poi come id ordine.</small>
      </div>

      <div id="eo-filter-sql" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_sql">Query SELECT</label>
        <textarea name="filter_sql" id="filter_sql" class="form-control" rows="4"
                  style="font-family: monospace; font-size: 0.9em;"
                  placeholder="SELECT o.id FROM orders o WHERE ..."><?php echo esc_html($filterSql); ?></textarea>
        <small class="form-text text-muted">Solo SELECT; la <strong>prima colonna</strong> deve contenere gli id della tabella <code>orders</code>. Gli id inesistenti vengono ignorati.</small>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Carica ordini</button>
    </form>
  </div>
</div>

<?php if ($emailOrders !== null && empty($emailOrders)): ?>
  <div class="alert alert-info"><i class="fas fa-info-circle"></i> Nessun ordine trovato con i filtri selezionati.</div>
<?php endif; ?>

<?php if (!empty($emailOrders)): ?>

<!-- ── Card ordini trovati ── -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-inbox mr-1"></i> Ordini trovati (<?php echo count($emailOrders); ?>)</span>
    <span class="text-muted" id="eoSelectedCount">0 selezionati</span>
  </div>
  <div class="card-body py-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectAll(true)">
      <i class="fas fa-check-square"></i> Seleziona tutti
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectNeverEmailed()">
      <i class="fas fa-envelope-open"></i> Seleziona mai contattati
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectAll(false)">
      <i class="fas fa-square"></i> Deseleziona
    </button>
  </div>
  <div class="card-body p-0" style="overflow-x: auto;">
    <table class="table table-hover table-sm mb-0" id="eoOrdersTable">
      <thead class="thead-light">
        <tr>
          <th style="width: 40px;"><input type="checkbox" id="eoSelectAllCheckbox" onchange="eoSelectAll(this.checked)"></th>
          <th>Pratica</th>
          <th>Venditore</th>
          <th>Email</th>
          <th>Stato</th>
          <th>Libri</th>
          <th>Ultima email</th>
          <th style="width: 60px;">Esito</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($emailOrders as $eo): ?>
          <tr id="eo-row-<?php echo (int)$eo->id; ?>">
            <td>
              <input type="checkbox" class="eo-order-checkbox" value="<?php echo (int)$eo->id; ?>"
                     data-emailed="<?php echo $eo->last_email_at ? '1' : '0'; ?>"
                     onchange="eoUpdateSelectedCount()">
            </td>
            <td><strong><?php echo (int)$eo->numPratica > 0 ? (int)$eo->numPratica : '-'; ?></strong></td>
            <td><?php echo esc_html($eo->last_name . ' ' . $eo->first_name); ?></td>
            <td><small><?php echo esc_html($eo->email); ?></small></td>
            <td><span class="badge badge-secondary"><?php echo esc_html($eo->status); ?></span></td>
            <td><?php echo (int)$eo->num_libri; ?></td>
            <td>
              <?php if ($eo->last_email_at): ?>
                <span class="badge badge-warning" title="Oggetto: <?php echo esc_html($eo->last_email_subject); ?>">
                  <i class="fas fa-envelope"></i> <?php echo date('d/m/Y H:i', strtotime($eo->last_email_at)); ?>
                </span>
              <?php else: ?>
                <small class="text-muted">mai</small>
              <?php endif; ?>
            </td>
            <td class="eo-result"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Card composizione ── -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-pen mr-1"></i> Componi email</div>
  <div class="card-body">
    <div class="form-group">
      <label for="eoTemplateSelect">Template</label>
      <div class="input-group">
        <select id="eoTemplateSelect" class="form-control" onchange="eoApplyTemplate()">
          <option value="0">— Nessun template (scrivi a mano) —</option>
          <?php foreach ($emailOrderTemplates as $t): ?>
            <option value="<?php echo (int)$t->id; ?>"><?php echo esc_html($t->name); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="input-group-append">
          <a href="<?php echo ROOT_URL; ?>admin/?page=site_utils&tab=email_templates" class="btn btn-outline-secondary">
            <i class="fas fa-cog"></i> Gestisci template
          </a>
        </div>
      </div>
      <small class="form-text text-muted">Selezionando un template, oggetto e corpo vengono precompilati ma restano modificabili.</small>
    </div>

    <div class="form-group">
      <label for="eoSubject">Oggetto <span class="text-danger">*</span></label>
      <input type="text" id="eoSubject" class="form-control" placeholder="Es. Mercatino: pratica {num_pratica}">
    </div>

    <div class="form-group">
      <label for="eoBody">Corpo (testo semplice) <span class="text-danger">*</span></label>
      <textarea id="eoBody" class="form-control" rows="10" placeholder="Ciao {nome},&#10;..."></textarea>
    </div>

    <details class="mb-3">
      <summary class="text-muted" style="cursor:pointer;"><i class="fas fa-tags"></i> Segnaposto disponibili</summary>
      <table class="table table-sm mt-2 mb-0">
        <tbody>
          <?php foreach (OrderEmailManager::PLACEHOLDERS as $ph => $desc): ?>
            <tr>
              <td style="width: 140px;"><code><?php echo esc_html($ph); ?></code></td>
              <td><small><?php echo esc_html($desc); ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>

    <button type="button" class="btn btn-outline-info" id="eoPreviewBtn" onclick="eoPreview()">
      <i class="fas fa-eye"></i> Anteprima (primo selezionato)
    </button>
    <button type="button" class="btn btn-primary" id="eoSendBtn" onclick="eoSendSelected()">
      <i class="fas fa-paper-plane"></i> Invia alle selezionate
    </button>

    <!-- Progresso invio -->
    <div id="eoProgressWrap" class="mt-3" style="display:none;">
      <div class="progress" style="height: 24px;">
        <div id="eoProgressBar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
      </div>
      <div class="mt-2" id="eoProgressText"></div>
      <div class="mt-2 text-danger" id="eoProgressErrors" style="white-space: pre-wrap; font-size: 0.85em;"></div>
    </div>
  </div>
</div>

<!-- ── Modal anteprima ── -->
<div class="modal fade" id="eoPreviewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Anteprima email</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>A:</strong> <span id="eoPreviewTo"></span></div>
        <div class="mb-2"><strong>Oggetto:</strong> <span id="eoPreviewSubject"></span></div>
        <hr>
        <div class="bg-light p-3" id="eoPreviewBody" style="white-space: pre-wrap; font-size: 0.9rem;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
// Dati per il tab Email Ordini (prefisso eo- per evitare collisioni con gli altri tab).
var eoTemplates = <?php echo json_encode($tplJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var eoCsrfToken = '<?php echo CSRF::getTokenForAjax(); ?>';
var eoEndpoint = '<?php echo ROOT_URL; ?>api/admin/send-order-email.php';
var eoSending = false;

function eoToggleFilterFields() {
  var mode = document.getElementById('filter_mode').value;
  document.querySelectorAll('.eo-filter-fields').forEach(function(el) {
    el.style.display = 'none';
  });
  var active = document.getElementById('eo-filter-' + mode);
  if (active) {
    active.style.display = '';
  }
}

function eoCheckboxes() {
  return Array.prototype.slice.call(document.querySelectorAll('.eo-order-checkbox'));
}

function eoSelectAll(checked) {
  eoCheckboxes().forEach(function(cb) { cb.checked = checked; });
  var master = document.getElementById('eoSelectAllCheckbox');
  if (master) master.checked = checked;
  eoUpdateSelectedCount();
}

function eoSelectNeverEmailed() {
  eoCheckboxes().forEach(function(cb) { cb.checked = cb.dataset.emailed === '0'; });
  eoUpdateSelectedCount();
}

function eoUpdateSelectedCount() {
  var count = eoCheckboxes().filter(function(cb) { return cb.checked; }).length;
  var el = document.getElementById('eoSelectedCount');
  if (el) el.textContent = count + ' selezionati';
}

function eoApplyTemplate() {
  var id = parseInt(document.getElementById('eoTemplateSelect').value, 10);
  if (!id) return;
  for (var i = 0; i < eoTemplates.length; i++) {
    if (eoTemplates[i].id === id) {
      document.getElementById('eoSubject').value = eoTemplates[i].subject;
      document.getElementById('eoBody').value = eoTemplates[i].body;
      return;
    }
  }
}

function eoComposeData(orderId, preview) {
  return {
    order_id: orderId,
    subject: document.getElementById('eoSubject').value,
    body: document.getElementById('eoBody').value,
    template_id: document.getElementById('eoTemplateSelect').value,
    csrf_token: eoCsrfToken,
    preview: preview ? 1 : 0
  };
}

function eoValidateCompose() {
  if (document.getElementById('eoSubject').value.trim() === '' ||
      document.getElementById('eoBody').value.trim() === '') {
    alert('Compila oggetto e corpo prima di continuare.');
    return false;
  }
  return true;
}

function eoSelectedIds() {
  return eoCheckboxes().filter(function(cb) { return cb.checked; })
                       .map(function(cb) { return parseInt(cb.value, 10); });
}

function eoPreview() {
  if (!eoValidateCompose()) return;
  var ids = eoSelectedIds();
  if (ids.length === 0) {
    alert('Seleziona almeno un ordine per vedere l\'anteprima.');
    return;
  }
  $.post(eoEndpoint, eoComposeData(ids[0], true))
    .done(function(resp) {
      if (resp && resp.ok) {
        $('#eoPreviewTo').text(resp.to);
        $('#eoPreviewSubject').text(resp.subject);
        $('#eoPreviewBody').text(resp.body);
        $('#eoPreviewModal').modal('show');
      } else {
        alert('Errore anteprima: ' + (resp && resp.error ? resp.error : 'risposta non valida'));
      }
    })
    .fail(function() {
      alert('Errore di rete durante l\'anteprima.');
    });
}

function eoSendSelected() {
  if (eoSending) return;
  if (!eoValidateCompose()) return;
  var ids = eoSelectedIds();
  if (ids.length === 0) {
    alert('Seleziona almeno un ordine.');
    return;
  }
  var alreadyEmailed = eoCheckboxes().filter(function(cb) {
    return cb.checked && cb.dataset.emailed === '1';
  }).length;
  var msg = 'Inviare ' + ids.length + ' email (una per ordine selezionato)?';
  if (alreadyEmailed > 0) {
    msg += '\n\nAttenzione: ' + alreadyEmailed + ' di questi ordini hanno già ricevuto un\'email.';
  }
  if (!confirm(msg)) return;

  eoSending = true;
  document.getElementById('eoSendBtn').disabled = true;
  document.getElementById('eoPreviewBtn').disabled = true;
  document.getElementById('eoProgressWrap').style.display = '';
  document.getElementById('eoProgressErrors').textContent = '';

  var sent = 0, failed = 0;
  var errors = [];

  function eoUpdateProgress() {
    var done = sent + failed;
    var pct = Math.round(done * 100 / ids.length);
    var bar = document.getElementById('eoProgressBar');
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
    bar.className = 'progress-bar' + (failed > 0 ? ' bg-warning' : ' bg-success');
    document.getElementById('eoProgressText').textContent =
      done + '/' + ids.length + ' elaborate — ' + sent + ' inviate, ' + failed + ' errori';
    document.getElementById('eoProgressErrors').textContent = errors.join('\n');
  }

  function eoMarkRow(orderId, ok, error) {
    var row = document.getElementById('eo-row-' + orderId);
    if (!row) return;
    var cell = row.querySelector('.eo-result');
    // Costruzione via DOM: il testo dell'errore (es. messaggio SMTP) non deve
    // essere concatenato come HTML.
    var badge = document.createElement('span');
    badge.className = ok ? 'badge badge-success' : 'badge badge-danger';
    if (!ok) badge.title = String(error || '');
    var icon = document.createElement('i');
    icon.className = ok ? 'fas fa-check' : 'fas fa-times';
    badge.appendChild(icon);
    cell.innerHTML = '';
    cell.appendChild(badge);
  }

  function eoSendNext(i) {
    if (i >= ids.length) {
      eoSending = false;
      document.getElementById('eoSendBtn').disabled = false;
      document.getElementById('eoPreviewBtn').disabled = false;
      return;
    }
    $.post(eoEndpoint, eoComposeData(ids[i], false))
      .done(function(resp) {
        if (resp && resp.ok) {
          sent++;
          eoMarkRow(ids[i], true);
        } else {
          failed++;
          var err = resp && resp.error ? resp.error : 'risposta non valida';
          errors.push('Ordine ' + ids[i] + ': ' + err);
          eoMarkRow(ids[i], false, err);
        }
      })
      .fail(function() {
        failed++;
        errors.push('Ordine ' + ids[i] + ': errore di rete');
        eoMarkRow(ids[i], false, 'errore di rete');
      })
      .always(function() {
        eoUpdateProgress();
        eoSendNext(i + 1);
      });
  }

  eoUpdateProgress();
  eoSendNext(0);
}

// Mostra i campi filtro corretti al caricamento (anche dopo un submit).
eoToggleFilterFields();

// DataTable per ricerca/ordinamento se la lista è lunga. paging:false è
// necessario: con la paginazione le righe non visibili escono dal DOM e le
// checkbox selezionate andrebbero perse dal loop di invio.
$(document).ready(function() {
  if ($('#eoOrdersTable tbody tr').length > 10) {
    $('#eoOrdersTable').DataTable({
      paging: false,
      order: [[1, 'asc']],
      columnDefs: [
        { orderable: false, targets: [0, 7] }
      ],
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Italian.json'
      }
    });
  }
});
</script>
```

- [ ] **Step 4: Lint (best-effort)**

Run: `php -l web/htdocs/admin/pages/site_utils.php && php -l web/htdocs/admin/pages/site_utils_email_orders.php`
Expected: `No syntax errors detected` (or careful re-read if php unavailable).

- [ ] **Step 5: Mirror to staging**

Diff `web/htdocs/admin/pages/site_utils.php` against the staging copy: if the only differences are the Task 3+5 edits, copy the main file over. Copy the new partial to `web/htdocs/staging/admin/pages/site_utils_email_orders.php`.

- [ ] **Step 6: Commit**

```bash
git add web/htdocs/admin/pages/site_utils.php web/htdocs/admin/pages/site_utils_email_orders.php web/htdocs/staging/admin/pages/site_utils.php web/htdocs/staging/admin/pages/site_utils_email_orders.php
git commit -m "Utilità sito: tab Email Ordini con filtri, mail merge e invio con progresso"
```

---

### Task 6: Aggiornamento knowledge base (`context/`)

**Files:**
- Modify: `context/03-codebase-map.md` (classes table + admin pages + API endpoints)
- Modify: `context/04-database.md` (new tables + migration status note)
- Modify: `context/05-domain-workflows.md` (new workflow section)
- Modify: `context/INDEX.md` (status snapshot)

**Interfaces:**
- Consumes: everything built in Tasks 1–5.
- Produces: docs only. NOT mirrored to `web/htdocs/` (docs live only at repo root).

- [ ] **Step 1: `context/03-codebase-map.md`**

In the classes table, extend the row that lists `Email.php` (or add a new row next to it):

```markdown
| `EmailTemplate.php`, `OrderEmail.php` | `EmailTemplateManager` (CRUD tabella `email_template`); `OrderEmailManager` (ricerca ordini per filtri, merge segnaposto, log invii in `order_email_log`). Usati dai tab "Email Ordini"/"Template Email" di site_utils. |
```

Where admin pages are listed, mention that `site_utils.php` has five tabs (Email di Test, Esecuzione SQL, Impostazioni, Email Ordini, Template Email) and that the last two are partials `site_utils_email_orders.php` / `site_utils_email_templates.php` included by `site_utils.php` (not routed pages). Where API endpoints are listed, add `api/admin/send-order-email.php` (POST, CSRF ajax; send/preview one mail-merge email per order; logs to `order_email_log`).

- [ ] **Step 2: `context/04-database.md`**

Add after the `site_settings` (or nearest fitting) section:

```markdown
### `email_template` and `order_email_log` (Email Ordini)
- `email_template`: `id`, `name`, `subject`, `body` (testo semplice con segnaposto
  `{nome}`, `{num_pratica}`, `{elenco_libri}`, ...), timestamps. Gestita dal tab
  "Template Email" di site_utils.
- `order_email_log`: `id`, `order_id`, `template_id` (NULL se testo ad-hoc o template
  eliminato — nessuna FK), `recipient_email`, `subject` (copia del merged), `sent_at`,
  `sent_by` (admin). Alimenta l'avviso "già inviata" del tab "Email Ordini".
- Migrazione: `202607070001_email_template_e_log.sql`.
```

- [ ] **Step 3: `context/05-domain-workflows.md`**

Add a new section (near the other email-related workflows):

```markdown
## Email massive agli ordini (mail merge) — site_utils
Da `admin/?page=site_utils&tab=email_orders` l'admin filtra gli ordini (stato+anno,
libro contenuto, elenco pratiche incollato, o SELECT libera che restituisce id ordine),
seleziona le righe (una email per ordine, al venditore), sceglie un template dal tab
"Template Email" o scrive oggetto/corpo a mano con segnaposto, vede l'anteprima e invia.
L'invio è sequenziale via AJAX (`api/admin/send-order-email.php`, una richiesta per
ordine) con barra di progresso; ogni invio riuscito è registrato in `order_email_log`
e la lista mostra un badge "già inviata" (avviso, non bloccante). Il merge avviene sul
testo semplice PRIMA dell'escape (`nl2br(esc_html())` + shell HTML come la newsletter
rimborsi), quindi i dati non possono iniettare HTML.
```

- [ ] **Step 4: `context/INDEX.md`**

In the "Status snapshot" section, append this sentence to the existing paragraph (adjust the date heading to the current month if needed):

```markdown
site_utils ha i tab "Email Ordini" (mail merge verso i venditori delle pratiche
filtrate, log in `order_email_log`) e "Template Email"; la migrazione
`202607070001` esiste nel repo ma va applicata a mano su ogni ambiente.
```

- [ ] **Step 5: Commit**

```bash
git add context/03-codebase-map.md context/04-database.md context/05-domain-workflows.md context/INDEX.md
git commit -m "Docs: knowledge base aggiornata con Email Ordini e template"
```

---

### Task 7: Verifica finale manuale (staging)

**Files:** none (verification only).

- [ ] **Step 1: Re-read the full diff**

Run: `git log --oneline -7` and `git diff HEAD~6 --stat` — confirm every main-tree file has its staging mirror in the same commits, and no unrelated files slipped in.

- [ ] **Step 2: Checklist for the user's manual test on staging** (report this list, do not claim it was executed)

1. Apply `202607070001_email_template_e_log.sql` by hand on the staging DB.
2. Open `admin/?page=site_utils&tab=email_templates`: create a template with placeholders, edit it, delete a throwaway one.
3. Open `admin/?page=site_utils&tab=email_orders`: try all four filter modes (status+year with `inviata`; a book title; pasted pratica numbers; a `SELECT o.id FROM orders o LIMIT 3`).
4. Select 1–2 orders with your own email as seller, pick the template, check the preview modal (placeholders resolved, book list correct).
5. Send; verify progress bar, ✓ badges, received email rendering, and that "Ultima email" appears after reloading the list.
6. Verify a CSRF failure path: reload after session expiry → error alert, no white page.
7. Only after staging is verified, apply the migration and deploy to production.
