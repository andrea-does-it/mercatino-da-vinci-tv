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
      <label for="isbn-file">Oppure carica file CSV (colonna 'isbn'):</label>
      <input type="file" id="isbn-file" class="form-control-file" accept=".csv">
    </div>
    <button id="btn-verify" class="btn btn-primary">Verifica</button>
  </div>
</div>

<div id="results-section" style="display:none;">
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">Risultati Verifica</h5>
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

  // Load CSV file
  $('#isbn-file').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
      const csv = event.target.result;
      const lines = csv.trim().split('\n');
      let isbns = [];

      // Parser CSV standard: separatore virgola, campi opzionalmente racchiusi tra
      // doppi apici (con "" come apice interno). Trova la colonna 'isbn' dall'intestazione.
      const parseLine = function(line) {
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
      };

      if (lines.length > 0) {
        const header = parseLine(lines[0].replace(/\r$/, '')).map(c => c.trim().toLowerCase());
        let isbnCol = header.indexOf('isbn');
        let startIdx = 1;          // salta l'intestazione
        if (isbnCol === -1) {       // nessuna intestazione: prima colonna = ISBN
          isbnCol = 0;
          startIdx = 0;
        }

        for (let i = startIdx; i < lines.length; i++) {
          const line = lines[i].replace(/\r$/, '').trim();
          if (line === '') continue;
          const cols = parseLine(line);
          const isbn = (cols[isbnCol] || '').replace(/[^0-9Xx]/g, ''); // tiene solo cifre/X
          if (isbn) isbns.push(isbn);
        }
      }

      $('#isbn-textarea').val(isbns.join('\n'));
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

      let disabled = '';
      let checked = '';
      if (item.error) {
        disabled = 'disabled';
      } else if (!item.exists) {
        checked = 'checked';
      }

      let categoryOptions = '<option value="0">-- Seleziona categoria --</option>';
      const categories = <?php echo json_encode($categories); ?>;
      categories.forEach(function(cat) {
        categoryOptions += '<option value="' + cat.id + '">' + esc_html(cat.name) + '</option>';
      });

      const row = '<tr>' +
        '<td>' + coverHtml + '</td>' +
        '<td>' + esc_html(item.title) + warnings + '</td>' +
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
