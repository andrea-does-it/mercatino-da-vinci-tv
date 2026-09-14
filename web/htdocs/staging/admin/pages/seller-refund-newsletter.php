<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $sellerRefundMgr = new SellerRefundManager();

  // Default to current year
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

  // Get filters
  $newsletterFilter = isset($_GET['newsletter']) ? $_GET['newsletter'] : '';
  $preferenceFilter = isset($_GET['preference']) ? $_GET['preference'] : '';
  // Filtri sul profilo del venditore (tabella user)
  $ibanFilter = isset($_GET['iban']) ? $_GET['iban'] : '';
  $donationFilter = isset($_GET['donation']) ? $_GET['donation'] : '';
  $toContactOnly = isset($_GET['tocontact']) && $_GET['tocontact'] === '1';

  // Query string dei filtri correnti, per i link che devono tornare alla stessa vista
  $filterQuery = http_build_query([
    'page' => 'seller-refund-newsletter',
    'year' => $selectedYear,
    'newsletter' => $newsletterFilter,
    'preference' => $preferenceFilter,
    'iban' => $ibanFilter,
    'donation' => $donationFilter,
    'tocontact' => $toContactOnly ? '1' : ''
  ]);

  // Handle actions
  if (isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      switch ($_POST['action']) {
        case 'mark_sent':
          // Mark single record as sent
          if (isset($_POST['refund_id'])) {
            $sellerRefundMgr->markNewsletterSent((int)$_POST['refund_id'], $loggedInUser->id);
            $alertMsg = 'newsletter_marked_sent';
          }
          break;

        case 'mark_multiple_sent':
          // Mark multiple records as sent
          if (isset($_POST['refund_ids']) && is_array($_POST['refund_ids'])) {
            $count = $sellerRefundMgr->markMultipleNewsletterSent($_POST['refund_ids'], $loggedInUser->id);
            $alertMsg = 'newsletters_marked_sent';
          }
          break;

        case 'reset_status':
          // Reset newsletter status for re-sending
          if (isset($_POST['refund_id'])) {
            $sellerRefundMgr->resetNewsletterStatus((int)$_POST['refund_id']);
            $alertMsg = 'newsletter_reset';
          }
          break;

        case 'send_email':
          // Send email to single seller
          if (isset($_POST['refund_id'])) {
            $refundId = (int)$_POST['refund_id'];
            $emailData = $sellerRefundMgr->getSellerRefundForEmail($refundId);
            if ($emailData) {
              $emailContent = $sellerRefundMgr->generateNewsletterEmailContent($emailData);

              // Testo semplice -> HTML, con il link preferenze cliccabile
              $htmlBody = $sellerRefundMgr->buildNewsletterHtmlBody($emailContent['body']);

              $mailSent = send_mail($emailData->email, $emailContent['subject'], $htmlBody);

              if ($mailSent) {
                // Mark as sent
                $sellerRefundMgr->markNewsletterSent($refundId, $loggedInUser->id);
                $alertMsg = 'email_sent';
              } else {
                $alertMsg = 'email_error';
              }
            }
          }
          break;

        case 'send_multiple_emails':
          // Send emails to multiple sellers
          if (isset($_POST['refund_ids']) && is_array($_POST['refund_ids'])) {
            $sentCount = 0;
            $errorCount = 0;

            foreach ($_POST['refund_ids'] as $refundId) {
              $refundId = (int)$refundId;
              $emailData = $sellerRefundMgr->getSellerRefundForEmail($refundId);
              if ($emailData && !$emailData->newsletter_sent) {
                $emailContent = $sellerRefundMgr->generateNewsletterEmailContent($emailData);

                // Testo semplice -> HTML, con il link preferenze cliccabile
                $htmlBody = $sellerRefundMgr->buildNewsletterHtmlBody($emailContent['body']);

                $mailSent = send_mail($emailData->email, $emailContent['subject'], $htmlBody);

                if ($mailSent) {
                  $sellerRefundMgr->markNewsletterSent($refundId, $loggedInUser->id);
                  $sentCount++;
                } else {
                  $errorCount++;
                }
              }
            }

            if ($errorCount > 0) {
              $alertMsg = 'emails_partial';
              $_SESSION['emails_sent_count'] = $sentCount;
              $_SESSION['emails_error_count'] = $errorCount;
            } else {
              $alertMsg = 'emails_sent';
              $_SESSION['emails_sent_count'] = $sentCount;
            }
          }
          break;

        case 'preview_email':
          // This is handled below with $showPreview
          break;
      }
    }
  }

  // Email preview
  $showPreview = false;
  $previewData = null;
  if (isset($_POST['action']) && $_POST['action'] === 'preview_email' && isset($_POST['refund_id'])) {
    $previewData = $sellerRefundMgr->getSellerRefundForEmail((int)$_POST['refund_id']);
    if ($previewData) {
      $emailContent = $sellerRefundMgr->generateNewsletterEmailContent($previewData);
      $previewData->email_subject = $emailContent['subject'];
      $previewData->email_body = $emailContent['body'];
      // Stesso rendering dell'email inviata, così l'anteprima mostra il link cliccabile
      $previewData->email_html = email_text_to_html($emailContent['body']);
      $showPreview = true;
    }
  }

  // Get available years
  $availableYears = $sellerRefundMgr->getAvailableYears();
  if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
  }

  // Get sellers for newsletter
  $sellers = $sellerRefundMgr->getSellersForNewsletter(
    $selectedYear,
    $newsletterFilter ?: null,
    $preferenceFilter ?: null,
    $ibanFilter ?: null,
    $donationFilter ?: null,
    $toContactOnly
  );

  // Get statistics
  $stats = $sellerRefundMgr->getNewsletterStats($selectedYear);

  // Alert messages
  $alertMessages = [
    'newsletter_marked_sent' => ['type' => 'success', 'text' => 'Newsletter segnata come inviata.'],
    'newsletters_marked_sent' => ['type' => 'success', 'text' => 'Newsletter segnate come inviate.'],
    'newsletter_reset' => ['type' => 'info', 'text' => 'Stato newsletter resettato.'],
    'email_sent' => ['type' => 'success', 'text' => 'Email inviata con successo!'],
    'email_error' => ['type' => 'danger', 'text' => 'Errore durante l\'invio dell\'email. Riprova.'],
    'emails_sent' => ['type' => 'success', 'text' => 'Email inviate con successo: ' . (isset($_SESSION['emails_sent_count']) ? $_SESSION['emails_sent_count'] : 0)],
    'emails_partial' => ['type' => 'warning', 'text' => 'Email inviate: ' . (isset($_SESSION['emails_sent_count']) ? $_SESSION['emails_sent_count'] : 0) . ', errori: ' . (isset($_SESSION['emails_error_count']) ? $_SESSION['emails_error_count'] : 0)],
  ];
  // Clear session counts after reading
  unset($_SESSION['emails_sent_count'], $_SESSION['emails_error_count']);
