# Mercatino Da Vinci — Context Index

> **Read this first when starting a new chat.** This folder is a consolidated, current
> knowledge base for the Mercatino Da Vinci codebase. It lives at the repo root and is
> **NOT** under `web/htdocs/`, so it is never deployed to the live site.

## What this project is
Web app for the **second-hand schoolbook market ("mercatino del libro usato")** of
*Liceo Scientifico Leonardo Da Vinci, Treviso*, run by the parents' committee
(*Comitato Genitori*). Parents/students **sell** used textbooks and **buy** available
ones; admins run acceptance, in-person sales, and end-of-mercatino payouts.

## How to use these docs
Load the file(s) relevant to the task. For most tasks, skim `01-overview` +
`06-conventions-and-gotchas`, then the topic file you need.

| File | When to read it |
|------|-----------------|
| [01-overview.md](01-overview.md) | Purpose, stakeholders, tech stack, environments |
| [02-architecture-and-routing.md](02-architecture-and-routing.md) | Request flow, routers, bootstrap/config, dual-tree, deployment |
| [03-codebase-map.md](03-codebase-map.md) | Directory layout, every class's purpose, key admin pages & APIs |
| [04-database.md](04-database.md) | Main tables, migrations convention, how schema changes are applied |
| [05-domain-workflows.md](05-domain-workflows.md) | Selling (pratica), sales transactions + receipt, seller refunds/closing, products & images, auth, emails |
| [06-conventions-and-gotchas.md](06-conventions-and-gotchas.md) | **Critical**: CSRF, escaping, commit rules, mirroring, and recurring bug traps |

## Hard rules (see 06 for detail)
1. **Dual-tree:** every change under `web/htdocs/<path>` must be mirrored to `web/htdocs/staging/<path>` (and SQL to both `sql/` dirs).
2. **Commit messages must NOT mention Claude/AI** (any author/co-author trailer included).
3. **Migrations are applied manually to each database** — a committed `.sql` file does *not* mean the column exists in production.
4. **CSRF token required** on every POST / admin AJAX call.
5. UI text is Italian, informal **"tu"** form.

## Deeper (older) reference docs
These predate the 2026 sales-transaction work but remain useful for breadth:
`../docs/website_overview.md`, `../docs/application_structure.md`,
`../docs/database_schema.md`, `../docs/api_documentation.md`.
Where they conflict with this folder, **this folder wins** (it is current).

## Status snapshot (2026-10)
Nuova sezione **Distinte SEPA** (`seller-refund-sepa.php`, `admin/?page=seller-refund-sepa`):
genera il file XML di bonifici multipli (SEPA CBI 04.01) per pagare in un colpo solo i
rimborsi venditori con bonifico, e tiene lo storico di chi è stato pagato con quale file.
Nuovo stato del rimborso `xmlsaved` ("Distinta generata"), nuove tabelle `sepa_batch` /
`sepa_batch_item` (l'XML non viene mai salvato: solo i metadati e l'IBAN mascherato), dati
ordinante/causale in `site_settings`. Classi `SepaCbiExport` (costruzione XML, pura) e
`SepaBatchManager` (`classes/SepaBatch.php`); self-test di sola lettura
`admin/?page=sepa-selftest`. **Migrazioni `202610010001_sepa_distinte.sql` e
`202610020001_sepa_localita.sql` da applicare a mano, in ordine, su ogni ambiente** —
finché non lo sono entrambe, `SepaBatchManager::isInstalled()` lo rileva e
pagina/endpoint/self-test si bloccano con un messaggio esplicito **prima** di scrivere
qualunque cosa (non un salvataggio che fallisce). La seconda migrazione (dopo il rifiuto del
primo caricamento reale su UniCredit) aggiunge `user.iban_town` e i settings
`sepa_debtor_town`/`sepa_default_creditor_town`/`sepa_category_purpose`: la banca richiede
Località+Paese per ordinante e beneficiari e il Category Purpose, mentre il CUC è risultato
facoltativo (la banca lo sostituisce comunque). Dettagli: `03-codebase-map.md`, `04-database.md`,
`05-domain-workflows.md` §C, `06-conventions-and-gotchas.md` (connessione PDO per manager,
download da endpoint standalone, caratteri non-ASCII nei sorgenti PHP).

## Status snapshot (2026-09)
I rimborsi venditori ereditano le preferenze dal profilo: alla creazione dei record
`payment_preference` = bonifico se l'utente ha l'IBAN, altrimenti contanti, e
`donate_unsold` = `user.donate_books`; i record già esistenti si allineano con il pulsante
"Applica Preferenze dal Profilo" (tocca solo le colonne ancora NULL). La newsletter
preferenze (`seller-refund-newsletter`) filtra anche sul profilo (IBAN / Donazione /
"Da contattare") per scrivere solo a chi non ha ancora risposto.
La **pratica 100** (libri di proprietà del mercatino) è esclusa da tutta la sezione rimborsi
— importi, elenchi, newsletter, report — perché il suo incasso è guadagno netto del Comitato;
resta invece nelle viste vendite.
Nella landing page `payment-preference.php` due scelte sono **a senso unico**: se l'utente ha
già un IBAN i radio sono disabilitati (solo bonifico, IBAN/intestatario restano modificabili e
ora precompilati) e, una volta confermata la donazione dei libri invenduti, la casella resta
spuntata e disabilitata — si annulla **solo via UPDATE SQL**. Entrambi i blocchi sono imposti
lato server. Il report di chiusura ha ora gli stessi filtri della newsletter (+ Stato), tre
formati di stampa (A4, A3, **A4 Sintetico** con solo il numero di libri da rendere) e la
scelta della dimensione del carattere. La pagina rimborsi mostra il **Ricavo del Comitato**
(incasso pratica 100 + ricarico sulle altre pratiche, dal registro vendite) e il totale
**Contanti da Rimborsare**. Nuova pagina **Riepilogo Pratiche** (`seller-orders-report`):
**foglio di ritiro A3 landscape**, una riga per venditore con elenco dei libri da rendere
(`pratica/titolo`) e colonne da firmare a mano; letto da `order_item.status`, quindi
indipendente da record di rimborso e vendite registrate.
**Nessuna migrazione DB** per queste modifiche.

## Status snapshot (2026-07)
The transaction-based sales management is live (replacing the old per-item "venduto"
flow), the `donate_books` profile preference exists, product admin has hidden/esaurimento
columns + CSV/Excel export + filters, and all DB migrations through `202606220001` are
applied in production. History is on `main`. site_utils ha i tab "Email Ordini" (mail merge
verso i venditori delle pratiche filtrate, log in `order_email_log`) e "Template Email";
la migrazione `202607070001` esiste nel repo ma va applicata a mano su ogni ambiente.
