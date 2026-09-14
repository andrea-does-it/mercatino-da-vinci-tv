<?php
/**
 * Payment Preference Landing Page
 * Sellers access this page via a secure token link sent in newsletter
 * to express their preference for refund payment method (cash or wire transfer)
 */
require_once 'inc/init.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$success = false;
$error = '';
$refund = null;

$sellerRefundMgr = new SellerRefundManager();

// Validate token
if (empty($token)) {
    $error = 'link_invalid';
} else {
    $refund = $sellerRefundMgr->getByToken($token);
    if (!$refund) {
        $error = 'link_expired';
    }
}

// Check if user has unsold books (status 'vendere')
$hasUnsoldBooks = false;
$unsoldBooksCount = 0;
if ($refund) {
    $hasUnsoldBooks = $sellerRefundMgr->userHasUnsoldBooks($refund->user_id);
    if ($hasUnsoldBooks) {
        $unsoldBooksCount = $sellerRefundMgr->getUnsoldBooksCount($refund->user_id);
    }
}

// Existing bank details: when an IBAN is already on file the refund can only be
// a wire transfer, so the seller may edit the IBAN but not switch back to cash.
// The donation, once given, cannot be taken back from this page.
// Both locks are enforced server-side below: disabled inputs are not submitted,
// so the posted values can never be trusted to carry them.
$existingIban = null;
$hasIban = false;
$donationLocked = false;
if ($refund) {
    $userMgr = new UserManager();
    $existingIban = $userMgr->getIBAN($refund->user_id);
    $hasIban = ($existingIban !== null && !empty($existingIban['iban']));
    $donationLocked = !empty($refund->donate_unsold);
}

// Handle form submission
if ($refund && isset($_POST['submit_preference'])) {
    // Captured before the save: the refund is re-read afterwards (and the CSRF
    // branch never sets them), but the activity log below always needs them.
    $sellerUserId = $refund->user_id;
    $refundId = $refund->id;
    $refundYear = $refund->year;
    $preference = '';
    $donateUnsold = null;
    $ibanProvided = false;

    if (!CSRF::validateToken()) {
        $error = 'csrf_error';
    } else {
        $preference = isset($_POST['payment_preference']) ? $_POST['payment_preference'] : '';

        // Get optional fields
        $donateUnsold = isset($_POST['donate_unsold']) ? 1 : 0;
        $sellerNotes = isset($_POST['seller_notes']) ? trim($_POST['seller_notes']) : null;

        // An IBAN on file forces the wire transfer: the radios are disabled in the
        // form (so they submit nothing) and a forged post must not switch to cash.
        if ($hasIban) {
            $preference = 'wire_transfer';
        }

        // Only allow donate_unsold if user actually has unsold books
        if (!$hasUnsoldBooks) {
            $donateUnsold = null;
        } elseif ($donationLocked) {
            // The donation cannot be withdrawn from this page: a disabled checkbox
            // submits nothing, which would otherwise silently reset it to 0.
            $donateUnsold = 1;
        }

        if (!in_array($preference, ['cash', 'wire_transfer'])) {
            $error = 'invalid_preference';
        } elseif ($preference === 'wire_transfer') {
            // Validate IBAN and owner name for wire transfer
            $iban = isset($_POST['iban']) ? strtoupper(preg_replace('/\s+/', '', $_POST['iban'])) : '';
            $ibanOwnerName = isset($_POST['iban_owner_name']) ? trim($_POST['iban_owner_name']) : '';
            $ibanProvided = ($iban !== '');

            if (empty($iban)) {
                $error = 'iban_required';
            } elseif (strlen($iban) < 15 || strlen($iban) > 34) {
                $error = 'iban_invalid';
            } elseif (empty($ibanOwnerName)) {
                $error = 'iban_owner_required';
            } else {
                // Save preference with IBAN and extra fields
                if ($sellerRefundMgr->setPaymentPreference($token, $preference, $iban, $ibanOwnerName, $donateUnsold, $sellerNotes)) {
                    $success = true;
                } else {
                    $error = 'save_error';
                }
            }
        } else {
            // Cash payment - no IBAN needed
            if ($sellerRefundMgr->setPaymentPreference($token, $preference, null, null, $donateUnsold, $sellerNotes)) {
                $success = true;
            } else {
                $error = 'save_error';
            }
        }
    }

    // Refresh refund data (and the derived locks) after save
    if ($success) {
        $refund = $sellerRefundMgr->getByToken($token);
        if ($refund) {
            $existingIban = $userMgr->getIBAN($refund->user_id);
            $hasIban = ($existingIban !== null && !empty($existingIban['iban']));
            $donationLocked = !empty($refund->donate_unsold);
        }
    }

    // Registro attività: la risposta del venditore arriva da questa pagina pubblica
    // (autenticata dal token, quindi senza $loggedInUser) e va attribuita al venditore.
    // Nel dettaglio va solo contesto NON personale: si registra se un IBAN è stato
    // fornito, mai il suo valore (è cifrato ovunque altrove).
    $logDetail = 'year: ' . (int)$refundYear
        . ', refund_id: ' . (int)$refundId
        . ', preference: ' . (in_array($preference, ['cash', 'wire_transfer'], true) ? $preference : '-')
        . ', donation: ' . ($donateUnsold === null ? 'n/d' : (int)$donateUnsold)
        . ', iban: ' . ($ibanProvided ? 'fornito' : 'no');

    if ($success) {
        log_activity($sellerUserId, 'payment_preference_set', $logDetail);
    } else {
        log_activity($sellerUserId, 'payment_preference_error',
            $logDetail . ', error: ' . ($error !== '' ? $error : 'unknown'));
    }
}