?>

<h1>Invio Newsletter Preferenze Pagamento - <?php echo $selectedYear; ?>
  <a href="<?php echo ROOT_URL; ?>admin/?page=help-seller-refund-newsletter" class="btn btn-sm btn-outline-info ml-2" title="Guida">
    <i class="fas fa-question-circle"></i> Guida
  </a>
</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna a Rimborsi Venditori
</a>

<?php if ($alertMsg && isset($alertMessages[$alertMsg])): ?>
  <div class="alert alert-<?php echo $alertMessages[$alertMsg]['type']; ?> alert-dismissible fade show">
    <?php echo $alertMessages[$alertMsg]['text']; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
  </div>
<?php endif; ?>

<!-- Email Preview Modal -->
<?php if ($showPreview && $previewData): ?>
<div class="modal fade show" id="previewModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Anteprima Email per <?php echo esc_html($previewData->first_name . ' ' . $previewData->last_name); ?></h5>
        <a href="<?php echo ROOT_URL; ?>admin/?<?php echo esc_html($filterQuery); ?>" class="close">&times;</a>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <strong>A:</strong> <?php echo esc_html($previewData->email); ?>
        </div>
        <div class="mb-3">
          <strong>Oggetto:</strong> <?php echo esc_html($previewData->email_subject); ?>
        </div>
        <hr>
        <div class="bg-light p-3" style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<?php
  // Già escapato da email_text_to_html(): il testo è stato reso HTML lì,
  // quindi qui NON va applicato un secondo escape (romperebbe i link).
  echo $previewData->email_html;
