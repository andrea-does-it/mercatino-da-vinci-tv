# Design — Email Ordini (mail merge da lista ordini) in Utilità Sito

**Data:** 2026-07-06
**Stato:** approvato (brainstorming concluso)

## Obiettivo

Aggiungere ad `admin/?page=site_utils` una funzione per inviare email ai venditori a
partire da una lista di ordini (pratiche), selezionati tramite filtri (es. pratiche
inviate ma non ancora consegnate al mercatino, oppure pratiche contenenti un certo
libro). L'admin seleziona gli ordini, vede l'email di ciascuno, compone oggetto e corpo
con segnaposto (mail merge) partendo da template gestibili, e invia in blocco con
barra di avanzamento.

## Decisioni prese

| Decisione | Scelta |
|---|---|
| Filtri lista ordini | Tutti e quattro: stato+anno, contiene libro, query SQL, elenco pratiche incollato |
| Venditore con più pratiche | Una email per ordine (i segnaposto si riferiscono alla singola pratica) |
| Tracciamento invii | Log completo + avviso "già inviata" non bloccante |
| Formato corpo | Testo semplice → HTML (`nl2br` + shell HTML come newsletter rimborsi) |
| Architettura | Tab in site_utils, UI server-rendered, invio via AJAX un ordine alla volta con progresso |

## Database

Nuova migrazione `2026MMDDNNNN_email_template_e_log.sql` in **entrambi** i `sql/`
(applicazione manuale per ambiente, come da regola):

```sql
CREATE TABLE email_template (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE order_email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  template_id INT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  sent_by INT NOT NULL,
  KEY idx_order (order_id)
);
```

