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

// ── POST ACTIONS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!csrf_validate()) {
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=csrf_error&tab=news';</script>";
    exit;
  }

  // ── ADD NEWS ──────────────────────────────────────────────────────────────
  if (isset($_POST['add_news'])) {
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title && $content) {
      $news   = new News(0, $loggedInUser->id, $title, $content);
      $newsId = $newsMgr->create($news);

      // Multiple file attachments
      if (isset($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        foreach ($_FILES['attachments']['name'] as $i => $name) {
          if ($_FILES['attachments']['size'][$i] > 0 && $_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
            $singleFile = [
              'name'     => $name,
              'type'     => $_FILES['attachments']['type'][$i],
              'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
              'error'    => $_FILES['attachments']['error'][$i],
              'size'     => $_FILES['attachments']['size'][$i],
            ];
            $dlId = $downloadMgr->uploadFile($singleFile, $loggedInUser->id, $name, 'Allegato news: ' . $title);
            if ($dlId && $newsId) {
              $db->execute("INSERT INTO news_downloads (news_id, download_id) VALUES (?, ?)", [(int)$newsId, (int)$dlId]);
            }
          }
        }
      }

      echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=created&tab=news';</script>";
    } else {
      echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=mandatory_fields&tab=news';</script>";
    }
    exit;
  }

  // ── DELETE NEWS ───────────────────────────────────────────────────────────
  if (isset($_POST['delete_news'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $db->execute("DELETE FROM news_downloads WHERE news_id = ?", [$id]);
      $newsMgr->delete($id);
    }
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=deleted&tab=news';</script>";
    exit;
  }

  // ── TOGGLE PUBLISH ────────────────────────────────────────────────────────
  if (isset($_POST['toggle_publish'])) {
    $id    = (int)($_POST['id']            ?? 0);
    $state = (int)($_POST['current_state'] ?? 0);
    if ($id > 0) $newsMgr->togglePublish($id);
    $msg = $state ? 'unpublished' : 'published';
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=".$msg."&tab=news';</script>";
    exit;
  }

  // ── TOGGLE PIN ────────────────────────────────────────────────────────────
  if (isset($_POST['toggle_pin'])) {
    $id    = (int)($_POST['id']            ?? 0);
    $state = (int)($_POST['current_state'] ?? 0);
    if ($id > 0) $newsMgr->togglePin($id);
    $msg = $state ? 'unpinned' : 'pinned';
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=".$msg."&tab=news';</script>";
    exit;
  }

  // ── ADD DOWNLOAD ──────────────────────────────────────────────────────────
  if (isset($_POST['add_download'])) {
    $title       = trim($_POST['dl_title']       ?? '');
    $description = trim($_POST['dl_description'] ?? '');

    if ($title && isset($_FILES['file']) && $_FILES['file']['size'] > 0 && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
      $dlId = $downloadMgr->uploadFile($_FILES['file'], $loggedInUser->id, $title, $description);
      $msg  = $dlId ? 'created' : 'err';
    } else {
      $msg = 'mandatory_fields';
    }
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=".$msg."&tab=downloads';</script>";
    exit;
  }

  // ── EDIT DOWNLOAD ─────────────────────────────────────────────────────────
  if (isset($_POST['edit_download'])) {
    $id          = (int)($_POST['id']            ?? 0);
    $title       = trim($_POST['dl_title']       ?? '');
    $description = trim($_POST['dl_description'] ?? '');

    if ($id > 0 && $title) {
      $downloadMgr->updateInfo($id, $title, $description);
      $msg = 'updated';
    } else {
      $msg = 'mandatory_fields';
    }
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=".$msg."&tab=downloads';</script>";
    exit;
  }

  // ── DELETE DOWNLOAD ───────────────────────────────────────────────────────
  if (isset($_POST['delete_download'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $download = $downloadMgr->get($id);
      if ($download && isset($download->filepath)) {
        $filePath = ROOT_PATH . $download->filepath;
        if (file_exists($filePath)) {
          unlink($filePath);
        }
        $db->execute("DELETE FROM news_downloads WHERE download_id = ?", [$id]);
        $downloadMgr->delete($id);
      }
    }
    echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=deleted&tab=downloads';</script>";
    exit;
  }
}

// ── ACTIVE TAB & DATA ─────────────────────────────────────────────────────────
$activeTab    = (isset($_GET['tab']) && $_GET['tab'] === 'downloads') ? 'downloads' : 'news';
$allNews      = $newsMgr->getAdminAll();
$allDownloads = $downloadMgr->getAllAdmin();

// ── HELPERS ───────────────────────────────────────────────────────────────────
function newsSnippet($html, $maxLen = 120) {
  $text = strip_tags($html);
  return mb_strlen($text) > $maxLen
    ? esc_html(mb_substr($text, 0, $maxLen)) . '…'
    : esc_html($text);
}

function fileTypeIcon($filename) {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  $map = [
    'pdf'  => 'fa-file-pdf text-danger',
    'doc'  => 'fa-file-word text-primary',
    'docx' => 'fa-file-word text-primary',
    'xls'  => 'fa-file-excel text-success',
    'xlsx' => 'fa-file-excel text-success',
    'ppt'  => 'fa-file-powerpoint text-danger',
    'pptx' => 'fa-file-powerpoint text-danger',
    'zip'  => 'fa-file-archive text-warning',
    'rar'  => 'fa-file-archive text-warning',
    '7z'   => 'fa-file-archive text-warning',
    'jpg'  => 'fa-file-image text-info',
    'jpeg' => 'fa-file-image text-info',
    'png'  => 'fa-file-image text-info',
    'gif'  => 'fa-file-image text-info',
  ];
  return 'fas ' . ($map[$ext] ?? 'fa-file text-secondary');
}
?>

<h1>Gestione News e Download</h1>

<!-- Tab navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'news' ? 'active' : ''; ?>"
       href="<?php echo ROOT_URL; ?>admin/?page=news-management&tab=news">
      <i class="fas fa-newspaper"></i> News
      <?php if (!empty($allNews)) : ?>
        <span class="badge badge-secondary ml-1"><?php echo count($allNews); ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'downloads' ? 'active' : ''; ?>"
       href="<?php echo ROOT_URL; ?>admin/?page=news-management&tab=downloads">
      <i class="fas fa-file-download"></i> Allegati e Download
      <?php if (!empty($allDownloads)) : ?>
        <span class="badge badge-secondary ml-1"><?php echo count($allDownloads); ?></span>
      <?php endif; ?>
    </a>
  </li>
</ul>

<!-- ═══════════════════════ NEWS TAB ═══════════════════════ -->
<div class="<?php echo $activeTab === 'news' ? '' : 'd-none'; ?>" id="tab-news">

  <!-- Add news form -->
  <div class="card mb-4">
    <div class="card-header"><strong><i class="fas fa-plus mr-1"></i>Aggiungi News</strong></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="form-group">
          <label for="title">Titolo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="form-group">
          <label for="content">Contenuto <span class="text-danger">*</span></label>
          <textarea id="content" name="content" class="form-control"></textarea>
        </div>
        <div class="form-group">
          <label for="attachments">Allegati (Facoltativo — selezione multipla)</label>
          <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif">
          <small class="form-text text-muted">Formati accettati: PDF, Word, Excel, PowerPoint, ZIP, immagini</small>
        </div>
        <button type="submit" name="add_news" class="btn btn-primary">
          <i class="fas fa-plus"></i> Aggiungi News
        </button>
      </form>
    </div>
  </div>

  <!-- News list -->
  <h4>Elenco News</h4>

  <?php if (empty($allNews)) : ?>
    <p class="text-muted">Nessuna news presente.</p>
  <?php else : ?>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="thead-light">
        <tr>
          <th style="width:42%">Titolo / Anteprima</th>
          <th>Data</th>
          <th>Autore</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allNews as $item) : ?>
        <tr class="<?php echo !empty($item['is_pinned']) ? 'table-warning' : ''; ?>">
          <td>
            <?php if (!empty($item['is_pinned'])) : ?>
              <i class="fas fa-thumbtack text-warning mr-1" title="Fissata in cima"></i>
            <?php endif; ?>
            <strong><?php echo esc_html($item['title']); ?></strong>
            <br>
            <small class="text-muted"><?php echo newsSnippet($item['content']); ?></small>
          </td>
          <td><small><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></small></td>
          <td><small><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></small></td>
          <td>
            <?php if ($item['is_published']) : ?>
              <span class="badge badge-success">Pubblicata</span>
            <?php else : ?>
              <span class="badge badge-secondary">Bozza</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex flex-wrap" style="gap:4px;">
              <a href="<?php echo ROOT_URL; ?>admin/?page=edit-news&id=<?php echo $item['id']; ?>"
                 class="btn btn-sm btn-outline-secondary" title="Modifica contenuto">
                <i class="fas fa-edit"></i>
              </a>

              <form method="post" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                <input type="hidden" name="current_state" value="<?php echo $item['is_published']; ?>">
                <button type="submit" name="toggle_publish"
                        class="btn btn-sm btn-outline-<?php echo $item['is_published'] ? 'warning' : 'success'; ?>"
                        title="<?php echo $item['is_published'] ? 'Archivia come bozza' : 'Pubblica'; ?>">
                  <i class="fas <?php echo $item['is_published'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                </button>
              </form>

              <?php if (array_key_exists('is_pinned', $item)) : // enabled after DB migration ?>
              <form method="post" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                <input type="hidden" name="current_state" value="<?php echo $item['is_pinned']; ?>">
                <button type="submit" name="toggle_pin"
                        class="btn btn-sm btn-outline-<?php echo $item['is_pinned'] ? 'secondary' : 'warning'; ?>"
                        title="<?php echo $item['is_pinned'] ? 'Rimuovi pin' : 'Fissa in cima alla lista'; ?>">
                  <i class="fas fa-thumbtack"></i>
                </button>
              </form>
              <?php endif; ?>

              <form method="post" class="d-inline"
                    onsubmit="return confirm('Eliminare questa news? L\'operazione è irreversibile.')">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                <button type="submit" name="delete_news" class="btn btn-sm btn-outline-danger" title="Elimina">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div><!-- /tab-news -->


