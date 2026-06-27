# 02 — Architecture & Routing

## Request flow
1. Each functional area has an entry router `index.php`: `public/`, `shop/`, `user/`,
   `auth/`, `admin/`. They take a `?page=<name>` query param.
2. The router includes `inc/init.php`, renders `public/template-parts/header.php`, then
   **`include "pages/<page>.php"`**, then `footer.php`.
3. Page controllers are procedural PHP files in `<area>/pages/`. They read `$_POST`/`$_GET`,
   call manager classes, and emit HTML. Shared globals (`$loggedInUser`, `$alertMsg`) are
   set up by the bootstrap and template includes.

### Page whitelisting (LFI protection)
`admin/index.php` validates `?page=` against a hard-coded `$allowedPages` array before
including the file; unknown pages fall back to `dashboard`. **When you add a new admin
page, add its slug to `$allowedPages`** (in both trees). Standalone scripts that stream
output (PDF/CSV/export) are *not* routed through `index.php` and live under `api/` or
`shop/invoices/` instead (they require `inc/init.php` themselves).

## Bootstrap & config
`inc/init.php` loads, in order:
`config.php` → `include-classes.php` → `globals.php` → `functions.php` → `authorize.php`.

- `inc/config.php` defines constants (`ROOT_PATH`, `ROOT_URL`, `SITE_NAME`, DB creds, SMTP
  constants). A `.env` file at `web/htdocs/.env` holds environment secrets. **Do not commit
  or echo secrets.**
- `inc/include-classes.php` requires the class files (no autoloader).
- `inc/globals.php` sets shared globals.
- `inc/functions.php` holds helpers: `send_mail()`, `esc()`, `esc_html()`, `csrf_field()`,
  `log_activity()`, etc.
- `inc/authorize.php` resolves the session user into the global `$loggedInUser`.

## Access control
- `$loggedInUser->user_type` ∈ `regular` | `admin` | `pwuser`.
- The whole admin area is gated in `admin/index.php`: only `admin` or `pwuser` pass;
  others are redirected to the user dashboard.
- Some admin APIs are stricter (`admin` only) — e.g. `api/admin/upload.php`,
  `categories.php`. Check the gate at the top of each endpoint.

## Dual-tree & deployment
- Two parallel copies: `web/htdocs/` (prod) and `web/htdocs/staging/` (served at `/staging/`).
- Deployment to the live server is by **file sync/FTP** done by the maintainer, not by a
  pipeline. Bumping file mtimes (`touch`) is sometimes used so a sync picks changed files up.
- Because of this, **committing/pushing does not deploy**, and **applying a migration file
  to git does not change any database** — both are separate manual steps on the server.
- Mixed CRLF/LF line endings exist across the repo (git `autocrlf` noise); content parity
  matters, line-ending diffs are cosmetic.

## Frontend conventions
- Bootstrap 4 markup; jQuery for behavior; DataTables for admin tables (sorting, search,
  and column filters via `table.column(i).search(...).draw()`).
- AJAX posts to `api/...` endpoints returning JSON; must include a CSRF token (see 06).
