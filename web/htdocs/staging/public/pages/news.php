<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
$newsMgr = new NewsManager();
$news    = $newsMgr->getRecentNews(20);
?>

<h1>News &amp; Aggiornamenti</h1>

<?php if ($loggedInUser && ($loggedInUser->user_type == 'admin' || $loggedInUser->user_type == 'pwuser')) : ?>
  <div class="mb-4">
    <a href="<?php echo ROOT_URL; ?>admin/?page=news-management" class="btn btn-primary">
      <i class="fas fa-edit"></i> Gestione News
    </a>
  </div>
<?php endif; ?>

<?php if (count($news) > 0) : ?>
  <div class="news-list">
    <?php foreach ($news as $item) : ?>
      <div class="card mb-4 <?php echo !empty($item['is_pinned']) ? 'border-warning' : ''; ?>">
        <?php if (!empty($item['is_pinned'])) : ?>
          <div class="card-header bg-warning text-dark py-1">
            <small><i class="fas fa-thumbtack mr-1"></i>In evidenza</small>
          </div>
        <?php endif; ?>
        <div class="card-body">
          <h5 class="card-title"><?php echo esc_html($item['title']); ?></h5>
          <h6 class="card-subtitle mb-3 text-muted">
            <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
            &mdash; <?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?>
          </h6>

          <!-- Content rendered as HTML (stored via Summernote editor) -->
          <div class="card-text news-content">
            <?php echo $item['content']; ?>
          </div>

          <?php
          // Fetch attachments for this news item
          $db          = new DB();
          $attachments = $db->prepare(
            "SELECT d.* FROM downloads d
             JOIN news_downloads nd ON d.id = nd.download_id
             WHERE nd.news_id = ?
             ORDER BY d.created_at DESC",
            [(int)$item['id']]
          );

          if (is_array($attachments) && count($attachments) > 0) : ?>
            <div class="attachments mt-3 pt-2 border-top">
              <strong><i class="fas fa-paperclip mr-1"></i>Allegati:</strong>
              <ul class="list-group list-group-flush mt-1">
                <?php foreach ($attachments as $attachment) : ?>
                  <li class="list-group-item px-0 py-1">
                    <a href="<?php echo ROOT_URL . $attachment['filepath']; ?>" target="_blank">
                      <i class="fas fa-download mr-1"></i><?php echo esc_html($attachment['title']); ?>
                    </a>
                    <span class="text-muted ml-1">
                      (<?php echo round($attachment['filesize'] / 1024, 1); ?> KB)
                    </span>
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
  <p class="text-muted">Nessuna news disponibile.</p>
<?php endif; ?>