- `template_id` è NULL se oggetto/corpo sono stati scritti a mano (o il template poi
  cancellato: nessuna FK, il log conserva copia dell'oggetto).
- `sent_by` = id dell'utente admin che ha lanciato l'invio.

## Componenti

### 1. `site_utils.php` — due nuovi tab

Tab esistenti: Email di Test / Esecuzione SQL / Impostazioni. Si aggiungono:

- **Email Ordini** → include `admin/pages/site_utils_email_orders.php`
- **Template Email** → include `admin/pages/site_utils_email_templates.php`

I contenuti dei tab vivono in partial separati per non far crescere `site_utils.php`.
`$activeTab` esteso con `email_orders` e `email_templates`.

### 2. Partial "Email Ordini" (`site_utils_email_orders.php`)

Tre card impilate:

**Card filtri** (GET, pulsante "Carica ordini") con selettore di modalità:

1. *Stato + anno*: dropdown su `orders.status` (`inviata`, `accettata`, `chiusa`,
   `annullata`) e anno di `orders.created_at`. Il caso "inviate ma non consegnate"
   = stato `inviata`.
2. *Contiene libro*: ricerca testo su titolo/ISBN dei prodotti → ordini che hanno
   almeno un `order_item` con prodotto corrispondente.
3. *Query SQL*: textarea; solo SELECT (prima parola verificata); la prima colonna del
   risultato deve contenere gli id ordine (cast a int, id inesistenti ignorati).
4. *Elenco pratiche*: textarea con `numPratica` **o** id ordine separati da virgola
   o a capo (numeri; si cerca prima per `numPratica`, poi per id).

**Card ordini trovati** — tabella, una riga per ordine:

| Colonna | Contenuto |
|---|---|
| checkbox | selezione (`order_ids[]`) |
| Pratica | `numPratica` (o "—" se non assegnato) |
| Venditore | cognome nome |
| Email | `user.email` |
| Stato | badge stato ordine |
| Libri | numero di `order_item` non eliminati |
| Ultima email | da `order_email_log`: data + oggetto in tooltip; badge giallo "già inviata" (solo avviso, mai bloccante) |

Seleziona tutti / deseleziona / contatore; DataTable se > 10 righe (stesso pattern JS
di `seller-refund-newsletter.php`, inclusa la raccolta checkbox su tutte le pagine).

**Card composizione:**

- Dropdown template (opzioni caricate dal PHP; alla selezione, JS copia oggetto+corpo
  nei campi da un blob JSON incorporato nella pagina — i campi restano modificabili).
- Campi **Oggetto** (input) e **Corpo** (textarea, testo semplice).
- Legenda segnaposto.
- **Anteprima**: modal che mostra l'email risultante per il primo ordine selezionato
  (merge fatto lato server: chiamata all'endpoint AJAX con `preview=1`, nessun invio).
- **Invia alle selezionate**: avvia il loop AJAX.

### 3. Segnaposto (merge)

Sostituiti lato server per ogni ordine, sia in anteprima che all'invio:

| Segnaposto | Valore |
|---|---|
| `{nome}` | `user.first_name` |
| `{cognome}` | `user.last_name` |
| `{email}` | `user.email` |
| `{num_pratica}` | `orders.numPratica` |
| `{stato}` | stato ordine |
| `{data_pratica}` | data creazione ordine (gg/mm/aaaa) |
| `{num_libri}` | conteggio libri della pratica |
| `{elenco_libri}` | una riga per libro: `Titolo — € prezzo` |

Ordine delle operazioni: prima il merge sul testo semplice, poi
`nl2br(esc_html(...))`, poi wrapping nella shell HTML (stessa della newsletter
rimborsi). Così i valori merged non possono iniettare HTML.

### 4. Partial "Template Email" (`site_utils_email_templates.php`)

- Tabella template: nome, oggetto, ultimo aggiornamento, azioni Modifica / Elimina
  (conferma JS).
- Form crea/modifica: nome, oggetto, corpo + legenda segnaposto.
- Postback classici con `csrf_field()` + `CSRF::validateToken()`.
- L'eliminazione non tocca `order_email_log` (che conserva copia dell'oggetto).

Persistenza tramite nuova classe `EmailTemplateManager extends DBManager`
(pattern esistente, tabella `email_template`) in `classes/EmailTemplate.php`.

### 5. Endpoint AJAX `api/admin/send-order-email.php`

- Controllo admin (stesso check di site_utils) + `CSRF::validateAjaxOrDie()`
  (token da `CSRF::getTokenForAjax()` incorporato nella pagina).
- Input POST: `order_id` (int), `subject`, `body`, `template_id` (int|vuoto),
  più `preview=1` opzionale per la sola anteprima (nessun invio, ritorna il merge).
- Carica ordine + venditore + libri; se l'ordine non esiste o l'email è vuota/non
  valida → `{ok:false, error:"..."}`.
- Esegue merge, costruisce HTML, chiama `send_mail()`; se ok inserisce riga in
  `order_email_log` e risponde `{ok:true}`; altrimenti `{ok:false, error}`.

**JS di invio:** richieste sequenziali (una alla volta) per ogni ordine selezionato;
barra di avanzamento "N/M inviate, K errori"; ogni riga marcata ✓/✗; riepilogo finale
con l'elenco degli errori. Un'interruzione a metà non perde nulla: gli invii riusciti
sono già loggati e la colonna "Ultima email" li mostrerà al ricaricamento.

## Sicurezza e convenzioni

- CSRF su ogni POST e sull'endpoint AJAX.
- Output sempre via `esc_html()`; id cast a int; URL non HTML-escaped dentro stringhe JS.
- Modalità SQL: sola lettura (prima parola `SELECT`), id risultanti cast a int.
- Testi UI in italiano, "tu" informale.
- **Dual-tree**: ogni file speculare in `web/htdocs/staging/…`, SQL in entrambi i `sql/`.
- Aggiornare `context/03-codebase-map.md` (nuovi partial, classe, endpoint),
  `context/04-database.md` (nuove tabelle), `context/05-domain-workflows.md`
  (nuovo flusso email ordini) nello stesso lavoro.
- Nessuna test suite: verifica per lettura + prova manuale su staging.

## Fuori scope

Allegati, CC/BCC, editor HTML/WYSIWYG, invii programmati, gestione disiscrizione,
raggruppamento per venditore (una sola email con più pratiche).
