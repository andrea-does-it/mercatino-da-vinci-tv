<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
$downloadMgr = new DownloadManager();
$downloads = $downloadMgr->getAllDownloadsWithUserInfo();
?>

<h1>Downloads</h1>

<?php 
// Add management button for admin and pwuser user types
if ($loggedInUser && ($loggedInUser->user_type == 'admin' || $loggedInUser->user_type == 'pwuser')) : 
?>
  <div class="mb-4">
    <a href="<?php echo ROOT_URL; ?>admin/?page=download-management" class="btn btn-primary">
      <i class="fas fa-edit"></i> Gestione Download
    </a>
  </div>
<?php endif; ?>

<?php if (count($downloads) > 0) : ?>
  <div class="downloads-list">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Title</th>
          <th>Description</th>
          <th>File Size</th>
          <th>Uploaded</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($downloads as $download) : ?>
          <tr>
            <td><?php echo esc_html($download['title']); ?></td>
            <td><?php echo esc_html($download['description']); ?></td>
            <td><?php echo round($download['filesize'] / 1024, 2); ?> KB</td>
            <td>
              <?php echo date('F j, Y', strtotime($download['created_at'])); ?><br>
              <small>by <?php echo esc_html($download['first_name'] . ' ' . $download['last_name']); ?></small>
            </td>
            <td>
              <a href="<?php echo ROOT_URL . $download['filepath']; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-download"></i> Download
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else : ?>
  <p>No downloads available.</p>
<?php endif; ?>