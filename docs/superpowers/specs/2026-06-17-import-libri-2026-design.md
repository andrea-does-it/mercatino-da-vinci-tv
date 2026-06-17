# Import Libri 2026 — Design

Data: 2026-06-17
Stato: in revisione

## Contesto

La cartella `docs/import_libri/2026/` contiene 11 foto di liste di libri **scritte a
mano**, organizzate per anno (Prime → Quinte classi), sezione (2A, 2D…) e indirizzo
(Tradizionale / Sc. Applicate / Sportivo). Per ogni libro: titolo, ISBN, materia.
Una legenda colori indica: sottolineatura **blu** = "libri già inseriti",
verde/giallo = "nuove edizioni/adozioni", riquadro **rosso** = "consigliato".

Queste sono le liste dei libri di testo supportati nel 2026 dal mercatino del libro
del sito in `web/htdocs`.

### Modello dati esistente
- Tabella `product`: `id, name, price, category_id, sconto, data_inizio_sconto,
  data_fine_sconto, qta, ISBN, autori, editore, nota_volumi, fl_esaurimento`
  (vedi `classes/Product.php`).
- Immagini in tabella `product_images` (`id, product_id, image_extension, title,
  alt, order_number`) + file su filesystem `images/<product_id>/<image_id>.jpg`.