?>
        </div>
        <hr>
        <div class="alert alert-info mb-0">
          <small>
            <i class="fas fa-link"></i> Link preferenze: <a href="<?php echo esc_html($previewData->landing_url); ?>" target="_blank"><?php echo esc_html($previewData->landing_url); ?></a>
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?php echo ROOT_URL; ?>admin/?<?php echo esc_html($filterQuery); ?>" class="btn btn-secondary">Chiudi</a>
        <form method="post" class="d-inline">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="mark_sent">
          <input type="hidden" name="refund_id" value="<?php echo $previewData->id; ?>">
          <button type="submit" class="btn btn-outline-success">
            <i class="fas fa-check"></i> Segna come Inviata (senza invio)
          </button>
        </form>
        <form method="post" class="d-inline">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="send_email">
          <input type="hidden" name="refund_id" value="<?php echo $previewData->id; ?>">
          <button type="submit" class="btn btn-primary" onclick="return confirm('Inviare l\'email a <?php echo esc_html($previewData->email); ?>?');">
            <i class="fas fa-paper-plane"></i> Invia Email
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Filters and Year selector -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="form-inline">
      <input type="hidden" name="page" value="seller-refund-newsletter">

      <div class="form-group mr-3">
        <label for="year" class="mr-2">Anno:</label>
        <select name="year" id="year" class="form-control" onchange="this.form.submit()">
          <?php foreach ($availableYears as $year): ?>
            <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
              <?php echo $year; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="newsletter" class="mr-2">Newsletter:</label>
        <select name="newsletter" id="newsletter" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="sent" <?php echo $newsletterFilter === 'sent' ? 'selected' : ''; ?>>Inviate</option>
          <option value="not_sent" <?php echo $newsletterFilter === 'not_sent' ? 'selected' : ''; ?>>Non inviate</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="preference" class="mr-2">Preferenza:</label>
        <select name="preference" id="preference" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="set" <?php echo $preferenceFilter === 'set' ? 'selected' : ''; ?>>Impostata</option>
          <option value="not_set" <?php echo $preferenceFilter === 'not_set' ? 'selected' : ''; ?>>Non impostata</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="iban" class="mr-2">IBAN:</label>
        <select name="iban" id="iban" class="form-control" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <option value="with" <?php echo $ibanFilter === 'with' ? 'selected' : ''; ?>>Con IBAN</option>
          <option value="without" <?php echo $ibanFilter === 'without' ? 'selected' : ''; ?>>Senza IBAN</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="donation" class="mr-2">Donazione:</label>
        <select name="donation" id="donation" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="yes" <?php echo $donationFilter === 'yes' ? 'selected' : ''; ?>>Dona</option>
          <option value="no" <?php echo $donationFilter === 'no' ? 'selected' : ''; ?>>Non dona</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="tocontact" id="tocontact" value="1"
                 <?php echo $toContactOnly ? 'checked' : ''; ?> onchange="this.form.submit()">
          <label class="form-check-label" for="tocontact" title="Venditori senza IBAN oppure che non hanno scelto di donare i libri invenduti">
            <strong>Da contattare</strong> (manca IBAN o donazione)
          </label>
        </div>
      </div>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-newsletter&year=<?php echo $selectedYear; ?>" class="btn btn-secondary">
        <i class="fas fa-sync"></i> Reset
      </a>
    </form>
  </div>
</div>

<!-- Statistics -->
<?php if ($stats): ?>
<div class="row mb-4">
  <div class="col-md-2">
    <div class="card bg-primary text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->total_sellers; ?></h4>
        <small>Totale</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-success text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->newsletter_sent_count; ?></h4>
        <small>Email Inviate</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-warning text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->newsletter_not_sent_count; ?></h4>
        <small>Da Inviare</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-info text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->preference_set_count; ?></h4>
        <small>Con Preferenza</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-secondary text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->preference_not_set_count; ?></h4>
        <small>Senza Preferenza</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-danger text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->sent_no_response_count; ?></h4>
        <small>Inviate Senza Risposta</small>
      </div>
    </div>
  </div>
</div>

<!-- Profilo venditore: chi non ha ancora espresso una preferenza -->
<div class="row mb-4">
  <div class="col-md-2">
    <div class="card bg-light text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->no_iban_count; ?></h4>
        <small>Senza IBAN</small>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card bg-light text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->no_donation_count; ?></h4>
        <small>Senza Donazione</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-dark text-white text-center">
      <div class="card-body py-2">
        <h4 class="mb-0"><?php echo (int)$stats->to_contact_count; ?></h4>
        <small>Da Contattare (manca IBAN o donazione)</small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Bulk actions -->
