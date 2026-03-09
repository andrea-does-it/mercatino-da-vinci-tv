<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }
?>

<h1><i class="fas fa-question-circle text-info"></i> Guida - Newsletter Preferenze Pagamento</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-newsletter&year=<?php echo date('Y'); ?>" class="btn btn-secondary mb-4">
  <i class="fas fa-arrow-left"></i> Torna a Newsletter
</a>

<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <i class="fas fa-book-open"></i> Panoramica
  </div>
  <div class="card-body">
    <p>
      La pagina <strong>Newsletter Preferenze Pagamento</strong> permette di inviare a ciascun venditore
      un'email personalizzata contenente un link per indicare la propria preferenza di pagamento
      (contanti o bonifico bancario).
    </p>
    <p>
      Ogni venditore riceve un link univoco con scadenza. Cliccandolo, accede a una pagina dove
      pu&ograve; scegliere come vuole essere rimborsato e, nel caso di bonifico, fornire il proprio IBAN.
    </p>
  </div>
</div>

<!-- Section: Statistics -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-chart-pie"></i> Statistiche</div>
  <div class="card-body">
    <p>I sei riquadri in alto mostrano:</p>
    <table class="table table-sm table-bordered">
      <tr>
        <td class="bg-primary text-white text-center" style="width: 180px;">Totale</td>
        <td>Numero totale di venditori con un record di rimborso per l'anno</td>
      </tr>
      <tr>
        <td class="bg-success text-white text-center">Email Inviate</td>
        <td>Quanti venditori hanno gi&agrave; ricevuto l'email</td>
      </tr>
      <tr>
        <td class="bg-warning text-center">Da Inviare</td>
        <td>Quanti venditori non hanno ancora ricevuto l'email</td>
      </tr>
      <tr>
        <td class="bg-info text-white text-center">Con Preferenza</td>
        <td>Quanti venditori hanno gi&agrave; scelto la modalit&agrave; di pagamento</td>
      </tr>
      <tr>
        <td class="bg-secondary text-white text-center">Senza Preferenza</td>
        <td>Quanti venditori non hanno ancora risposto</td>
      </tr>
      <tr>
        <td class="bg-danger text-white text-center">Inviate Senza Risposta</td>
        <td>Venditori che hanno ricevuto l'email ma non hanno ancora indicato la preferenza (da sollecitare)</td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Filters -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter"></i> Filtri</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr>
        <td><strong>Anno</strong></td>
        <td>Seleziona l'anno scolastico di riferimento</td>
      </tr>
      <tr>
        <td><strong>Newsletter</strong></td>
        <td>
          <ul class="mb-0">
            <li><strong>Inviate</strong> &mdash; mostra solo i venditori che hanno gi&agrave; ricevuto l'email</li>
            <li><strong>Non inviate</strong> &mdash; mostra solo quelli a cui non &egrave; ancora stata inviata</li>
          </ul>
        </td>
      </tr>
      <tr>
        <td><strong>Preferenza</strong></td>
        <td>
          <ul class="mb-0">
            <li><strong>Impostata</strong> &mdash; il venditore ha gi&agrave; scelto contanti o bonifico</li>
            <li><strong>Non impostata</strong> &mdash; il venditore non ha ancora risposto</li>
          </ul>
        </td>
      </tr>
    </table>
    <p>
      <strong>Combinazione utile:</strong> filtra per "Newsletter: Inviate" + "Preferenza: Non impostata"
      per trovare i venditori da sollecitare.
    </p>
  </div>
</div>

