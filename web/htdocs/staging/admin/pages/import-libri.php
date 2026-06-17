<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}

$catMgr = new CategoryManager();
$categories = $catMgr->GetCategories();
?>

<a href="<?php echo ROOT_URL . 'admin/?page=dashboard'; ?>" class="back underline">&laquo; Torna al cruscotto</a>

<h1>Importa Libri</h1>

<div class="card mb-3 mt-3">
  <div class="card-body">
    <h5 class="card-title">Inserisci ISBN</h5>
    <div class="form-group">
      <label for="isbn-textarea">ISBN (uno per riga):</label>
      <textarea id="isbn-textarea" class="form-control" rows="6" placeholder="9788812345678&#10;9788887654321"></textarea>
    </div>
    <div class="form-group">
      <label for="isbn-file">Oppure carica file CSV (separatore virgola). Se contiene le colonne titolo/prezzo, la tabella viene compilata direttamente dal file; se contiene solo gli ISBN, premi "Verifica" per recuperare i dati da Libraccio.</label>
      <input type="file" id="isbn-file" class="form-control-file" accept=".csv">
    </div>
    <div id="csv-info" class="alert alert-info" style="display:none;"></div>
    <button id="btn-verify" class="btn btn-primary">Verifica</button>
  </div>
</div>

<div class="card mb-3 mt-3">
  <div class="card-body">
    <h5 class="card-title">Libri attualmente visibili nello shop (nascosto = 0) <span id="visible-count" class="badge badge-secondary"></span></h5>
    <p class="text-muted mb-2">Anteprima dei libri ora visibili. Se hai già caricato/verificato un elenco, quelli che verrebbero <strong>nascosti</strong> dalla sincronizzazione sono evidenziati in rosso.</p>
    <button id="btn-show-visible" class="btn btn-info mb-2">Mostra libri attualmente visibili</button>
    <table id="visible-table" class="table table-sm table-hover" style="display:none;">
      <thead>
        <tr><th>ISBN</th><th>Titolo</th><th>Categoria</th><th>Qta</th><th>Esito sincronizzazione</th></tr>
      </thead>
      <tbody id="visible-tbody"></tbody>
    </table>
  </div>
</div>

<div id="results-section" style="display:none;">
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">Risultati Verifica <span id="results-count" class="badge badge-secondary"></span></h5>
      <table id="results-table" class="table table-hover">
        <thead>
          <tr>
            <th>Copertina</th>
            <th>Titolo</th>
            <th>Autori</th>
            <th>Editore</th>
            <th>Prezzo Listino</th>
            <th>Prezzo Mercatino</th>
            <th>Stato</th>
            <th>Categoria</th>
            <th>Importa</th>
          </tr>
        </thead>
        <tbody id="results-tbody">
        </tbody>
      </table>
    </div>
  </div>

  <div class="form-group">
    <button id="btn-import" class="btn btn-success">Importa selezionati</button>
    <button id="btn-export" class="btn btn-secondary">Esporta CSV</button>
    <button id="btn-sync-visibility" class="btn btn-warning">Nascondi dalla vendita i libri non in questa lista</button>
  </div>

  <div id="import-progress" style="display:none;" class="alert alert-info">
    Importazione in corso...
  </div>

  <div id="import-results" style="display:none;" class="alert">
    <h6>Risultati Importazione</h6>
    <div id="import-results-content"></div>
  </div>
</div>