<!-- ═══════════════════════ DOWNLOADS TAB ═══════════════════════ -->
<div class="<?php echo $activeTab === 'downloads' ? '' : 'd-none'; ?>" id="tab-downloads">

  <!-- Add download form -->
  <div class="card mb-4">
    <div class="card-header"><strong><i class="fas fa-upload mr-1"></i>Carica nuovo file</strong></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="form-group">
          <label for="dl_title">Titolo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="dl_title" name="dl_title" required>
        </div>
        <div class="form-group">
          <label for="dl_description">Descrizione</label>
          <textarea id="dl_description" name="dl_description" class="form-control"></textarea>
        </div>
        <div class="form-group">
          <label for="file">File <span class="text-danger">*</span></label>
          <input type="file" class="form-control-file" id="file" name="file" required
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif">
          <small class="form-text text-muted">Formati accettati: PDF, Word, Excel, PowerPoint, ZIP, immagini</small>
        </div>
        <button type="submit" name="add_download" class="btn btn-primary">
          <i class="fas fa-upload"></i> Carica File
        </button>
      </form>
    </div>
  </div>

  <!-- Downloads list -->
  <h4>Elenco Files</h4>

  <?php if (empty($allDownloads)) : ?>
    <p class="text-muted">Nessun file presente.</p>
  <?php else : ?>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="thead-light">
        <tr>
          <th>File</th>
          <th style="width:28%">Titolo</th>
          <th>Dim.</th>
          <th>News</th>
          <th>Data</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allDownloads as $dl) : ?>
        <tr>
          <td>
            <i class="<?php echo fileTypeIcon($dl['filename']); ?> fa-lg mr-1"></i>
            <small><?php echo esc_html($dl['filename']); ?></small>
          </td>
          <td><?php echo esc_html($dl['title']); ?></td>
          <td><small><?php echo round($dl['filesize'] / 1024, 1); ?> KB</small></td>
          <td>
            <?php if ($dl['linked_news_count'] > 0) : ?>
              <span class="badge badge-info"><?php echo $dl['linked_news_count']; ?></span>
            <?php else : ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><small><?php echo date('d/m/Y', strtotime($dl['created_at'])); ?></small></td>
          <td>
            <div class="d-flex flex-wrap" style="gap:4px;">
              <a href="<?php echo ROOT_URL . $dl['filepath']; ?>"
                 class="btn btn-sm btn-outline-primary" target="_blank" title="Visualizza">
                <i class="fas fa-eye"></i>
              </a>

              <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-dl"
                      data-id="<?php echo $dl['id']; ?>"
                      data-title="<?php echo htmlspecialchars($dl['title'], ENT_QUOTES); ?>"
                      data-description="<?php echo htmlspecialchars($dl['description'] ?? '', ENT_QUOTES); ?>"
                      title="Modifica titolo/descrizione">
                <i class="fas fa-edit"></i>
              </button>

              <form method="post" class="d-inline"
                    onsubmit="return confirm('Eliminare il file? L\'operazione è irreversibile.')">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $dl['id']; ?>">
                <button type="submit" name="delete_download" class="btn btn-sm btn-outline-danger" title="Elimina">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div><!-- /tab-downloads -->