<!-- Section: Single Actions -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-cogs"></i> Azioni per singolo venditore</div>
  <div class="card-body">
    <p>Per ogni riga della tabella, i pulsanti disponibili sono:</p>
    <table class="table table-sm table-bordered">
      <tr>
        <td style="width: 50px;" class="text-center"><span class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></span></td>
        <td>
          <strong>Anteprima email</strong> &mdash; Mostra il testo completo dell'email che verrebbe inviata,
          incluso il link personalizzato. Dall'anteprima puoi decidere se inviare o solo segnare come inviata.
        </td>
      </tr>
      <tr>
        <td class="text-center"><span class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i></span></td>
        <td>
          <strong>Invia email</strong> &mdash; Invia immediatamente l'email al venditore via SMTP.
          Dopo l'invio, il record viene automaticamente segnato come "inviata" con data e operatore.
          <br><em>Visibile solo se l'email non &egrave; stata ancora inviata.</em>
        </td>
      </tr>
      <tr>
        <td class="text-center"><span class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i></span></td>
        <td>
          <strong>Segna come inviata (senza invio)</strong> &mdash; Utile se hai inviato l'email manualmente
          (es. da un altro client di posta) e vuoi solo aggiornare lo stato nel sistema.
          <br><em>Visibile solo se l'email non &egrave; stata ancora inviata.</em>
        </td>
      </tr>
      <tr>
        <td class="text-center"><span class="btn btn-sm btn-outline-warning"><i class="fas fa-undo"></i></span></td>
        <td>
          <strong>Reset</strong> &mdash; Resetta lo stato della newsletter per permettere un nuovo invio.
          Utile se il venditore non ha ricevuto l'email o se vuoi inviarla di nuovo.
          <br><em>Visibile solo se l'email &egrave; gi&agrave; stata segnata come inviata.</em>
        </td>
      </tr>
      <tr>
        <td class="text-center"><span class="btn btn-sm btn-outline-primary"><i class="fas fa-euro-sign"></i></span></td>
        <td>
          <strong>Dettaglio rimborso</strong> &mdash; Apre la scheda completa del venditore con
          l'elenco dei libri venduti, lo storico pagamenti e le opzioni di gestione.
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Bulk Actions -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-tasks"></i> Azioni di massa</div>
  <div class="card-body">
    <p>Per operazioni su pi&ugrave; venditori contemporaneamente:</p>

    <h6>Selezione</h6>
    <table class="table table-sm table-bordered">
      <tr>
        <td><strong>Seleziona Tutti</strong></td>
        <td>Seleziona tutte le righe (anche quelle su altre pagine della tabella)</td>
      </tr>
      <tr>
        <td><strong>Seleziona Non Inviati</strong></td>
        <td>Seleziona automaticamente solo i venditori a cui l'email non &egrave; ancora stata inviata</td>
      </tr>
      <tr>
        <td><strong>Deseleziona</strong></td>
        <td>Rimuove la selezione da tutti</td>
      </tr>
    </table>

    <h6 class="mt-3">Azioni</h6>
    <table class="table table-sm table-bordered">
      <tr>
        <td><span class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i> Segna come Inviati</span></td>
        <td>Segna tutti i selezionati come "email inviata" senza effettivamente inviare nulla</td>
      </tr>
      <tr>
        <td><span class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Invia Email Selezionate</span></td>
        <td>
          Invia l'email a tutti i venditori selezionati che non l'hanno ancora ricevuta.
          I venditori gi&agrave; segnati come "inviata" vengono automaticamente saltati.
        </td>
      </tr>
    </table>

    <div class="alert alert-warning mb-0">
      <i class="fas fa-exclamation-triangle"></i>
      <strong>Attenzione:</strong> l'invio di massa pu&ograve; richiedere alcuni minuti se ci sono molti venditori.
      Non chiudere la pagina durante l'operazione.
    </div>
  </div>
</div>

<!-- Section: Table Colors -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-palette"></i> Codice colori della tabella</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr class="table-warning">
        <td style="width: 200px;"><strong>Riga gialla</strong></td>
        <td>Email non ancora inviata &mdash; richiede azione</td>
      </tr>
      <tr>
        <td><strong>Riga bianca</strong></td>
        <td>Email gi&agrave; inviata</td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Workflow -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-project-diagram"></i> Flusso di lavoro consigliato</div>
  <div class="card-body">
    <ol>
      <li>
        <strong>Verifica i record</strong> &mdash; Assicurati che i record di rimborso siano stati creati
        dalla pagina <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds">Gestione Rimborsi</a>.
      </li>
      <li>
        <strong>Anteprima</strong> &mdash; Clicca <i class="fas fa-eye"></i> su qualche venditore per
        verificare che il testo dell'email e il link siano corretti.
      </li>
      <li>
        <strong>Invio di prova</strong> &mdash; Invia l'email a un singolo venditore (magari a te stesso)
        per verificare la ricezione e il funzionamento del link.
      </li>
      <li>
        <strong>Invio di massa</strong> &mdash; Usa "Seleziona Non Inviati" e poi "Invia Email Selezionate"
        per inviare a tutti i venditori rimasti.
      </li>
      <li>
        <strong>Monitoraggio</strong> &mdash; Nei giorni successivi, torna su questa pagina per verificare
        chi ha risposto (filtro "Preferenza: Impostata") e chi no.
      </li>
      <li>
        <strong>Sollecito</strong> &mdash; Per i venditori che non hanno risposto, puoi resettare lo stato
        e inviare nuovamente l'email.
      </li>
    </ol>
  </div>
</div>

<!-- Section: Technical Notes -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-info-circle"></i> Note tecniche</div>
  <div class="card-body">
    <ul>
      <li>Le email vengono inviate tramite il server SMTP configurato nelle impostazioni del sito.</li>
      <li>Il link di preferenza ha una <strong>scadenza</strong>; dopo la scadenza, il venditore non potr&agrave;
          pi&ugrave; accedere alla pagina. In tal caso, genera un nuovo link dalla pagina Rimborsi.</li>
      <li>L'email viene inviata all'indirizzo registrato nel profilo del venditore.
          Se l'indirizzo &egrave; errato, il venditore deve aggiornarlo dal proprio profilo.</li>
    </ul>
  </div>
</div>