<?php if (count($sellers) > 0): ?>
<form method="post" id="bulkForm">
  <?php csrf_field(); ?>
  <input type="hidden" name="action" value="mark_multiple_sent" id="bulkAction">

  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll()">
            <i class="fas fa-check-square"></i> Seleziona Tutti
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNotSent()">
            <i class="fas fa-clock"></i> Seleziona Non Inviati
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
            <i class="fas fa-square"></i> Deseleziona
          </button>
          <span class="ml-3 text-muted" id="selectedCount">0 selezionati</span>
        </div>
        <div>
          <button type="submit" class="btn btn-outline-success mr-2" onclick="return submitBulkMark();">
            <i class="fas fa-check"></i> Segna come Inviati (senza invio)
          </button>
          <button type="submit" class="btn btn-primary" onclick="return submitBulkSend();">
            <i class="fas fa-paper-plane"></i> Invia Email Selezionate
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Sellers table -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-users"></i> Venditori (<?php echo count($sellers); ?>)
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0" id="sellersTable">
        <thead class="thead-light">
          <tr>
            <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)"></th>
            <th>Venditore</th>
            <th>Email</th>
            <th>Pratiche</th>
            <th class="text-right">Dovuto</th>
            <th>Newsletter</th>
            <th>Preferenza</th>
            <th>IBAN</th>
            <th>Donazione</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sellers as $seller): ?>
            <tr class="<?php echo $seller->newsletter_sent ? '' : 'table-warning'; ?>" data-sent="<?php echo $seller->newsletter_sent ? '1' : '0'; ?>">
              <td>
                <input type="checkbox" name="refund_ids[]" value="<?php echo $seller->id; ?>" class="seller-checkbox" data-sent="<?php echo $seller->newsletter_sent ? '1' : '0'; ?>" onchange="updateSelectedCount()">
              </td>
              <td>
                <strong><?php echo esc_html($seller->last_name . ' ' . $seller->first_name); ?></strong>
              </td>
              <td>
                <a href="mailto:<?php echo esc_html($seller->email); ?>">
                  <small><?php echo esc_html($seller->email); ?></small>
                </a>
              </td>
              <td>
                <span class="badge badge-secondary"><?php echo esc_html($seller->pratica_numbers); ?></span>
              </td>
              <td class="text-right">
                <strong>&euro; <?php echo number_format((float)$seller->amount_owed, 2, ',', '.'); ?></strong>
              </td>
              <td>
                <?php if ($seller->newsletter_sent): ?>
                  <span class="badge badge-success" title="Inviata il <?php echo date('d/m/Y H:i', strtotime($seller->newsletter_sent_at)); ?> da <?php echo esc_html($seller->sender_first_name . ' ' . $seller->sender_last_name); ?>">
                    <i class="fas fa-check"></i> Inviata
                  </span>
                  <br>
                  <small class="text-muted"><?php echo date('d/m/Y', strtotime($seller->newsletter_sent_at)); ?></small>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fas fa-clock"></i> Non inviata</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($seller->payment_preference === 'cash'): ?>
                  <span class="badge badge-success"><i class="fas fa-money-bill-alt"></i> Contanti</span>
                <?php elseif ($seller->payment_preference === 'wire_transfer'): ?>
                  <span class="badge badge-primary"><i class="fas fa-university"></i> Bonifico</span>
                <?php else: ?>
                  <span class="badge badge-secondary"><i class="fas fa-question"></i> -</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$seller->has_iban === 1): ?>
                  <span class="badge badge-primary"><i class="fas fa-university"></i> S&igrave;</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fas fa-times"></i> No</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$seller->donate_books === 1): ?>
                  <span class="badge badge-success"><i class="fas fa-gift"></i> S&igrave;</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fas fa-times"></i> No</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <!-- Preview email -->
                  <button type="button" class="btn btn-outline-info" title="Anteprima Email" onclick="submitRowAction('preview_email', <?php echo $seller->id; ?>)">
                    <i class="fas fa-eye"></i>
                  </button>

                  <?php if (!$seller->newsletter_sent): ?>
                    <!-- Send email directly -->
                    <button type="button" class="btn btn-primary" title="Invia Email" onclick="if(confirm('Inviare l\'email a <?php echo esc_html($seller->email); ?>?')) submitRowAction('send_email', <?php echo $seller->id; ?>)">
                      <i class="fas fa-paper-plane"></i>
                    </button>
                    <!-- Mark as sent -->
                    <button type="button" class="btn btn-outline-success" title="Segna come Inviata (senza invio)" onclick="if(confirm('Segnare la newsletter come inviata senza inviarla?')) submitRowAction('mark_sent', <?php echo $seller->id; ?>)">
                      <i class="fas fa-check"></i>
                    </button>
                  <?php else: ?>
                    <!-- Reset status -->
                    <button type="button" class="btn btn-outline-warning" title="Reset (per re-invio)" onclick="if(confirm('Resettare lo stato per permettere un nuovo invio?')) submitRowAction('reset_status', <?php echo $seller->id; ?>)">
                      <i class="fas fa-undo"></i>
                    </button>
                  <?php endif; ?>

                  <!-- View refund details -->
                  <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo $seller->id; ?>" class="btn btn-outline-primary" title="Dettaglio Rimborso">
                    <i class="fas fa-euro-sign"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<!-- Hidden form for single row actions (outside bulkForm to avoid nested forms) -->