// Error messages
$errorMessages = [
    'link_invalid' => 'Il link non è valido. Contatta il Comitato per assistenza.',
    'link_expired' => 'Il link è scaduto o non è più valido. Contatta il Comitato per ricevere un nuovo link.',
    'csrf_error' => 'Errore di sicurezza. Riprova.',
    'invalid_preference' => 'Seleziona una modalità di pagamento valida.',
    'iban_required' => 'L\'IBAN è obbligatorio per il bonifico bancario.',
    'iban_invalid' => 'L\'IBAN inserito non è valido. Deve contenere tra 15 e 34 caratteri.',
    'iban_owner_required' => 'L\'intestatario del conto è obbligatorio.',
    'save_error' => 'Errore durante il salvataggio. Riprova più tardi.'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferenza Pagamento - Mercatino del Libro</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .preference-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .card {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .preference-option {
            padding: 20px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .preference-option:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .preference-option.selected {
            border-color: #007bff;
            background-color: #e7f1ff;
        }
        .preference-option input[type="radio"] {
            margin-right: 10px;
        }
        /* Choice locked by an IBAN already on file: visible but not selectable */
        .preference-option.option-locked {
            cursor: not-allowed;
            opacity: 0.65;
        }
        .preference-option.option-locked:hover {
            border-color: #dee2e6;
            background-color: transparent;
        }
        .preference-option.option-locked.selected {
            opacity: 1;
            border-color: #007bff;
            background-color: #e7f1ff;
        }
        .preference-option.option-locked.selected:hover {
            border-color: #007bff;
            background-color: #e7f1ff;
        }
        .iban-section {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background-color: #fff3cd;
            border-radius: 8px;
        }
        .iban-section.visible {
            display: block;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="preference-container">
        <div class="text-center mb-4">
            <img src="<?php echo ROOT_URL; ?>assets/img/logo.png" alt="Logo" style="max-height: 80px;" onerror="this.style.display='none'">
            <h2 class="mt-3">Mercatino del Libro</h2>
        </div>

        <?php if ($error && isset($errorMessages[$error])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo esc_html($errorMessages[$error]); ?>
            </div>
            <?php if ($error === 'link_invalid' || $error === 'link_expired'): ?>
                <div class="text-center mt-4">
                    <a href="<?php echo ROOT_URL; ?>" class="btn btn-primary">Torna alla Homepage</a>
                </div>
                <?php $refund = null; // Don't show form ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-check-circle"></i> Preferenza Salvata</h4>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-check-circle success-icon mb-3"></i>
                    <h4>Grazie, <?php echo esc_html($refund->first_name); ?>!</h4>
                    <p class="lead">La tua preferenza è stata registrata con successo.</p>

                    <div class="alert alert-info mt-4">
                        <strong>Riepilogo:</strong><br>
                        Anno: <strong><?php echo esc_html($refund->year); ?></strong><br>
                        Modalità scelta: <strong><?php echo $refund->payment_preference === 'cash' ? 'Contanti' : 'Bonifico Bancario'; ?></strong>
                        <?php if ($refund->payment_preference === 'wire_transfer' && $refund->iban_owner_name): ?>
                            <br>Intestatario: <strong><?php echo esc_html($refund->iban_owner_name); ?></strong>
                        <?php endif; ?>
                        <?php if ($refund->donate_unsold): ?>
                            <br><i class="fas fa-heart text-danger"></i> Hai scelto di donare i libri invenduti
                        <?php endif; ?>
                    </div>

                    <p class="text-muted mt-4">
                        <small>Riceverai il rimborso secondo le modalità indicate quando sarà completata la rendicontazione delle vendite.</small>
                    </p>
                </div>
            </div>
        <?php elseif ($refund): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-money-bill-wave"></i> Preferenza Pagamento <?php echo esc_html($refund->year); ?></h4>
                </div>
                <div class="card-body">
                    <p class="lead">Ciao <strong><?php echo esc_html($refund->first_name . ' ' . $refund->last_name); ?></strong>,</p>
                    <p>Seleziona come preferisci ricevere il rimborso per i libri venduti nell'anno <?php echo esc_html($refund->year); ?>.</p>

                    <?php if ($refund->preference_set_at): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Hai già espresso una preferenza il <?php echo date('d/m/Y H:i', strtotime($refund->preference_set_at)); ?>.
                            Puoi modificarla compilando nuovamente il modulo.
                        </div>
                    <?php endif; ?>

                    <form method="post" id="preferenceForm">
                        <?php csrf_field(); ?>

                        <?php if ($hasIban): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-university"></i>
                                Hai gi&agrave; registrato un IBAN, quindi il rimborso avverr&agrave; tramite
                                <strong>bonifico bancario</strong>. Puoi aggiornare qui sotto l'IBAN e
                                l'intestatario del conto.
                            </div>
                        <?php endif; ?>

                        <div class="preference-option <?php echo (!$hasIban && $refund->payment_preference === 'cash') ? 'selected' : ''; ?> <?php echo $hasIban ? 'option-locked' : ''; ?>"
                             onclick="selectPreference('cash')">
                            <label class="mb-0 d-flex align-items-center" style="cursor: <?php echo $hasIban ? 'not-allowed' : 'pointer'; ?>;">
                                <input type="radio" name="payment_preference" value="cash" id="pref_cash"
                                       <?php echo (!$hasIban && $refund->payment_preference === 'cash') ? 'checked' : ''; ?>
                                       <?php echo $hasIban ? 'disabled' : ''; ?>>
                                <div>
                                    <strong><i class="fas fa-money-bill-alt text-success"></i> Contanti</strong>
                                    <p class="mb-0 text-muted"><small>Ritiro presso la sede del Comitato durante gli orari di apertura</small></p>
                                </div>
                            </label>
                        </div>

                        <div class="preference-option <?php echo ($hasIban || $refund->payment_preference === 'wire_transfer') ? 'selected' : ''; ?> <?php echo $hasIban ? 'option-locked' : ''; ?>"
                             onclick="selectPreference('wire_transfer')">
                            <label class="mb-0 d-flex align-items-center" style="cursor: <?php echo $hasIban ? 'not-allowed' : 'pointer'; ?>;">
                                <input type="radio" name="payment_preference" value="wire_transfer" id="pref_wire"
                                       <?php echo ($hasIban || $refund->payment_preference === 'wire_transfer') ? 'checked' : ''; ?>
                                       <?php echo $hasIban ? 'disabled' : ''; ?>>
                                <div>
                                    <strong><i class="fas fa-university text-primary"></i> Bonifico Bancario</strong>
                                    <p class="mb-0 text-muted"><small>Accredito diretto sul tuo conto corrente</small></p>
                                </div>
                            </label>
                        </div>

                        <div class="iban-section <?php echo ($hasIban || $refund->payment_preference === 'wire_transfer') ? 'visible' : ''; ?>" id="ibanSection">
                            <h5><i class="fas fa-university"></i> Dati Bancari</h5>
                            <p class="text-muted"><small>Inserisci i dati del conto su cui ricevere il bonifico</small></p>

                            <div class="form-group">
                                <label for="iban"><strong>IBAN *</strong></label>
                                <input type="text" name="iban" id="iban" class="form-control"
                                       placeholder="IT60X0542811101000000123456"
                                       value="<?php echo isset($_POST['iban']) ? esc_html($_POST['iban']) : esc_html($existingIban['iban'] ?? ''); ?>"
                                       maxlength="34"
                                       style="text-transform: uppercase; font-family: monospace;">
                                <small class="form-text text-muted">L'IBAN italiano inizia con IT e contiene 27 caratteri</small>
                            </div>

                            <div class="form-group">
                                <label for="iban_owner_name"><strong>Intestatario del Conto *</strong></label>
                                <input type="text" name="iban_owner_name" id="iban_owner_name" class="form-control"
                                       placeholder="Nome e Cognome dell'intestatario"
                                       value="<?php echo isset($_POST['iban_owner_name']) ? esc_html($_POST['iban_owner_name']) : esc_html($existingIban['iban_owner_name'] ?? $refund->iban_owner_name ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Donate unsold books section -->
                        <?php if ($hasUnsoldBooks): ?>
                        <div class="mt-4 p-3 border rounded" style="background-color: #f8f9fa;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="donate_unsold" name="donate_unsold" value="1"
                                       <?php echo ($donationLocked || isset($_POST['donate_unsold']) || $refund->donate_unsold) ? 'checked' : ''; ?>
                                       <?php echo $donationLocked ? 'disabled' : ''; ?>>
                                <label class="custom-control-label" for="donate_unsold" style="cursor: <?php echo $donationLocked ? 'not-allowed' : 'pointer'; ?>;">
                                    <strong><i class="fas fa-heart text-danger"></i> Desidero donare i libri invenduti alla Libreria</strong>
                                </label>
                            </div>
                            <p class="text-muted mb-0 mt-2">
                                <small>Hai attualmente <strong><?php echo $unsoldBooksCount; ?></strong> libr<?php echo $unsoldBooksCount == 1 ? 'o' : 'i'; ?> ancora in vendita. Se non vendut<?php echo $unsoldBooksCount == 1 ? 'o' : 'i'; ?> entro la fine dell'anno, <?php echo $unsoldBooksCount == 1 ? 'verrà donato' : 'verranno donati'; ?> alla Libreria invece di essere restituiti.</small>
                            </p>
                            <?php if ($donationLocked): ?>
                                <p class="mb-0 mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-lock"></i> Hai gi&agrave; confermato la donazione: da questa pagina
                                        non &egrave; pi&ugrave; possibile annullarla. Se hai cambiato idea, contatta il Comitato.
                                    </small>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Seller notes section -->
                        <div class="form-group mt-4">
                            <label for="seller_notes"><strong><i class="fas fa-comment"></i> Note per il Comitato</strong> <small class="text-muted">(facoltativo)</small></label>
                            <textarea name="seller_notes" id="seller_notes" class="form-control" rows="3"
                                      placeholder="Eventuali comunicazioni o richieste per il Comitato..."><?php echo isset($_POST['seller_notes']) ? esc_html($_POST['seller_notes']) : esc_html($refund->seller_notes ?? ''); ?></textarea>
                            <small class="form-text text-muted">Queste note saranno visibili ai gestori della Libreria</small>
                        </div>

                        <button type="submit" name="submit_preference" class="btn btn-primary btn-lg btn-block mt-4">
                            <i class="fas fa-check"></i> Conferma Preferenza
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted">
                    <small>
                        <i class="fas fa-lock"></i> I tuoi dati sono trattati in modo sicuro secondo la nostra
                        <a href="<?php echo ROOT_URL; ?>public/?page=privacy" target="_blank">Privacy Policy</a>
                    </small>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPreference(value) {
            var radio = document.getElementById('pref_' + (value === 'cash' ? 'cash' : 'wire'));

            // Locked choice (an IBAN is already on file): the refund can only be a
            // wire transfer, so clicking the rows must not change anything.
            if (!radio || radio.disabled) {
                return;
            }

            // Update radio button
            radio.checked = true;

            // Update visual selection
            document.querySelectorAll('.preference-option').forEach(function(el) {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show/hide IBAN section
            var ibanSection = document.getElementById('ibanSection');
            if (value === 'wire_transfer') {
                ibanSection.classList.add('visible');
            } else {
                ibanSection.classList.remove('visible');
            }
        }

        // Format IBAN as user types (add spaces every 4 characters for readability)
        document.getElementById('iban')?.addEventListener('input', function(e) {
            // Remove all non-alphanumeric characters
            var value = this.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
            this.value = value;
        });
    </script>
</body>
</html>
