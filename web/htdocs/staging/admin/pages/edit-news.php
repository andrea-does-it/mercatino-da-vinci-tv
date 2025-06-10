<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;

// Ensure only admins can access this page
if (!$loggedInUser || $loggedInUser->user_type != 'admin') {
  echo "<script>location.href='".ROOT_URL."auth?page=login&msg=forbidden';</script>";
  exit;
}

$newsMgr = new NewsManager();

// Get news ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
  echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=not_found';</script>";
  exit;
}

// Get the news item
$news = $newsMgr->get($id);

// If news not found
if (!isset($news->id)) {
  echo "<script>location.href='".ROOT_URL."admin/?page=news-management&msg=not_found';</script>";
  exit;
}

// Handle attachment deletion
if (isset($_POST['delete_attachment'])) {
    $attachmentId = (int)$_POST['attachment_id'];
    
    if ($attachmentId > 0) {
      // Remove the link between news and download
      $db = new DB();
      $db->query("DELETE FROM news_downloads WHERE news_id = $id AND download_id = $attachmentId");
      
      // Optionally also delete the file itself
      // $downloadMgr->delete($attachmentId);
      
      $alertMsg = 'attachment_deleted';
    }
}

// Handle form submission
if (isset($_POST['update_news'])) {
  $title = trim($_POST['title']);
  $content = trim($_POST['content']);
  
  if ($title && $content) {
    $news->title = $title;
    $news->content = $content;
    $news->updated_at = date('Y-m-d H:i:s');
    
    $success = $newsMgr->update($news, $id);
    
    // Handle file attachment if present
    if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
      $downloadMgr = new DownloadManager();
      $downloadId = $downloadMgr->uploadFile(
        $_FILES['attachment'], 
        $loggedInUser->id,
        $title,
        'Attachment for news: ' . $title
      );
      
      if ($downloadId) {
        // Link news to download
        $db = new DB();
        $db->query("INSERT INTO news_downloads (news_id, download_id) VALUES ($id, $downloadId)");
      }
    }
    
    $alertMsg = 'updated';
  } else {
    $alertMsg = 'mandatory_fields';
  }
}

// Get attachments
$db = new DB();
$attachments = $db->query("
  SELECT d.*
  FROM downloads d
  JOIN news_downloads nd ON d.id = nd.download_id
  WHERE nd.news_id = $id
  ORDER BY d.created_at DESC
");
?>

<h1>Aggiorna News</h1>

<a href="<?php echo ROOT_URL . 'admin/?page=news-management'; ?>" class="back underline d-block mb-4">&laquo; Torna a Gestione News</a>

<div class="card mb-4">
  <div class="card-header">
    Modifica News
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label for="title">Titolo</label>
        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($news->title); ?>" required>
      </div>
      <div class="form-group">
        <label for="content">Contenuto</label>
        <textarea class="form-control" id="content" name="content" rows="4" required><?php echo htmlspecialchars($news->content); ?></textarea>
      </div>
      
      <?php if (count($attachments) > 0) : ?>
      <div class="form-group">
        <label>Current Attachments</label>
        <ul class="list-group mb-3">
          <?php foreach ($attachments as $attachment) : ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo htmlspecialchars($attachment['filename']); ?>
            <div>
              <a href="<?php echo ROOT_URL . $attachment['filepath']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">View</a>
              <form method="post" class="d-inline">
                <input type="hidden" name="attachment_id" value="<?php echo $attachment['id']; ?>">
                <button type="submit" name="delete_attachment" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Remove</button>
              </form>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      
      <div class="form-group">
        <label for="attachment">Aggiungi nuovo allegato (Facoltativo)</label>
        <input type="file" class="form-control-file" id="attachment" name="attachment">
      </div>
      <button type="submit" name="update_news" class="btn btn-primary">Aggiorna News</button>
    </form>
  </div>
</div>