<form method="post" id="rowActionForm" style="display: none;">
  <?php csrf_field(); ?>
  <input type="hidden" name="action" id="rowActionType" value="">
  <input type="hidden" name="refund_id" id="rowActionRefundId" value="">
</form>
<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun venditore trovato con i filtri selezionati.
  </div>
<?php endif; ?>

<!-- Instructions -->
<div class="card mt-4">
  <div class="card-header bg-light">
    <i class="fas fa-info-circle"></i> Istruzioni per l'invio Newsletter
  </div>
  <div class="card-body">
    <ol>
      <li><strong>Prepara la lista</strong>: Filtra per "Newsletter: Non inviate" per vedere i venditori a cui non è stata ancora inviata la comunicazione.</li>
      <li><strong>Scrivi solo a chi serve</strong>: Spunta <strong>"Da contattare"</strong> per limitare l'elenco ai venditori che nel loro profilo non hanno l'IBAN <em>oppure</em> non hanno scelto di donare i libri invenduti: sono quelli a cui manca ancora una risposta. I filtri "IBAN" e "Donazione" ti permettono di isolare una sola delle due condizioni.</li>
      <li><strong>Anteprima</strong>: Clicca sull'icona <i class="fas fa-eye"></i> per vedere l'anteprima dell'email con il link personalizzato.</li>
      <li><strong>Invio singolo</strong>: Clicca su <i class="fas fa-paper-plane"></i> per inviare l'email direttamente al venditore.</li>
      <li><strong>Invio multiplo</strong>: Seleziona più venditori (o usa "Seleziona Non Inviati") e clicca su "Invia Email Selezionate".</li>
      <li><strong>Monitora</strong>: Usa i filtri per vedere chi ha risposto ("Preferenza: Impostata") e chi no ("Newsletter: Inviate" + "Preferenza: Non impostata").</li>
    </ol>
    <div class="alert alert-info mb-0">
      <i class="fas fa-info-circle"></i> <strong>Pulsanti:</strong>
      <ul class="mb-0 mt-2">
        <li><i class="fas fa-paper-plane text-primary"></i> <strong>Invia Email</strong>: invia l'email direttamente al venditore</li>
        <li><i class="fas fa-check text-success"></i> <strong>Segna come Inviata</strong>: segna come inviata senza inviare (se hai già inviato manualmente)</li>
        <li><i class="fas fa-undo text-warning"></i> <strong>Reset</strong>: resetta lo stato per permettere un nuovo invio</li>
      </ul>
    </div>
  </div>
</div>

<script>
// Store DataTable reference (declared early so functions can use it)
var sellersDataTable = null;

// Submit single row action using the hidden form (avoids nested forms issue)
function submitRowAction(action, refundId) {
  document.getElementById('rowActionType').value = action;
  document.getElementById('rowActionRefundId').value = refundId;
  document.getElementById('rowActionForm').submit();
}

function selectAll() {
  if (sellersDataTable) {
    sellersDataTable.$('.seller-checkbox').each(function() { this.checked = true; });
  } else {
    document.querySelectorAll('.seller-checkbox').forEach(cb => cb.checked = true);
  }
  var selectAllCb = document.getElementById('selectAllCheckbox');
  if (selectAllCb) selectAllCb.checked = true;
  updateSelectedCount();
}