<script>
$(document).ready(function() {
  let verifiedItems = [];

  // Get CSRF token
  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  // Parser di una riga CSV standard: separatore virgola, campi opzionalmente
  // racchiusi tra doppi apici (con "" come apice interno).
  function parseCsvLine(line) {
    const out = [];
    let cur = '', inQ = false;
    for (let k = 0; k < line.length; k++) {
      const ch = line[k];
      if (ch === '"') {
        if (inQ && line[k + 1] === '"') { cur += '"'; k++; }
        else { inQ = !inQ; }
      } else if (ch === ',' && !inQ) {
        out.push(cur); cur = '';
      } else {
        cur += ch;
      }
    }
    out.push(cur);
    return out;
  }

  // Mappa la materia AIE (es. "STORIA DELL'ARTE - CORSI") al nome della categoria
  // del sito. L'ordine dei controlli e' significativo (es. ARTE prima di STORIA,
  // SCIENZE MOTORIE prima di SCIENZE, GEOSTORIA prima di STORIA).
  function materiaToCategoria(materia) {
    const m = (materia || '').toUpperCase();
    if (m.indexOf('ARTE') !== -1) return 'Arte';
    if (m.indexOf('DIRITTO') !== -1) return 'Diritto';
    if (m.indexOf('DISCIPLINE SPORTIVE') !== -1) return 'Discipline Sportive';
    if (m.indexOf('FILOSOFIA') !== -1) return 'Filosofia';
    if (m.indexOf('FISICA') !== -1) return 'Fisica';
    if (m.indexOf('GEOSTORIA') !== -1) return 'Geostoria';
    if (m.indexOf('INFORMATICA') !== -1 || m.indexOf('TECNOLOGIE INFORMATICHE') !== -1) return 'Informatica';
    if (m.indexOf('INGLESE') !== -1) return 'Inglese';
    if (m.indexOf('ITALIANO') !== -1) return 'Italiano';
    if (m.indexOf('LATINO') !== -1) return 'Latino';
    if (m.indexOf('MATEMATICA') !== -1) return 'Matematica';
    if (m.indexOf('SCIENZE MOTORIE') !== -1 || m.indexOf('MOTORIA') !== -1) return 'Motoria';
    if (m.indexOf('SCIENZE') !== -1 || m.indexOf('BIOLOGIA') !== -1 || m.indexOf('CHIMICA') !== -1) return 'Scienze';
    if (m.indexOf('STORIA') !== -1) return 'Storia';
    return '';
  }

  // Converte una stringa numerica (anche con virgola decimale) in numero o null.
  function toNum(v) {
    if (v === undefined || v === null) return null;
    v = String(v).trim().replace(',', '.');
    if (v === '') return null;
    const n = parseFloat(v);
    return isNaN(n) ? null : n;
  }

  // Verifica in blocco la presenza a DB degli ISBN già caricati in verifiedItems,
  // poi mostra la tabella (i dati provengono dal CSV, niente lookup Libraccio).
  function checkExistenceAndDisplay() {
    const isbns = verifiedItems.map(function(i) { return i.isbn; });
    $.ajax({
      url: '<?php echo ROOT_URL; ?>api/admin/import-libri.php',
      method: 'POST',
      dataType: 'json',
      data: { op: 'check', isbns: JSON.stringify(isbns), csrf_token: getCsrfToken() },
      success: function(resp) {
        if (resp && resp.results) {
          const map = {};
          resp.results.forEach(function(r) { map[r.isbn] = r; });
          verifiedItems.forEach(function(it) {
            const m = map[it.isbn];
            if (m) { it.exists = m.exists; it.existing_id = m.existing_id; }
          });
        }
        displayResults();
      },
      error: function() { displayResults(); }
    });
  }

  // Load CSV file
  $('#isbn-file').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
      const csv = event.target.result;
      const lines = csv.trim().split('\n');
      if (lines.length === 0) return;

      const header = parseCsvLine(lines[0].replace(/\r$/, '')).map(function(c) { return c.trim().toLowerCase(); });
      const colIdx = function(names) {
        for (let n = 0; n < names.length; n++) {
          const i = header.indexOf(names[n]);
          if (i !== -1) return i;
        }
        return -1;
      };

      let isbnCol = colIdx(['isbn']);
      let startIdx = 1;            // salta l'intestazione
      if (isbnCol === -1) {        // nessuna intestazione riconosciuta: prima colonna = ISBN
        isbnCol = 0;
        startIdx = 0;
      }

      // Colonne dati (nomi alternativi gestiti)
      const cTitle = colIdx(['titolo', 'titolo_libraccio', 'titolo_manoscritto', 'title', 'name']);
      const cAuthors = colIdx(['autori', 'authors']);
      const cPublisher = colIdx(['editore', 'publisher']);
      const cListPrice = colIdx(['prezzo_listino', 'list_price']);
      const cMerc = colIdx(['prezzo_mercatino']);
      const cMateria = colIdx(['materia']);
      // CSV "ricco" = ha intestazione e una colonna titolo: usiamo direttamente i dati del file
      const rich = (startIdx === 1 && cTitle !== -1);

      const items = [];
      const isbnsOnly = [];
      let dataLines = 0;   // righe dati non vuote presenti nel file
      for (let i = startIdx; i < lines.length; i++) {
        const line = lines[i].replace(/\r$/, '').trim();
        if (line === '') continue;
        dataLines++;
        const cols = parseCsvLine(line);
        const isbn = (cols[isbnCol] || '').replace(/[^0-9Xx]/g, '');
        if (!isbn) continue;
        if (rich) {
          const title = cTitle !== -1 ? (cols[cTitle] || '').trim() : '';
          if (title === '') continue;   // salta righe senza titolo (es. righe vuote finali)
          const lp = cListPrice !== -1 ? toNum(cols[cListPrice]) : null;
          let pm = cMerc !== -1 ? toNum(cols[cMerc]) : null;
          if (pm === null && lp !== null) pm = Math.round((lp / 2 - 1.50) * 100) / 100;
          isbnsOnly.push(isbn);
          items.push({
            isbn: isbn,
            title: title,
            authors: cAuthors !== -1 ? (cols[cAuthors] || '') : '',
            publisher: cPublisher !== -1 ? (cols[cPublisher] || '') : '',
            list_price: lp,
            prezzo_mercatino: pm,
            materia: cMateria !== -1 ? (cols[cMateria] || '') : '',
            cover_url: 'https://www.libraccio.it/images/' + isbn + '_0_500_0_75.jpg',
            exists: false,
            existing_id: null,
            warnings: []
          });
        } else {
          isbnsOnly.push(isbn);
        }
      }

      $('#isbn-textarea').val(isbnsOnly.join('\n'));

      // Conteggio di controllo: righe lette dal file vs caricate
      const loaded = rich ? items.length : isbnsOnly.length;
      const skipped = dataLines - loaded;
      let info = 'File: <strong>' + dataLines + '</strong> righe dati · caricate <strong>' + loaded + '</strong>';
      if (skipped > 0) {
        info += ' · <span class="text-danger">' + skipped + ' scartate</span> (ISBN o titolo mancante)';
      }
      if (!rich) {
        info += ' — CSV con soli ISBN: premi "Verifica" per recuperare i dati.';
      }
      $('#csv-info').html(info).show();

      if (rich && items.length > 0) {
        // Mostra subito la tabella dai dati del CSV (con verifica presenza a DB),
        // senza dover interrogare Libraccio.
        verifiedItems = items;
        checkExistenceAndDisplay();
      }
    };
    reader.readAsText(file);
  });

  // Verify ISBNs
  $('#btn-verify').on('click', function() {
    const text = $('#isbn-textarea').val().trim();
    if (!text) {
      alert('Inserisci almeno un ISBN');
      return;
    }

    const isbns = text.split('\n').map(line => line.trim()).filter(line => line !== '');
    verifiedItems = [];

    const csrfToken = getCsrfToken();
    let completed = 0;

    isbns.forEach(function(isbn) {
      $.ajax({
        url: '<?php echo ROOT_URL; ?>api/admin/import-libri.php',
        method: 'POST',
        dataType: 'json',
        data: {
          op: 'lookup',
          isbn: isbn,
          csrf_token: csrfToken
        },
        success: function(resp) {
          if (resp.error) {
            verifiedItems.push({
              isbn: isbn,
              title: 'ERRORE',
              authors: '',
              publisher: '',
              list_price: null,
              prezzo_mercatino: null,
              cover_url: '',
              exists: false,
              existing_id: null,
              error: resp.error,
              warnings: []
            });
          } else {
            verifiedItems.push(resp);
          }
          completed++;
          if (completed === isbns.length) {
            displayResults();
          }
        },
        error: function() {
          verifiedItems.push({
            isbn: isbn,
            title: 'ERRORE',
            authors: '',
            publisher: '',
            list_price: null,
            prezzo_mercatino: null,
            cover_url: '',
            exists: false,
            existing_id: null,
            error: 'Errore rete',
            warnings: []
          });
          completed++;
          if (completed === isbns.length) {
            displayResults();
          }
        }
      });
    });
  });

  // Display results
  function displayResults() {
    const tbody = $('#results-tbody');
    tbody.empty();

    verifiedItems.forEach(function(item, idx) {
      let statusHtml = '';
      if (item.error) {
        statusHtml = '<span class="badge badge-danger">ERRORE</span><br>' + esc_html(item.error);
      } else if (item.exists && item.existing_id) {
        statusHtml = '<span class="badge badge-warning">Già a DB</span><br>' +
          '<a href="' + escAttr('<?php echo ROOT_URL; ?>admin/?page=product&id=' + item.existing_id) + '" target="_blank">Vedi</a>';
      } else {
        statusHtml = '<span class="badge badge-success">Nuovo</span>';
      }

      let coverHtml = '';
      if (item.cover_url) {
        coverHtml = '<img src="' + escAttr(item.cover_url) + '" style="max-width:50px; max-height:80px;" alt="Cover">';
      } else {
        coverHtml = '<img src="../images/noimage.jpg" style="max-width:50px;" alt="No cover">';
      }

      let warnings = '';
      if (item.warnings && item.warnings.length > 0) {
        warnings = '<br><small class="text-muted">' + item.warnings.join(', ') + '</small>';
      }

      let materiaHtml = '';
      if (item.materia) {
        materiaHtml = '<br><small class="text-muted">Materia: ' + esc_html(item.materia) + '</small>';
      }

      let disabled = '';
      let checked = '';
      if (item.error) {
        disabled = 'disabled';
      } else if (!item.exists) {
        checked = 'checked';
      }

      let categoryOptions = '<option value="0">-- Seleziona categoria --</option>';
      const categories = <?php echo json_encode($categories); ?>;
      const targetCat = materiaToCategoria(item.materia);  // nome categoria sito mappato dalla materia
      categories.forEach(function(cat) {
        const sel = (targetCat && cat.name && cat.name.toLowerCase() === targetCat.toLowerCase()) ? ' selected' : '';
        categoryOptions += '<option value="' + cat.id + '"' + sel + '>' + esc_html(cat.name) + '</option>';
      });

      const row = '<tr>' +
        '<td>' + coverHtml + '</td>' +
        '<td>' + esc_html(item.title) + materiaHtml + warnings + '</td>' +
        '<td>' + esc_html(item.authors) + '</td>' +
        '<td>' + esc_html(item.publisher) + '</td>' +
        '<td>€ ' + (item.list_price !== null ? item.list_price.toFixed(2) : '-') + '</td>' +
        '<td><input type="number" class="form-control form-control-sm prezzo-mercatino" value="' +
          (item.prezzo_mercatino !== null ? item.prezzo_mercatino.toFixed(2) : '') + '" step="0.01" style="width:100px;"></td>' +
        '<td>' + statusHtml + '</td>' +
        '<td><select class="form-control form-control-sm categoria-select" ' + disabled + '>' + categoryOptions + '</select></td>' +
        '<td><input type="checkbox" class="import-checkbox" data-index="' + idx + '" ' + disabled + ' ' + checked + '></td>' +
        '</tr>';

      tbody.append(row);
    });

    // Conteggio totale con dettaglio (confrontabile col contenuto del file)
    const total = verifiedItems.length;
    const nuovi = verifiedItems.filter(function(i) { return !i.error && !i.exists; }).length;
    const giaDB = verifiedItems.filter(function(i) { return !i.error && i.exists; }).length;
    const errori = verifiedItems.filter(function(i) { return i.error; }).length;
    let countTxt = total + ' libri';
    const extra = [];
    if (nuovi) extra.push(nuovi + ' nuovi');
    if (giaDB) extra.push(giaDB + ' già a DB');
    if (errori) extra.push(errori + ' errori');
    if (extra.length) countTxt += ' (' + extra.join(', ') + ')';
    $('#results-count').text(countTxt);

    $('#results-section').show();
  }

  // Import selected
  $('#btn-import').on('click', function() {
    const itemsToImport = [];

    $('.import-checkbox:checked').each(function() {
      const idx = parseInt($(this).attr('data-index'));
      const item = verifiedItems[idx];
      const row = $(this).closest('tr');

      const prezzo_mercatino = parseFloat(row.find('.prezzo-mercatino').val()) || null;
      const category_id = parseInt(row.find('.categoria-select').val()) || 0;

      if (category_id === 0) {
        alert('Seleziona una categoria per ISBN ' + item.isbn);
        return false;
      }

      itemsToImport.push({
        isbn: item.isbn,
        name: item.title,
        authors: item.authors,
        publisher: item.publisher,
        list_price: item.list_price,
        prezzo_mercatino: prezzo_mercatino !== null ? prezzo_mercatino : item.prezzo_mercatino,
        category_id: category_id,
        qta: 0
      });
    });

    if (itemsToImport.length === 0) {
      alert('Seleziona almeno un libro da importare');
      return;
    }

    const csrfToken = getCsrfToken();

    $('#import-progress').show();
    $('#import-results').hide();

    $.ajax({
      url: '<?php echo ROOT_URL; ?>api/admin/import-libri.php',
      method: 'POST',
      dataType: 'json',
      data: {
        op: 'import',
        items: JSON.stringify(itemsToImport),
        csrf_token: csrfToken
      },
      success: function(resp) {
        $('#import-progress').hide();
        let resultHtml = '';

        if (resp.results && Array.isArray(resp.results)) {
          resp.results.forEach(function(res) {
            let statusClass = 'success';
            let statusText = '';

            if (res.error) {
              statusClass = 'danger';
              statusText = 'ERRORE: ' + res.error;
            } else if (res.skipped) {
              statusClass = 'warning';
              statusText = 'SALTATO (già esistente, ID: ' + res.product_id + ')';
            } else {
              statusText = 'OK (ID: ' + res.product_id + ', Cover: ' + (res.cover ? 'Sì' : 'No') + ')';
            }

            resultHtml += '<div class="alert alert-' + statusClass + '">' +
              '<strong>' + esc_html(res.isbn) + '</strong>: ' + statusText +
              '</div>';
          });
        }

        $('#import-results-content').html(resultHtml);
        $('#import-results').show();
      },
      error: function() {
        $('#import-progress').hide();
        $('#import-results').html('<div class="alert alert-danger">Errore durante l\'importazione</div>').show();
      }
    });
  });

  // Mostra i libri attualmente visibili (nascosto=0); se c'è un elenco caricato,
  // evidenzia quali verrebbero nascosti dalla sincronizzazione.
  $('#btn-show-visible').on('click', function() {
    const isbns = verifiedItems.filter(function(i) { return !i.error; }).map(function(i) { return i.isbn; });
    const $btn = $(this);
    $btn.prop('disabled', true);
    $.ajax({
      url: '<?php echo ROOT_URL; ?>api/admin/import-libri.php',
      method: 'POST',
      dataType: 'json',
      data: { op: 'visible_products', isbns: JSON.stringify(isbns), csrf_token: getCsrfToken() },
      success: function(resp) {
        $btn.prop('disabled', false);
        const rows = (resp && resp.results) ? resp.results : [];
        const hasList = resp && resp.has_list;
        const tbody = $('#visible-tbody');
        tbody.empty();
        let hideCount = 0;
        rows.forEach(function(r) {
          let esito = '-';
          if (hasList) {
            esito = r.would_hide
              ? '<span class="badge badge-danger">verrà nascosto</span>'
              : '<span class="badge badge-success">resta visibile</span>';
          }
          if (r.would_hide) hideCount++;
          const trClass = r.would_hide ? ' class="table-danger"' : '';
          tbody.append('<tr' + trClass + '>' +
            '<td>' + esc_html(r.isbn) + '</td>' +
            '<td>' + esc_html(r.name) + '</td>' +
            '<td>' + esc_html(r.category) + '</td>' +
            '<td>' + r.qta + '</td>' +
            '<td>' + esito + '</td>' +
            '</tr>');
        });
        let badge = rows.length + ' visibili';
        if (hasList) badge += ' · ' + hideCount + ' verranno nascosti';
        $('#visible-count').text(badge);
        $('#visible-table').show();
      },
      error: function() {
        $btn.prop('disabled', false);
        alert('Errore nel recupero dei libri visibili');
      }
    });
  });

  // Sincronizza visibilità: nasconde dallo shop i libri non presenti in questo elenco
  $('#btn-sync-visibility').on('click', function() {
    const isbns = verifiedItems.filter(function(i) { return !i.error; }).map(function(i) { return i.isbn; });
    if (isbns.length === 0) {
      alert('Nessun libro valido in elenco.');
      return;
    }
    if (!confirm('Verranno NASCOSTI dalla vendita tutti i libri del catalogo il cui ISBN non è tra i ' +
        isbns.length + ' di questo elenco, e resi visibili quelli presenti.\n\nProcedere?')) {
      return;
    }
    const csrfToken = getCsrfToken();
    const $btn = $(this);
    $btn.prop('disabled', true);
    $.ajax({
      url: '<?php echo ROOT_URL; ?>api/admin/import-libri.php',
      method: 'POST',
      dataType: 'json',
      data: { op: 'sync_visibility', isbns: JSON.stringify(isbns), csrf_token: csrfToken },
      success: function(resp) {
        $btn.prop('disabled', false);
        if (resp && resp.error) {
          alert('Errore: ' + resp.error);
          return;
        }
        alert('Visibilità aggiornata.\n' +
          'Visibili nello shop: ' + resp.visibili + '\n' +
          'Nascosti: ' + resp.nascosti + '\n' +
          'Totale catalogo: ' + resp.totale + ' (elenco: ' + resp.in_elenco + ' ISBN)');
      },
      error: function() {
        $btn.prop('disabled', false);
        alert('Errore durante la sincronizzazione della visibilità');
      }
    });
  });

  // Export CSV
  $('#btn-export').on('click', function() {
    let csv = 'isbn,titolo,autori,editore,prezzo_listino,prezzo_mercatino,stato\n';

    verifiedItems.forEach(function(item) {
      const stato = item.error ? 'ERRORE' : (item.exists ? 'Esiste' : 'Nuovo');
      csv += '"' + escCsv(item.isbn) + '",' +
        '"' + escCsv(item.title) + '",' +
        '"' + escCsv(item.authors) + '",' +
        '"' + escCsv(item.publisher) + '",' +
        (item.list_price !== null ? item.list_price.toFixed(2) : '') + ',' +
        (item.prezzo_mercatino !== null ? item.prezzo_mercatino.toFixed(2) : '') + ',' +
        '"' + stato + '"\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.setAttribute('href', URL.createObjectURL(blob));
    link.setAttribute('download', 'import-libri-' + new Date().getTime() + '.csv');
    link.click();
  });

  // Utility functions
  function esc_html(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function escAttr(str) {
    if (!str) return '';
    return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function escCsv(str) {
    if (!str) return '';
    str = String(str).replace(/"/g, '""');
    // CSV formula injection protection: prepend apostrophe if starts with special chars
    if (/^[=+@\-\t\r]/.test(str)) {
      str = "'" + str;
    }
    return str;
  }
});
</script>
