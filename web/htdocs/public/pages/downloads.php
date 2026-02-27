<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
$downloadMgr = new DownloadManager();
$downloads   = $downloadMgr->getAllDownloadsWithUserInfo();
?>

<h1>Download</h1>

<?php if ($loggedInUser && ($loggedInUser->user_type == 'admin' || $loggedInUser->user_type == 'pwuser')) : ?>
  <div class="mb-4">
    <a href="<?php echo ROOT_URL; ?>admin/?page=news-management&tab=downloads" class="btn btn-primary">
      <i class="fas fa-edit"></i> Gestione Download
    </a>
  </div>
<?php endif; ?>

<?php if (count($downloads) > 0) : ?>
  <div class="downloads-list">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Descrizione</th>
          <th>Dimensione</th>
          <th>Caricato</th>
          <th>Azione</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($downloads as $download) : ?>
          <tr>
            <td><?php echo esc_html($download['title']); ?></td>
            <td><?php echo $download['description']; ?></td>
            <td><?php echo round($download['filesize'] / 1024, 1); ?> KB</td>
            <td>
              <?php echo date('d/m/Y', strtotime($download['created_at'])); ?><br>
              <small><?php echo esc_html($download['first_name'] . ' ' . $download['last_name']); ?></small>
            </td>
            <td>
              <a href="<?php echo ROOT_URL . $download['filepath']; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-download"></i> Scarica
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else : ?>
  <p class="text-muted">Nessun file disponibile.</p>
<?php endif; ?>
