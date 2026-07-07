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
