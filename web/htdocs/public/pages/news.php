<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
$newsMgr = new NewsManager();
$news = $newsMgr->getRecentNews(20);
?>

<h1>News & Aggiornamenti</h1>

<?php 
// Add management button for admin and pwuser user types
if ($loggedInUser && ($loggedInUser->user_type == 'admin' || $loggedInUser->user_type == 'pwuser')) : 
?>
  <div class="mb-4">
    <a href="<?php echo ROOT_URL; ?>admin/?page=news-management" class="btn btn-primary">
      <i class="fas fa-edit"></i> Gestione News
    </a>
  </div>
<?php endif; ?>

<?php if (count($news) > 0) : ?>
  <div class="news-list">
    <?php foreach ($news as $item) : ?>
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title"><?php echo esc_html($item['title']); ?></h5>
          <h6 class="card-subtitle mb-2 text-muted">
            <?php echo date('F j, Y', strtotime($item['created_at'])); ?> by 
            <?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?>
          </h6>
          <p class="card-text"><?php echo nl2br(esc_html($item['content'])); ?></p>
          
          <?php
          // Check if there are any attachments
          $db = new DB();
          $attachments = $db->query("
            SELECT d.*
            FROM downloads d
            JOIN news_downloads nd ON d.id = nd.download_id
            WHERE nd.news_id = " . $item['id'] . "
            ORDER BY d.created_at DESC
          ");
          
          if (count($attachments) > 0) : ?>
            <div class="attachments mt-3">
              <strong>Attachments:</strong>
              <ul class="list-group list-group-flush">
                <?php foreach ($attachments as $attachment) : ?>
                  <li class="list-group-item">
                    <a href="<?php echo ROOT_URL . $attachment['filepath']; ?>" target="_blank">
                      <i class="fas fa-download"></i> <?php echo esc_html($attachment['title']); ?>
                    </a>
                    <span class="text-muted">(<?php echo round($attachment['filesize'] / 1024, 2); ?> KB)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else : ?>
  <p>No news items available.</p>
<?php endif; ?>