function selectNotSent() {
  if (sellersDataTable) {
    sellersDataTable.$('.seller-checkbox').each(function() {
      this.checked = $(this).data('sent') === 0 || $(this).data('sent') === '0';
    });
  } else {
    document.querySelectorAll('.seller-checkbox').forEach(cb => {
      cb.checked = cb.dataset.sent === '0';
    });
  }
  updateSelectedCount();
}

function deselectAll() {
  if (sellersDataTable) {
    sellersDataTable.$('.seller-checkbox').each(function() { this.checked = false; });
  } else {
    document.querySelectorAll('.seller-checkbox').forEach(cb => cb.checked = false);
  }
  var selectAllCb = document.getElementById('selectAllCheckbox');
  if (selectAllCb) selectAllCb.checked = false;
  updateSelectedCount();
}

function toggleAll(checkbox) {
  if (sellersDataTable) {
    sellersDataTable.$('.seller-checkbox').each(function() { this.checked = checkbox.checked; });
  } else {
    document.querySelectorAll('.seller-checkbox').forEach(cb => cb.checked = checkbox.checked);
  }
  updateSelectedCount();
}

function updateSelectedCount() {
  var count = 0;
  if (sellersDataTable) {
    count = sellersDataTable.$('.seller-checkbox:checked').length;
  } else {
    count = document.querySelectorAll('.seller-checkbox:checked').length;
  }
  document.getElementById('selectedCount').textContent = count + ' selezionati';
}

// Get all checked checkboxes (including those on other DataTable pages)
function getCheckedCheckboxes() {
  if (sellersDataTable) {
    // DataTable is active - need to get checkboxes from all pages
    return sellersDataTable.$('.seller-checkbox:checked');
  } else {
    // No DataTable - standard DOM query
    return document.querySelectorAll('.seller-checkbox:checked');
  }
}

// Add hidden inputs for checked checkboxes before form submission
function addHiddenInputsForChecked(form) {
  // Remove any previously added hidden inputs
  form.querySelectorAll('input.bulk-hidden-id').forEach(el => el.remove());

  // Get all checked checkboxes (including from other DataTable pages)
  const checked = getCheckedCheckboxes();

  // Add hidden inputs for each checked checkbox
  checked.each ? checked.each(function() {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'refund_ids[]';
    hidden.value = this.value;
    hidden.className = 'bulk-hidden-id';
    form.appendChild(hidden);
  }) : checked.forEach(function(cb) {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'refund_ids[]';
    hidden.value = cb.value;
    hidden.className = 'bulk-hidden-id';
    form.appendChild(hidden);
  });

  return checked.length;
}

function submitBulkMark() {
  const form = document.getElementById('bulkForm');
  const count = addHiddenInputsForChecked(form);

  if (count === 0) {
    alert('Seleziona almeno un venditore.');
    return false;
  }
  if (!confirm('Segnare ' + count + ' newsletter come inviate (senza inviarle)?')) {
    return false;
  }
  document.getElementById('bulkAction').value = 'mark_multiple_sent';
  return true;
}

function submitBulkSend() {
  const form = document.getElementById('bulkForm');
  const count = addHiddenInputsForChecked(form);

  if (count === 0) {
    alert('Seleziona almeno un venditore.');
    return false;
  }

  // Count how many are not yet sent
  let notSentCount = 0;
  const checked = getCheckedCheckboxes();
  const checkFunc = function(cb) {
    if ((cb.dataset ? cb.dataset.sent : $(cb).data('sent')) === '0') notSentCount++;
  };
  checked.each ? checked.each(function() { checkFunc(this); }) : checked.forEach(checkFunc);

  if (notSentCount === 0) {
    alert('Tutti i venditori selezionati hanno già ricevuto l\'email. Usa "Reset" per permettere un nuovo invio.');
    return false;
  }

  if (!confirm('Inviare ' + notSentCount + ' email?\n\nNota: le email già inviate verranno saltate.')) {
    return false;
  }
  document.getElementById('bulkAction').value = 'send_multiple_emails';
  return true;
}

$(document).ready(function() {
  if ($('#sellersTable tbody tr').length > 10) {
    sellersDataTable = $('#sellersTable').DataTable({
      pageLength: 25,
      order: [[1, 'asc']],
      columnDefs: [
        { orderable: false, targets: [0, 9] }
      ],
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Italian.json'
      }
    });
  }
});
</script>
