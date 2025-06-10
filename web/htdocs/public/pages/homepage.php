<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }
?>

<div class="row">
  <!-- Main content column (left side) -->
  <div class="col-lg-8">

    <h1>Benvenuti</h1>
    <p class="lead">Benvenuti nel sito del Comitato Genitori del Liceo Scientifico Leonardo Da Vinci di Treviso! </p>
    <p class="lead">Il Comitato dei Genitori è un organismo composto da tutti i rappresentanti dei genitori regolarmente eletti nei Consigli di Classe e nel Consiglio d’Istituto e
    dai genitori che desiderano parteciparvi.
    Esso persegue lo scopo di promuovere la partecipazione dei genitori alla vita scolastica nell’interesse degli studenti del liceo durante il loro processo di
    formazione e di apprendimento. </p>
    <p class="lead">Il Comitato si propone come finalità di:  </p>
    <p class="lead">• facilitare il confronto e la discussione tra alunni, insegnanti, genitori e Dirigente Scolastico sulle problematiche che emergono all’interno della scuola; </p>
    <p class="lead">• coordinare le proposte, emerse dai Genitori, da far prevenire all’attenzione del Consiglio d’Istituto, del Collegio dei Docenti, dei Consigli di classe; </p>
    <p class="lead">• promuovere incontri di formazione per genitori, aperti ai docenti e agli studenti, riguardanti le problematiche degli adolescenti; </p>
    <p class="lead">• proporre conferenze di valore culturale e sociale; </p>
    <p class="lead">• monitorare l’efficacia del Patto Educativo di Corresponsabilità proposto dall’Istituto e sottoscritto dai genitori; </p>
    <p class="lead">• favorire la conoscenza presso i Genitori del Piano dell’Offerta Formativa dell’Istituto e di verificare la sua efficacia e attualità educativa. </p>
    <p class="lead"></p>
    <p class="lead text-danger font-weight-bold">Clicca sul bottone se vuoi mettere in vendita libri per il mercatino del libro usato.</p>
    <a href="<?php echo ROOT_URL . 'shop/?page=products-list'; ?>" class="btn btn-primary btn-lg mb-5 mt-3">Vai al Mercatino &raquo;</a>

  </div>

  <!-- News column (right side) -->
  <div class="col-lg-4">
    <?php
    // Get the last 3 news items
    $newsMgr = new NewsManager();
    $latestNews = $newsMgr->getRecentNews(3);
    ?>

    <!-- Latest News Section -->
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <h4 class="mb-0">Ultime Notizie</h4>
      </div>
      <div class="card-body">
        <?php if (count($latestNews) > 0) : ?>
          <div class="latest-news">
            <?php foreach ($latestNews as $item) : ?>
              <div class="news-item mb-3 pb-3 border-bottom">
                <h5><?php echo esc_html($item['title']); ?></h5>
                <div class="text-muted small mb-2">
                  <?php echo date('d/m/Y', strtotime($item['created_at'])); ?> 
                </div>
                <p>
                  <?php 
                  // Limit content to 100 characters for the sidebar
                  $content = esc_html($item['content']);
                  echo (strlen($content) > 100) ? substr($content, 0, 100) . '...' : $content; 
                  ?>
                </p>
                <a href="<?php echo ROOT_URL; ?>public?page=news" class="btn btn-sm btn-outline-info">Leggi di più &raquo;</a>
                
                <?php
                // Check if there are any attachments
                $db = new DB();
                $attachments = $db->query("
                  SELECT d.*
                  FROM downloads d
                  JOIN news_downloads nd ON d.id = nd.download_id
                  WHERE nd.news_id = " . $item['id'] . "
                  LIMIT 1
                ");
                
                if (count($attachments) > 0) : ?>
                  <div class="attachment mt-2 small">
                    <i class="fas fa-paperclip"></i> 
                    <a href="<?php echo ROOT_URL . $attachments[0]['filepath']; ?>" target="_blank">
                      <?php echo esc_html($attachments[0]['title']); ?>
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            
            <div class="text-right">
              <a href="<?php echo ROOT_URL; ?>public?page=news" class="btn btn-sm btn-info">Tutte le notizie &raquo;</a>
            </div>
          </div>
        <?php else : ?>
          <p>Nessuna notizia disponibile.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>