<!-- ═══════════════════════ EDIT DOWNLOAD MODAL ═══════════════════════ -->
<div class="modal fade" id="editDownloadModal" tabindex="-1" role="dialog" aria-labelledby="editDownloadModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="post">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" id="edit_dl_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editDownloadModalLabel">
            <i class="fas fa-edit mr-1"></i>Modifica File
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="edit_dl_title">Titolo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit_dl_title" name="dl_title" required>
          </div>
          <div class="form-group">
            <label for="edit_dl_description">Descrizione</label>
            <textarea class="form-control" id="edit_dl_description" name="dl_description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
          <button type="submit" name="edit_download" class="btn btn-primary">
            <i class="fas fa-save"></i> Salva
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
$(document).ready(function () {

  // News content — full toolbar
  $('#content').summernote({
    height: 300,
    toolbar: [
      ['style',  ['bold', 'italic', 'underline', 'clear']],
      ['font',   ['strikethrough', 'superscript', 'subscript']],
      ['para',   ['ul', 'ol', 'paragraph']],
      ['table',  ['table']],
      ['insert', ['link', 'hr']],
      ['view',   ['fullscreen', 'codeview']]
    ],
    placeholder: 'Scrivi il contenuto della news...'
  });

  // New download description — simple toolbar
  $('#dl_description').summernote({
    height: 120,
    toolbar: [
      ['style',  ['bold', 'italic', 'underline']],
      ['para',   ['ul', 'ol']],
      ['insert', ['link']],
      ['view',   ['codeview']]
    ],
    placeholder: 'Descrizione del file (opzionale)...'
  });

  // Edit modal description — simple toolbar (initialized once)
  $('#edit_dl_description').summernote({
    height: 120,
    toolbar: [
      ['style',  ['bold', 'italic', 'underline']],
      ['para',   ['ul', 'ol']],
      ['insert', ['link']],
      ['view',   ['codeview']]
    ]
  });

  // Open edit modal with pre-filled data
  $('.btn-edit-dl').on('click', function () {
    var id          = $(this).data('id');
    var title       = $(this).data('title');
    var description = $(this).data('description');

    $('#edit_dl_id').val(id);
    $('#edit_dl_title').val(title);
    $('#edit_dl_description').summernote('code', description);
    $('#editDownloadModal').modal('show');
  });
});
</script>