- Pipeline upload (`api/admin/upload.php`): crea record `product_images`, salva il
  file, poi `ImageUtilities::wallpaper` (resize max 1280×1280, sovrascrive
  l'originale) + `ImageUtilities::thumbnail` (crea `<id>_thumbnail.jpg` max 320×320).
- Categorie (`category`): gerarchiche (`parent_id`). **Le categorie reali esistono
  solo nel DB di produzione**; il repository contiene solo categorie demo segnaposto.

### Fattibilità fonte dati (verificata)
Libraccio espone tutto il necessario via URL di ricerca:
`https://www.libraccio.it/src/?FT=<ISBN>&ch=libraccio` →
titolo, autori, editore, **prezzo di copertina**.
Copertina via URL **deterministico**:
`https://www.libraccio.it/images/<ISBN>_0_500_0_75.jpg` (niente scraping immagine).
Esempio verificato: ISBN `9788838338748` → "Homo sum civis sum… Vol. 3", Sansoni,
€34,60.

## Obiettivo

Uno **strumento riutilizzabile** (pagina admin) che, da una lista di ISBN:
1. recupera dati + copertina + prezzo da Libraccio (+ cross-check seconda fonte);
2. verifica se il libro è già presente a DB (per ISBN);
3. per i nuovi libri popola `product`, scarica e installa la copertina con le
   dimensioni corrette, calcolando il prezzo mercatino.

Riutilizzabile ogni anno per nuove liste, non solo per il 2026.

## Decisioni prese

- **Prezzo**: salvo **entrambi**. `product.price` = prezzo mercatino calcolato;
  nuovo campo `product.prezzo_listino` = prezzo di copertina pieno.
- **Formula prezzo mercatino**: `price = (prezzo_listino / 2) − 1,50`, arrotondato a
  2 decimali. Le impostazioni `bookshop_seller_deduction` / `bookshop_buyer_markup`
  NON si applicano a questi prodotti (il contributo è già nella formula).
- **Categorie**: l'utente fornirà un CSV di mappatura `materia → category_id`. Il
  design produce (a) una query SQL per estrarre le categorie reali e (b) l'elenco
  delle materie ricavate dalle immagini.
- **Esecuzione 2026**: prima si consegna la tabella arricchita per revisione, poi si
  costruisce comunque il tool riutilizzabile.
- **Ambiente**: si sviluppa e si testa **prima in staging** (`web/htdocs/staging/`,
  sito `https://www.comitatogenitoridavtv.it/staging`, DB separato da produzione).
  Solo dopo i test su staging si rispecchia il codice in `web/htdocs/`. Il codice PHP
  e gli accessi al DB girano **lato server**: dall'ambiente locale si scrive il codice
  e si committa; i test funzionali (lookup runtime, insert, immagini) li esegue
  l'admin sull'URL di staging.

## Architettura

### Fase 1 — Tabella 2026 arricchita (una tantum, eseguita dall'agente)

1. **Trascrizione foto → `docs/import_libri/2026/libri_2026.csv`**
   Colonne: `anno, sezioni, indirizzo, materia, titolo_manoscritto, isbn,
   flag` (nuova_adozione | gia_in_uso | consigliato | gia_inserito-da-legenda).
   I titoli ripetuti tra più sezioni vengono deduplicati per ISBN.

2. **Arricchimento via web** (per ogni ISBN distinto):
   - validazione checksum ISBN-13;
   - lookup Libraccio → titolo, autori, editore, prezzo di copertina;
   - URL copertina deterministico + download del file in
     `docs/import_libri/2026/covers/<isbn>.jpg`;
   - cross-check prezzo con seconda fonte (Google Books / IBS) ove possibile;
   - cross-check titolo Libraccio vs `titolo_manoscritto` per intercettare ISBN
     trascritti male; le discordanze vengono evidenziate.

3. **Calcolo** `prezzo_mercatino = round(listino/2 − 1,50; 2)`.

4. **Verifica presenza a DB**: in Fase 1 si usa la legenda (blu = già inserito);
   si fornisce inoltre una query SQL con gli ISBN del 2026 per il controllo
   autorevole sul DB di produzione.

5. **Deliverable**: `docs/import_libri/2026/libri_2026_arricchito.csv` (con prezzi,
   prezzo mercatino, URL copertina, flag già-a-DB, anomalie) + cartella `covers/`.
   Consegnato per revisione **prima** di toccare il sito.

### Fase 2 — Strumento riutilizzabile

1. **Migrazione schema** — `sql/<timestamp>_product_prezzo_listino.sql` (+ mirror in
   `staging/sql/`): `ALTER TABLE product ADD COLUMN prezzo_listino DECIMAL(8,2)
   DEFAULT NULL AFTER price;`

2. **`classes/utilities/BookLookup.php`** — servizio di lookup ISBN:
   - `validateIsbn13($isbn): bool`
   - `lookup($isbn): array|null` → `{title, authors, publisher, list_price,
     cover_url, price_source, price_secondary?, warnings[]}`
   - fonte primaria Libraccio (parsing della pagina di ricerca); cover URL
     deterministico; seconda fonte per cross-check prezzo.
   - Nessuna dipendenza dallo stato dell'app: riceve un ISBN, restituisce dati.

3. **`ProductManager::findByISBN($isbn): Product|null`** — verifica presenza a DB.

4. **Pagina admin `admin/pages/import-libri.php` (+ endpoint API)**:
   - input: lista ISBN incollata o CSV caricato;
   - per ogni ISBN mostra: dati Libraccio, anteprima copertina, **già a DB?** (link
     al prodotto se esiste), prezzo listino, prezzo mercatino calcolato, **menù
     categoria** preimpostato dalla mappatura materia→categoria;
   - l'admin rivede, deseleziona già-presenti/errati, conferma;
   - su conferma, per ogni nuovo libro: insert `product` (price=mercatino,
     prezzo_listino=listino, qta, ISBN, autori, editore, category_id) → insert
     `product_images` → download copertina nel path corretto →
     `ImageUtilities::wallpaper` + `thumbnail` (identico a `upload.php`);
   - bottone **export CSV** della tabella arricchita.
   - protezioni esistenti: guard `user_type == 'admin'`, `CSRF::validateAjaxOrDie()`.

5. **Supporto mappatura categorie**:
   - query `SELECT id, name, parent_id FROM category ORDER BY parent_id, name;`
   - `docs/import_libri/mappatura_materie.csv` (materia → category_id), compilato
     dall'utente e consumato dalla pagina di import.

## Unità e confini

- `BookLookup`: input ISBN → output dati libro. Testabile in isolamento con ISBN noti.
- `ProductManager::findByISBN`: input ISBN → Product|null. Query singola.
- Pipeline immagini: riuso di `ImageUtilities` esistente, nessuna logica nuova di
  resize.
- Pagina import: orchestrazione (lookup + verifica + insert + immagini), nessuna
  logica di dominio propria oltre il calcolo prezzo.

## Gestione errori

- ISBN con checksum non valido → segnalato, non importato.
- Libraccio non trova il libro / prezzo assente → riga marcata "da completare a
  mano", nessun insert automatico.
- Discordanza prezzo tra fonti o titolo molto diverso → warning visibile, l'admin
  decide.
- Download copertina fallito → il prodotto viene comunque creato; copertina segnalata
  come mancante per ripiego manuale.
- Import idempotente: un ISBN già a DB non viene reinserito.

## Test

- `BookLookup::validateIsbn13` su ISBN validi/non validi noti.
- `BookLookup::lookup` su un campione di ISBN reali dalle liste 2026 (titolo, prezzo,
  cover URL attesi).
- `findByISBN` con prodotto esistente e inesistente.
- Calcolo prezzo mercatino su valori noti (es. 34,60 → 15,80).
- Verifica manuale fine-a-fine: import di 2-3 libri su staging, controllo file
  immagine (1280 + thumbnail 320) e record DB.

## Materie ricavate dalle immagini (20)

Antologia · Epica · Grammatica (italiano) · Latino · Latino-Laboratorio · Geostoria ·
Storia · Filosofia · Diritto ed Economia · Matematica · Fisica · Scienze (della
Terra) · Biologia · Chimica · Arte · Inglese-Grammatica · Inglese-Letteratura/Triennio
· Inglese-Biennio · Informatica · Scienze Motorie/Discipline Sportive

## Fuori scope

- Modifica delle logiche di prezzo/contributo del mercatino esistenti.
- Import automatico in produzione senza revisione admin.
- Gestione di fonti dati diverse da Libraccio + una seconda per cross-check prezzo.
