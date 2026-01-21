<?php

global $alertMsg;
$cssClass = 'hidden';
$msgHeading = '';
$msgBody = '';

$alertMsg = $alertMsg == '' ? (isset($_GET['msg']) ? esc_html($_GET['msg']) : '') : $alertMsg;

if ($alertMsg != '') {

  switch($alertMsg) {

    case 'created':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Inserimento riuscito';
      break;

    case 'order_sent':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Ordine inviato correttamente';
      break;

    case 'order_accepted':
      $cssClass = 'alert-success';
      $msgHeading = 'Pratica Accettata';
      $msgBody = 'La pratica è stata accettata e l\'etichetta è pronta per la stampa';
      break;

    case 'registered':
      $cssClass = 'alert-success';
      $msgHeading = 'Registrazione avvenuta';
      $msgBody = 'Ora è possibile effettuare il login';
      break;

    case 'login_for_checkout':
      $cssClass = 'alert-success';
      $msgHeading = 'EFFETTUARE IL LOGIN';
      $msgBody = 'Effettuare il login o registrarsi per poter inviare ordine';
      break;

    case 'add_to_cart':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Aggiunto al carrello';
      break;

    case 'order_shipped':
      $cssClass = 'alert-success';
      $msgHeading = 'Ordine Spedito';
      $msgBody = 'Email inviata al cliente';
      break;
      
    case 'updated':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Modifica riuscita';
      break;

    case 'deleted':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Eliminazione riuscita';
      break;

    case 'err':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Qualcosa è andato storto';
      break;

    case 'address_not_found':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Indirizzo di spedizione non presente. Correggere anagrafica';
      break;

    case 'login_err':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE DI ACCESSO';
      $msgBody = 'Email o password non corrette. Verifica i tuoi dati e riprova.';
      break;      

    case 'user_not_found':
      $cssClass = 'alert-danger';
      $msgHeading = 'UTENTE NON TROVATO';
      $msgBody = 'L\'email inserita non è registrata nel sistema.';
      break;

    case 'invalid_credentials':
      $cssClass = 'alert-danger';
      $msgHeading = 'CREDENZIALI NON VALIDE';
      $msgBody = 'La password inserita non è corretta.';
      break;      

    case 'forbidden':
      $cssClass = 'alert-danger';
      $msgHeading = 'PAGINA RISERVATA';
      $msgBody = 'Non disponi dei privilegi necessari';
      break;

    case 'mandatory_fields':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Compilare i campi obbligatori';
      break;

    case 'not_found':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Elemento non presente';
      break;

    case 'invalid_password':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'la password non è abbastanza robusta';
      break; 

    case 'invalid_email':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'la mail non è valida';
      break; 

    case 'email_not_exists':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'la mail non è registrata a sistema';
      break; 

    case 'passwords_not_match':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Le password non corrispondono';
      break; 

    case 'user_already_exists':
      $cssClass = 'alert-danger';
      $msgHeading = 'EMAIL GIÀ REGISTRATA';
      $msgBody = 'Questa email è già presente nel sistema. Se hai già un account, <a href="'.ROOT_URL.'auth?page=login" class="alert-link">effettua il login</a> o <a href="'.ROOT_URL.'auth?page=forgot-password" class="alert-link">recupera la password</a>.';
      break;

    case 'cart_empty':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Il carrello è vuoto';
      break;

    case 'order_empty':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = "L'ordine non contiene alcun elemento";
      break;

    case 'expired_cart':
      $cssClass = 'alert-warning';
      $msgHeading = 'Attenzione';
      $msgBody = 'Essendo trascorsi oltre 30 minuti di inattività, il carrello è stato svuotato.';
      break;

    case 'product_unavailable':
      $cssClass = 'alert-warning';
      $msgHeading = 'Attenzione';
      $msgBody = 'Questo libro non può essere aggiunto al carrello.';
      break;

    case 'order_quantity_resored':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Quantità ordine ripristinata in stock.';
      break;

    case 'password_updated':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Password aggiornata correttamente.';
      break;

    case 'invalid_iban':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'Il codice IBAN inserito non è valido. Verifica di averlo digitato correttamente.';
      break;

    case 'iban_owner_required':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE';
      $msgBody = 'L\'intestatario del conto è obbligatorio quando si inserisce un IBAN.';
      break;

    case 'iban_deleted':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Codice IBAN rimosso correttamente.';
      break;

    case 'csrf_error':
      $cssClass = 'alert-danger';
      $msgHeading = 'ERRORE DI SICUREZZA';
      $msgBody = 'Token di sicurezza non valido. Riprova.';
      break;

    case 'privacy_required':
      $cssClass = 'alert-danger';
      $msgHeading = 'CONSENSO RICHIESTO';
      $msgBody = 'È necessario accettare l\'informativa sulla privacy per completare la registrazione.';
      break;

    case 'consent_updated':
      $cssClass = 'alert-success';
      $msgHeading = 'OK';
      $msgBody = 'Preferenze aggiornate correttamente.';
      break;

    case 'confirm_deletion_required':
      $cssClass = 'alert-warning';
      $msgHeading = 'CONFERMA RICHIESTA';
      $msgBody = 'Devi confermare di voler cancellare il tuo account selezionando la casella di conferma.';
      break;

    case 'account_deleted':
      $cssClass = 'alert-success';
      $msgHeading = 'ACCOUNT CANCELLATO';
      $msgBody = 'Il tuo account è stato cancellato correttamente. Tutti i tuoi dati personali sono stati eliminati.';
      break;

  }

}
?>

<div class="alert alert-dismissible <?php echo $cssClass; ?>">
  <button type="button" class="close" data-dismiss="alert">&times;</button>
  <h4 class="alert-heading"><?php echo $msgHeading; ?></h4>
  <p class="mb-0"><?php echo $msgBody; ?></p>
</div>