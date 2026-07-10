# 06 — Conventions & Gotchas

**Read this before editing.** These are the rules and the traps that have actually bitten.

## Hard conventions
- **Dual-tree mirroring:** apply every change to BOTH `web/htdocs/<path>` and
  `web/htdocs/staging/<path>` (SQL to both `sql/` dirs). A safe way to mirror a file that
  is identical between trees: confirm parity at HEAD (`git show HEAD:prod` vs
  `HEAD:staging`, ignoring CR), then copy. If a file differs between trees (e.g.
  `public/template-parts/header.php`), apply the change by hand to each, don't blind-copy.
- **Commit messages must NOT reference Claude/AI** — no "Claude", "Anthropic", "Generated
  with…", and **no `Co-Authored-By` AI trailer**. (This overrides any default trailer.)
  Messages are typically Italian.
- **UI text:** Italian, informal **"tu"** (not "Lei").
- **Escaping:** `esc($x)` = `mysqli_real_escape_string(htmlspecialchars($x))` for input
  going to DB/output; `esc_html($x)` = `htmlspecialchars` for display. Cast ids to `(int)`.
- **CSRF on every POST / admin AJAX** (see below).
- Prefer existing patterns (manager classes, page-controller style) over new abstractions.

## CSRF
- Forms: include `csrf_field()`; handlers call `CSRF::validateToken()` (or redirect with
  `msg=csrf_error`).
- AJAX: send `csrf_token` in the payload, value from `CSRF::getTokenForAjax()`; the endpoint
  calls `CSRF::validateAjaxOrDie()`.
- **Trap:** a page doing AJAX uploads/deletes must inject the token. `product.php` image
  upload silently 403'd for a long time because its AJAX calls sent no `csrf_token` and had
  no error handler. Pattern to copy: `var csrfToken = '<?php echo CSRF::getTokenForAjax(); ?>';`
  then add `csrf_token: csrfToken` (or `form_data.append('csrf_token', csrfToken)`).
- **Admin page controllers** (routed via `admin/index.php`, `include`d *after* `header.php`)
  proteggono le POST con `CSRF::validateOrDie()` subito dopo la guardia di accesso diretto,
  e i loro form includono `csrf_field()`. `validateOrDie()` è **output-safe**: se l'output è
  già iniziato (com'è sempre nell'area admin, dato che l'header è già stampato) fa un
  redirect lato client a `?msg=csrf_error`, invece di `http_response_code()`/`header()` che
  darebbero un warning *"headers already sent"*.
- **Trap di deploy CSRF:** un deploy *parziale* in cui l'enforcement è già online ma i form
  aggiornati (con `csrf_field()`) non sono ancora stati sincronizzati fa fallire **ogni** POST
  admin con `csrf_error` (il token non è nel form). Sincronizza sempre controller **e** form
  insieme; se il sync è basato su mtime, fai `touch` dei file cambiati (vedi Realtà d'ambiente).

## Recurring bug traps (each one cost a debugging session)
1. **Migration committed ≠ column exists.** SQL files are applied to the DB by hand per
   environment. If a feature "does nothing" or throws *Unknown column* / integrity errors
   on production but works on staging, first check whether the migration was actually run on
   that DB (`SHOW COLUMNS FROM <table> LIKE '<col>';`).
2. **`DBManager::create()`/`update()` cast the whole object** (`(array)$obj`) into the
   INSERT/UPDATE — every public property becomes a column. So:
   - An **uninitialized** property is sent as `NULL`. If its column is `NOT NULL`, inserts
     break (this broke registration via a `NOT NULL donate_books`; fix was nullable column
     + default the property). Keep boolean flag columns **nullable DEFAULT 0** *and/or*
     initialize the property (`public $flag = 0;`).
   - Don't add object properties that aren't real columns on objects passed to create/update.
3. **Checkbox value, not presence.** A page-wide JS `postUnchecked()` injects a hidden
   `<input name="<cb>" value="0">` for every *unchecked* checkbox, so `$_POST['<cb>']` is
   **always set**. Reading `isset($_POST['x']) ? 1 : 0` therefore always yields 1 — read the
   **value**: `isset($_POST['x']) ? (int)$_POST['x'] : 0` (this is why `fl_esaurimento`
   worked but `nascosto` didn't until fixed).
4. **Don't HTML-escape a URL used as a JS string.** `esc_html()` turns `&` into `&amp;`; in
   `window.location.href = '...&amp;id=1'` the query breaks (`$_GET['id']` never set). Emit
   the URL raw in JS contexts (it's fine HTML-escaped inside an `href` attribute).
5. **FPDF layout overflow.** `Cell(0, …)` does not wrap; long text overflows and the PDF
   viewer clips it. Use explicit margins + `MultiCell` for long headings; keep table column
   widths within the usable width. Keep the `iconv('UTF-8','windows-1252', …)` conversion.
6. **CSV/Excel export should be server-side**, not from the on-screen DataTable (the table
   has image carousels/action buttons that would pollute the output). Add a UTF-8 BOM so
   Excel shows accents; the "Excel" button can stream an HTML `<table>` as
   `application/vnd.ms-excel`.
7. **DataTables sorts `dd/mm/yyyy` dates as strings.** Type detection fails on day > 12, so
   `order: [[col,'desc']]` puts day 30/31 of ANY month on top — a log can look "stopped
   a week ago" right after a month change (this fooled us on the staging activity log).
   Emit `data-order="<?php echo strtotime($dt); ?>"` on the `<td>` so sorting is chronological.

## Environment realities
- **No test suite**; `php` is often not on PATH locally. Verify by reading code + manual
  browser/DB checks. Don't claim "tested" when you couldn't run it.
- **Mixed CRLF/LF**: ignore line-ending-only diffs (`diff --strip-trailing-cr`).
- **Deploy is manual** (FTP/sync by the maintainer). Pushing ≠ deploying; touching files
  bumps mtimes so a sync re-uploads them.
- New admin page → add its slug to `$allowedPages` in `admin/index.php` (both trees).
- **Secrets must not be web-served.** `.env` lives in the web root, so the root `.htaccess`
  denies dotfiles (`^\.`) plus `composer.json`/backup/dump extensions using the same
  `Order Allow,Deny` / `Deny from All` style as `sql/` + `jobs/` (Apache runs behind nginx
  and honours `.htaccess`). A publicly downloadable `.env` was a real incident — if you add
  secret files, keep them outside the docroot or covered by that deny, and never rely on
  obscurity. Rotating a leaked secret means rotating it at the source too (DB/SMTP/PayPal),
  and rotating `ENCRYPTION_KEY` requires re-encrypting stored IBANs.
- **Sessioni & HTTPS** (`inc/authorize.php`, entrambi i tree): il bootstrap invia gli
  header di sicurezza (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy`), imposta il cookie di sessione `HttpOnly` + `SameSite=Lax` (e `Secure`
  + HSTS quando la richiesta è https), e reindirizza in https le GET/HEAD arrivate in
  chiaro. **La rilevazione https NON si fida di `%{HTTPS}` di Apache** (dietro nginx risulta
  spesso `off`, tanto che i redirect di `mod_dir` escono in `http://`): usa
  `X-Forwarded-Proto` / porta 443, perciò l'enforcement va fatto in PHP, non con una regola
  `.htaccess` su `%{HTTPS}` (che andrebbe in loop).

## Git workflow that's been used here
Feature work on a branch, mirrored prod+staging per change, then fast-forward merge to
`main` and push. `main` is the integration branch.
