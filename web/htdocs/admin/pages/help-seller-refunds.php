<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }
?>

<h1><i class="fas fa-question-circle text-info"></i> Guida - Gestione Rimborsi Venditori</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds" class="btn btn-secondary mb-4">
  <i class="fas fa-arrow-left"></i> Torna a Rimborsi Venditori
</a>

<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <i class="fas fa-book-open"></i> Panoramica
  </div>
  <div class="card-body">
    <p>
      La pagina <strong>Gestione Rimborsi Venditori</strong> serve a gestire i pagamenti dovuti
      ai genitori che hanno venduto libri tramite il mercatino.
    </p>
    <p>
      Al termine del periodo di vendita, per ogni venditore che ha venduto almeno un libro,
      viene creato un <em>record di rimborso</em> che tiene traccia di:
      quanto gli spetta, come preferisce essere pagato, quanto &egrave; gi&agrave; stato pagato
      e lo stato complessivo del rimborso.
    </p>
  </div>
</div>

<!-- Section: Year Selector & Filters -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter"></i> Anno e Filtri</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr>
        <td><strong>Anno</strong></td>
        <td>
          Seleziona l'anno scolastico di riferimento. I rimborsi sono organizzati per anno.
          Il cambio anno aggiorna automaticamente l'elenco.
        </td>
      </tr>
      <tr>
        <td><strong>Stato</strong></td>
        <td>
          Filtra per stato del rimborso:
          <ul class="mb-0">
            <li><span class="badge badge-warning">In attesa</span> &mdash; nessun pagamento ancora effettuato</li>
            <li><span class="badge badge-info">Parziale</span> &mdash; pagato solo in parte</li>
            <li><span class="badge badge-success">Completato</span> &mdash; tutto l'importo &egrave; stato pagato</li>
          </ul>
        </td>
      </tr>
      <tr>
        <td><strong>Preferenza</strong></td>
        <td>
          Filtra per preferenza di pagamento del venditore:
          <ul class="mb-0">
            <li><span class="badge badge-success"><i class="fas fa-money-bill-alt"></i> Contanti</span> &mdash; ritiro in contanti con busta</li>
            <li><span class="badge badge-primary"><i class="fas fa-university"></i> Bonifico</span> &mdash; bonifico bancario sull'IBAN indicato</li>
          </ul>
        </td>
      </tr>
    </table>
    <p>Premi <strong>Reset</strong> per rimuovere i filtri.</p>
  </div>
</div>

<!-- Section: Summary Cards -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-chart-bar"></i> Riquadri di Riepilogo</div>
  <div class="card-body">
    <p>I quattro riquadri in alto mostrano:</p>
    <table class="table table-sm table-bordered">
      <tr>
        <td class="bg-primary text-white text-center" style="width: 160px;">Venditori Totali</td>
        <td>Numero di venditori con almeno un libro venduto nell'anno selezionato</td>
      </tr>
      <tr>
        <td class="bg-warning text-center">Da Pagare</td>
        <td>Somma degli importi ancora da rimborsare (dovuto &minus; gi&agrave; pagato)</td>
      </tr>
      <tr>
        <td class="bg-success text-white text-center">Gi&agrave; Pagato</td>
        <td>Somma totale dei rimborsi gi&agrave; effettuati</td>
      </tr>
      <tr>
        <td class="bg-info text-white text-center">Senza Preferenza</td>
        <td>Venditori che non hanno ancora indicato come vogliono essere pagati</td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Create Records -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-plus"></i> Creazione Record di Rimborso</div>
  <div class="card-body">
    <p>
      Il pulsante <strong><i class="fas fa-plus"></i> Crea Record per N Venditori</strong> compare quando
      ci sono venditori con libri venduti per i quali non &egrave; ancora stato creato un record di rimborso.
    </p>
    <p>Per ogni venditore viene creato automaticamente un record con:</p>
    <ul>
      <li>L'importo dovuto calcolato dai libri effettivamente venduti</li>
      <li>Stato iniziale: <span class="badge badge-warning">In attesa</span></li>
      <li>Preferenza di pagamento: non impostata (il venditore la sceglier&agrave; via newsletter)</li>
    </ul>
    <div class="alert alert-info mb-0">
      <i class="fas fa-info-circle"></i>
      La creazione &egrave; sicura: se un venditore ha gi&agrave; un record per l'anno selezionato,
      non viene duplicato.
    </div>
  </div>
</div>

<!-- Section: Refunds Table -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-list"></i> Tabella Rimborsi</div>
  <div class="card-body">
    <p>L'elenco mostra per ogni venditore:</p>
    <ul>
      <li><strong>Venditore</strong> &mdash; cognome e nome</li>
      <li><strong>Email</strong> &mdash; indirizzo email del genitore</li>
      <li><strong>Pratiche</strong> &mdash; numero di pratiche associate (una per ogni consegna di libri)</li>
      <li><strong>Dovuto</strong> &mdash; importo totale spettante al venditore</li>
      <li><strong>Pagato</strong> &mdash; quanto &egrave; gi&agrave; stato rimborsato</li>
      <li><strong>Preferenza</strong> &mdash; contanti o bonifico</li>
      <li><strong>Stato</strong> &mdash; stato del rimborso</li>
    </ul>
    <p>La tabella supporta ordinamento e ricerca tramite DataTables.</p>
  </div>
</div>

<!-- Section: Actions -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-cogs"></i> Azioni disponibili</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr>
        <td style="width: 50px;" class="text-center"><span class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></span></td>
        <td>
          <strong>Dettaglio rimborso</strong> &mdash; Apre la scheda completa del venditore con:
          elenco libri venduti, storico pagamenti, impostazione preferenza,
          gestione busta contanti, note e azioni di pagamento.
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- Section: Navigation -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-directions"></i> Navigazione</div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tr>
        <td><span class="btn btn-sm btn-info"><i class="fas fa-envelope"></i> Gestione Newsletter</span></td>
        <td>
          Vai alla pagina per inviare le email ai venditori chiedendo la preferenza di pagamento.
          Da usare dopo aver creato i record di rimborso.
        </td>
      </tr>
      <tr>
        <td><span class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Report</span></td>
        <td>
          Genera un report esportabile con il riepilogo di tutti i rimborsi dell'anno.
        </td>
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
        <strong>Fine vendite</strong> &mdash; Quando il periodo di vendita &egrave; concluso,
        vai su questa pagina e clicca <em>"Crea Record"</em> per generare i rimborsi.
      </li>
      <li>
        <strong>Invio newsletter</strong> &mdash; Vai su <em>Gestione Newsletter</em> per inviare
        a ciascun venditore l'email con il link per scegliere la modalit&agrave; di pagamento.
      </li>
      <li>
        <strong>Raccogli preferenze</strong> &mdash; Attendi che i venditori rispondano.
        Monitora dalla newsletter chi ha gi&agrave; risposto e chi no.
      </li>
      <li>
        <strong>Prepara i pagamenti</strong>:
        <ul>
          <li><strong>Contanti</strong>: nella scheda del venditore, prepara la busta e segna l'importo</li>
          <li><strong>Bonifico</strong>: usa l'IBAN fornito dal venditore per disporre il pagamento</li>
        </ul>
      </li>
      <li>
        <strong>Registra i pagamenti</strong> &mdash; Nella scheda di ogni venditore, registra
        il pagamento effettuato. Lo stato passer&agrave; automaticamente a "Completato".
      </li>
    </ol>
  </div>
</div>
