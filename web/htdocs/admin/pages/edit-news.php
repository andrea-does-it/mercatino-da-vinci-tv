<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;

if (!$loggedInUser || $loggedInUser->user_type != 'admin') {
  echo "<script>location.href='".ROOT_URL."auth?page=login&msg=forbidden';</script>";
  exit;
}

$newsMgr     = new NewsManager();
$downloadMgr = new DownloadManager();
$db          = new DB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=not_found';</script>";
  exit;
}

$news = $newsMgr->get($id);
if (!isset($news->id)) {
  echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=not_found';</script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!csrf_validate()) {
    echo "<script>location.href='".ROOT_URL."admin/?page=edit-news&id=".$id."&msg=csrf_error';</script>";
    exit;
  }

  // ── UPDATE NEWS ───────────────────────────────────────────────────────────
  if (isset($_POST['update_news'])) {
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title && $content) {
      $news->title      = $title;
      $news->content    = $content;
      $news->updated_at = date('Y-m-d H:i:s');
      $newsMgr->update($news, $id);

      // Upload new attachment file
      if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0 && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $dlId = $downloadMgr->uploadFile(
          $_FILES['attachment'],
          $loggedInUser->id,
          $_FILES['attachment']['name'],
          'Allegato news: ' . $title
        );
        if ($dlId) {
          $db->execute("INSERT INTO news_downloads (news_id, download_id) VALUES (?, ?)", [(int)$id, (int)$dlId]);
        }
      }

      // Link existing download
      if (!empty($_POST['link_download_id'])) {
        $linkId   = (int)$_POST['link_download_id'];
        $existing = $db->prepare(
          "SELECT 1 FROM news_downloads WHERE news_id = ? AND download_id = ?",
          [(int)$id, $linkId]
        );
        if (empty($existing)) {
          $db->execute("INSERT INTO news_downloads (news_id, download_id) VALUES (?, ?)", [(int)$id, $linkId]);
        }
      }

      echo "<script>location.href='".ROOT_URL."admin/?page=edit-news&id=".$id."&msg=updated';</script>";
    } else {
      echo "<script>location.href='".ROOT_URL."admin/?page=edit-news&id=".$id."&msg=mandatory_fields';</script>";
    }
    exit;
  }

  // ── REMOVE ATTACHMENT LINK ────────────────────────────────────────────────
  if (isset($_POST['delete_attachment'])) {
    $attachmentId = (int)($_POST['attachment_id'] ?? 0);
    if ($attachmentId > 0) {
      $db->execute(
        "DELETE FROM news_downloads WHERE news_id = ? AND download_id = ?",
        [(int)$id, $attachmentId]
      );
    }
    echo "<script>location.href='".ROOT_URL."admin/?page=edit-news&id=".$id."&msg=attachment_deleted';</script>";
    exit;
  }
}

// Get current attachments (prepared statement)
$attachments = $db->prepare(
  "SELECT d.* FROM downloads d
   JOIN news_downloads nd ON d.id = nd.download_id
   WHERE nd.news_id = ?
   ORDER BY d.created_at DESC",
  [(int)$id]
);

// Downloads NOT yet linked to this news (for "link existing" select)
$linkedIds          = !empty($attachments) ? array_column($attachments, 'id') : [];
if (!empty($linkedIds)) {
  $placeholders       = implode(',', array_fill(0, count($linkedIds), '?'));
  $availableDownloads = $db->prepare(
    "SELECT id, title, filename FROM downloads WHERE id NOT IN ($placeholders) ORDER BY title",
    $linkedIds
  );
} else {
  $availableDownloads = $db->prepare("SELECT id, title, filename FROM downloads ORDER BY title");
}
?>

<h1>Modifica News</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=news-management" class="back underline d-block mb-4">
  &laquo; Torna a Gestione News
</a>

<!-- ── EDIT FORM (no nested forms) ───────────────────────────────────────── -->
<div class="card mb-4">
  <div class="card-header"><strong>Modifica News</strong></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <?php csrf_field(); ?>

      <div class="form-group">
        <label for="title">Titolo <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="title" name="title"
               value="<?php echo htmlspecialchars($news->title); ?>" required>
      </div>

      <div class="form-group">
        <label for="content">Contenuto <span class="text-danger">*</span></label>
        <textarea id="content" name="content" class="form-control"><?php echo htmlspecialchars($news->content); ?></textarea>
      </div>

      <!-- Upload new attachment -->
      <div class="form-group">
        <label for="attachment">Carica nuovo allegato (Facoltativo)</label>
        <input type="file" class="form-control-file" id="attachment" name="attachment"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif">
        <small class="form-text text-muted">Formati accettati: PDF, Word, Excel, PowerPoint, ZIP, immagini</small>
      </div>

      <!-- Link existing download -->
      <?php if (!empty($availableDownloads)) : ?>
      <div class="form-group">
        <label for="link_download_id">Collega file esistente dall'archivio (Facoltativo)</label>
        <select class="form-control" id="link_download_id" name="link_download_id">
          <option value="">-- Nessuno --</option>
          <?php foreach ($availableDownloads as $avDl) : ?>
          <option value="<?php echo $avDl['id']; ?>">
            <?php echo esc_html($avDl['title']); ?> — <?php echo esc_html($avDl['filename']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <button type="submit" name="update_news" class="btn btn-primary">
        <i class="fas fa-save"></i> Aggiorna News
      </button>
    </form>
  </div>
</div>

<!-- ── CURRENT ATTACHMENTS (separate card — no nesting inside the edit form) ── -->
<?php if (is_array($attachments) && count($attachments) > 0) : ?>
<div class="card mb-4">
  <div class="card-header"><strong><i class="fas fa-paperclip mr-1"></i>Allegati collegati</strong></div>
  <div class="card-body p-0">
    <ul class="list-group list-group-flush">
      <?php foreach ($attachments as $att) : ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <span>
          <i class="fas fa-file mr-2 text-secondary"></i>
          <?php echo esc_html($att['title']); ?>
          <small class="text-muted ml-1">(<?php echo esc_html($att['filename']); ?>)</small>
        </span>
        <div>
          <a href="<?php echo ROOT_URL . $att['filepath']; ?>"
             class="btn btn-sm btn-outline-primary" target="_blank" title="Visualizza">
            <i class="fas fa-eye"></i>
          </a>
          <form method="post" class="d-inline"
                onsubmit="return confirm('Rimuovere il collegamento con questo allegato?')">
            <?php csrf_field(); ?>
            <input type="hidden" name="attachment_id" value="<?php echo $att['id']; ?>">
            <button type="submit" name="delete_attachment" class="btn btn-sm btn-outline-warning"
                    title="Rimuovi collegamento">
              <i class="fas fa-unlink"></i> Scollega
            </button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-muted small px-3 pt-2 pb-1">"Scollega" rimuove il collegamento; il file resta nell'archivio Download.</p>
  </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function () {
  $('#content').summernote({
    height: 300,
    toolbar: [
      ['style',  ['bold', 'italic', 'underline', 'clear']],
      ['font',   ['strikethrough', 'superscript', 'subscript']],
      ['para',   ['ul', 'ol', 'paragraph']],
      ['table',  ['table']],
      ['insert', ['link', 'hr']],
      ['view',   ['fullscreen', 'codeview']]
    ]
  });
});
</script>
