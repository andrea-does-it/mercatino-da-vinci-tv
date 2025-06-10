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

$downloadMgr = new DownloadManager();

// Handle file deletion
if (isset($_POST['delete_download'])) {
    $id = (int)$_POST['id'];
    
    if ($id > 0) {
        // First get the file info to delete the physical file
        $download = $downloadMgr->get($id);
        
        if ($download && isset($download->filepath)) {
            // Delete the physical file
            $filePath = ROOT_PATH . $download->filepath;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Also delete any references in news_downloads linking table if it exists
            $db = new DB();
            $db->query("DELETE FROM news_downloads WHERE download_id = $id");
            
            // Delete the database record
            $deleted = $downloadMgr->delete($id);
            
            if ($deleted) {
                $alertMsg = 'deleted';
            } else {
                $alertMsg = 'err';
            }
        }
    }
}

// Handle form submissions
if (isset($_POST['add_download']) && isset($_FILES['file'])) {
  $title = trim($_POST['title']);
  $description = trim($_POST['description']);
  
  if ($title && $_FILES['file']['size'] > 0) {
    $downloadId = $downloadMgr->uploadFile(
      $_FILES['file'], 
      $loggedInUser->id,
      $title,
      $description
    );
    
    if ($downloadId) {
      $alertMsg = 'created';
    } else {
      $alertMsg = 'err';
    }
  } else {
    $alertMsg = 'mandatory_fields';
  }
}

// Get all downloads
$downloads = $downloadMgr->getAll();
?>

<h1>Gestione Download</h1>

<div class="card mb-4">
  <div class="card-header">
    Aggiungi File
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label for="title">Titolo</label>
        <input type="text" class="form-control" id="title" name="title" required>
      </div>
      <div class="form-group">
        <label for="description">Descrizione</label>
        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label for="file">File</label>
        <input type="file" class="form-control-file" id="file" name="file" required>
      </div>
      <button type="submit" name="add_download" class="btn btn-primary">Upload</button>
    </form>
  </div>
</div>

<h3>Elenco Files</h3>
<table class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Title</th>
      <th scope="col">Filename</th>
      <th scope="col">Size</th>
      <th scope="col">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($downloads as $download) : ?>
    <tr>
      <td><?php echo esc_html($download->title); ?></td>
      <td><?php echo esc_html($download->filename); ?></td>
      <td><?php echo round($download->filesize / 1024, 2); ?> KB</td>
      <td>
        <a href="<?php echo ROOT_URL . $download->filepath; ?>" class="btn btn-sm btn-outline-primary" target="_blank">View</a>
        <form method="post" class="d-inline">
          <input type="hidden" name="id" value="<?php echo $download->id; ?>">
          <button type="submit" name="delete_download" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>