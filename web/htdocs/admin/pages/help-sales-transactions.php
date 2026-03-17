<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }
?>

<h1><i class="fas fa-question-circle text-info"></i> Guida - Gestione Vendite</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary mb-4">
  <i class="fas fa-arrow-left"></i> Torna a Gestione Vendite
</a>

<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <i class="fas fa-book-open"></i> Panoramica
  </div>
  <div class="card-body">
    <p>
      La pagina <strong>Gestione Vendite</strong> &egrave; il registro di cassa del mercatino.
      Ogni volta che un acquirente compra uno o pi&ugrave; libri, viene creata una <em>transazione di vendita</em>
      che registra: il metodo di pagamento, l'operatore, gli articoli venduti e il totale incassato.
    </p>
    <p>
      La transazione aggiorna automaticamente lo stato dei libri da <span class="badge badge-success">da vendere</span>
      a <span class="badge badge-primary">venduto</span>.
    </p>
  </div>
</div>

<!-- Section: Daily Summary -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-chart-line"></i> Riepilogo Giornaliero</div>
  <div class="card-body">
    <p>
      In cima alla pagina trovi il <strong>riepilogo delle vendite di oggi</strong>, suddiviso per metodo di pagamento:
    </p>
    <ul>
      <li><span class="badge badge-success">Contanti</span> &mdash; pagamenti in denaro contante</li>
      <li><span class="badge badge-primary">POS</span> &mdash; pagamenti con carta tramite terminale POS</li>
      <li><span class="badge badge-warning">Satispay</span> &mdash; pagamenti tramite app Satispay</li>
      <li><span class="badge badge-info">PayPal</span> &mdash; pagamenti tramite conto PayPal</li>
    </ul>
    <p>
      I totali si aggiornano automaticamente ad ogni nuova vendita o rimborso.
    </p>
  </div>
</div>

<!-- Section: Filters -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter"></i> Filtri</div>
  <div class="card-body">
    <p>Puoi filtrare l'elenco delle transazioni usando tre criteri:</p>
    <table class="table table-sm table-bordered">
      <tr>
        <td><strong>Metodo pagamento</strong></td>
        <td>Mostra solo le transazioni con un certo metodo (Contanti, POS, Satispay, PayPal)</td>
      </tr>
      <tr>
        <td><strong>Dal / Al</strong></td>
        <td>Filtra per intervallo di date. Utile per verificare gli incassi di un periodo specifico.</td>
      </tr>
    </table>
    <p>
      Quando i filtri sono attivi, compare un riquadro <em>Totali Periodo Filtrato</em> con la somma
      delle transazioni visibili, suddivisa per metodo di pagamento.
    </p>
    <p>Premi <strong>Reset</strong> per rimuovere tutti i filtri.</p>
  </div>
</div>

<!-- Section: New Sale -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-plus"></i> Nuova Vendita</div>
  <div class="card-body">
    <p>Per registrare una nuova vendita:</p>
    <ol>
      <li>Clicca il pulsante verde <strong><i class="fas fa-plus"></i> Nuova Vendita</strong></li>
      <li>Cerca i libri per <strong>ISBN</strong>, <strong>titolo</strong> o <strong>numero pratica</strong></li>
      <li>Clicca <strong>Aggiungi</strong> per inserire ogni libro nel carrello della vendita</li>
      <li>Seleziona il <strong>metodo di pagamento</strong></li>
      <li>Opzionalmente inserisci una <strong>descrizione</strong> (es. nome dell'acquirente)</li>
      <li>Conferma la vendita</li>
    </ol>
    <div class="alert alert-warning mb-0">
      <i class="fas fa-exclamation-triangle"></i>
      Vengono mostrati solo i libri in stato <em>"da vendere"</em> con un numero di pratica valido.
    </div>
  </div>
</div>

<!-- Section: Transaction List -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-list"></i> Elenco Transazioni</div>
  <div class="card-body">
    <p>La tabella mostra tutte le transazioni registrate con:</p>
    <ul>
      <li><strong>#</strong> &mdash; numero identificativo della transazione</li>
      <li><strong>Data/Ora</strong> &mdash; quando &egrave; stata registrata</li>
      <li><strong>Metodo Pagamento</strong> &mdash; come ha pagato l'acquirente</li>
      <li><strong>Descrizione</strong> &mdash; nota libera (es. nome acquirente)</li>
      <li><strong>N. Articoli</strong> &mdash; quanti libri sono inclusi</li>
      <li><strong>Totale</strong> &mdash; importo incassato</li>
      <li><strong>Operatore</strong> &mdash; chi ha registrato la vendita</li>
    </ul>
  </div>
</div>

<!-- Section: Actions -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-cogs"></i> Azioni disponibili</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr>
        <td style="width: 50px;" class="text-center"><span class="btn btn-sm btn-info"><i class="fas fa-eye"></i></span></td>
        <td>
          <strong>Visualizza dettaglio</strong> &mdash; Apre la pagina di dettaglio della transazione
          con l'elenco completo degli articoli venduti, i prezzi base e di vendita, e il margine del comitato.
        </td>
      </tr>
      <tr>
        <td class="text-center"><span class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></span></td>
        <td>
          <strong>Elimina transazione</strong> &mdash; Elimina la transazione dal registro.
          <strong class="text-danger">Attenzione:</strong> questa azione elimina solo il record della transazione,
          <u>non</u> riporta i libri in stato "da vendere". Per rimborsare, usa il pulsante apposito nel dettaglio.
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Refund -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-undo"></i> Rimborsi</div>
  <div class="card-body">
    <p>Nella pagina di <strong>dettaglio vendita</strong> puoi effettuare due tipi di rimborso:</p>

    <h6 class="mt-3">Rimborso singolo articolo</h6>
    <p>
      Clicca <span class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> Rimborsa</span> accanto a un articolo.
      Si apre una finestra in cui puoi inserire una <strong>nota di rimborso</strong> (facoltativa).
      Il libro torna in stato "da vendere" e il totale della transazione viene ricalcolato.
    </p>

    <h6 class="mt-3">Rimborso completo</h6>
    <p>
      Clicca <span class="btn btn-sm btn-danger"><i class="fas fa-undo"></i> Rimborsa Tutta la Vendita</span>
      in fondo alla pagina. Tutti i libri tornano disponibili e la transazione viene eliminata.
      Anche in questo caso puoi inserire una nota che verr&agrave; salvata su ciascun articolo.
    </p>

    <div class="alert alert-info mb-0">
      <i class="fas fa-info-circle"></i>
      La nota di rimborso viene salvata sull'articolo e rimane visibile come riferimento per gli operatori.
    </div>
  </div>
</div>

<!-- Section: Prices -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-euro-sign"></i> Come vengono calcolati i prezzi</div>
  <div class="card-body">
    <p>Per ogni libro venduto nel mercatino:</p>
    <ul>
      <li><strong>Prezzo base (venditore)</strong> = 50% del prezzo di copertina &minus; deduzione venditore</li>
      <li><strong>Prezzo vendita (acquirente)</strong> = prezzo base + ricarico totale (deduzione venditore + maggiorazione acquirente)</li>
      <li><strong>Margine comitato</strong> = differenza tra prezzo vendita e prezzo base = ricarico totale &times; numero libri</li>
    </ul>
    <p>
      I parametri di ricarico sono configurabili dalla pagina
      <a href="<?php echo ROOT_URL; ?>admin/?page=site_utils&tab=settings">Utilit&agrave; Sito &gt; Impostazioni</a>.
    </p>
  </div>
</div>
