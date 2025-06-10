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

// Handle delete action
if (isset($_POST['delete_news'])) {
    $id = (int)$_POST['id'];
    
    if ($id > 0) {
        // First delete any associated downloads (if you're using the news_downloads linking table)
        $db = new DB();
        $db->query("DELETE FROM news_downloads WHERE news_id = $id");
        
        // Then delete the news item
        $deleted = $newsMgr->delete($id);
        
        if ($deleted) {
            $alertMsg = 'deleted';
        } else {
            $alertMsg = 'err';
        }
    }
}

// Handle form submissions
if (isset($_POST['add_news'])) {
  $title = trim($_POST['title']);
  $content = trim($_POST['content']);
  
  if ($title && $content) {
    $news = new News(0, $loggedInUser->id, $title, $content);
    $id = $newsMgr->create($news);
    
    // Handle file attachment if present
    if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
      $downloadMgr = new DownloadManager();
      $downloadId = $downloadMgr->uploadFile(
        $_FILES['attachment'], 
        $loggedInUser->id,
        $title,
        'Attachment for news: ' . $title
      );
      
      if ($downloadId && $id) {
        // Link news to download
        $db = new DB();
        $db->query("INSERT INTO news_downloads (news_id, download_id) VALUES ($id, $downloadId)");
      }
    }
    
    $alertMsg = 'created';
  } else {
    $alertMsg = 'mandatory_fields';
  }
}

// Get all news items
$news = $newsMgr->getAll();
?>

<h1>Gestione News</h1>

<div class="card mb-4">
  <div class="card-header">
    Aggiungi News
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label for="title">Titolo</label>
        <input type="text" class="form-control" id="title" name="title" required>
      </div>
      <div class="form-group">
        <label for="content">Contenuto</label>
        <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
      </div>
      <div class="form-group">
        <label for="attachment">Allegato (Facoltativo)</label>
        <input type="file" class="form-control-file" id="attachment" name="attachment">
      </div>
      <button type="submit" name="add_news" class="btn btn-primary">Aggiungi News</button>
    </form>
  </div>
</div>

<h3>Elenco News</h3>
<table class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Titolo</th>
      <th scope="col">Data</th>
      <th scope="col">Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($news as $item) : ?>
    <tr>
      <td><?php echo esc_html($item->title); ?></td>
      <td><?php echo esc_html($item->created_at); ?></td>
      <td>
        <a href="<?php echo ROOT_URL . 'admin/?page=edit-news&id=' . $item->id; ?>" class="btn btn-sm btn-outline-secondary">Modifica</a>
        <form method="post" class="d-inline">
          <input type="hidden" name="id" value="<?php echo $item->id; ?>">
          <button type="submit" name="delete_news" class="btn btn-sm btn-outline-danger" onclick="return confirm('Confermi la cancellazione?')">Elimina</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>