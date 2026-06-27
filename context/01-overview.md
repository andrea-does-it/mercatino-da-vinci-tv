# 01 — Overview

## Purpose & users
A web app managing the used-textbook market of *Liceo Scientifico Da Vinci, Treviso*,
operated by the *Comitato Genitori*.

- **Sellers** (parents/students): submit used textbooks for sale, tracked as a numbered
  *pratica* (practice/case); follow status submitted → accepted → for sale → sold; the
  seller is paid their share at the end of the mercatino.
- **Buyers**: browse/search the catalog; the actual sale happens **in person** at the
  mercatino (cash/POS/Satispay), recorded by an operator as a *sales transaction*.
- **Admins / power users**: accept submissions, run the till (sales), produce the
  end-of-mercatino payout report, manage catalog/categories/news/users.

## Economic model
- Cover price → **seller price** ≈ 50% of cover − a configurable *seller deduction*.
- **Sale price** = seller price + a configurable *buyer markup*.
- The difference (deduction + markup = *total markup*) is the committee's margin.
- All amounts come from `SiteSettings` (`sellerDeduction()`, `buyerMarkup()`,
  `totalMarkup()`), not hard-coded.

## Tech stack
- **PHP 7/8**, **no framework** — procedural page controllers + "manager" classes over a
  thin PDO wrapper (`classes/DB.php`).
- **MySQL/MariaDB**; schema changes are plain `.sql` migration files applied by hand.
- **Frontend**: Bootstrap 4, jQuery, DataTables; server-rendered PHP pages.
- **PDF**: FPDF (`vendor/fpdf`) for receipts/invoices (`classes/utilities/PdfUtilities.php`).
- **Email**: PHPMailer over SMTP, wrapped by `send_mail()` in `inc/functions.php`
  (templates are inline HTML strings).
- **Payments**: PayPal/Stripe libraries are present in `vendor/`, but mercatino sales are
  in-person; the till payment methods are cash / POS / Satispay (PayPal removed from the
  new-sale dropdown).
- **Images**: stored on the filesystem under `images/<product_id>/`, tracked in
  `product_images`; covers can be auto-fetched by ISBN from Libraccio (`BookLookup`).

## Environments (one repo, two trees)
- **Production tree:** `web/htdocs/...` — served at the site root.
- **Staging tree:** `web/htdocs/staging/...` — a parallel copy served at `/staging/`.
- The two trees are kept in sync: **every change is applied to both** (see
  `06-conventions-and-gotchas.md`). They use separate databases/config.

## No automated tests
There is no test suite, and `php` is frequently not on PATH on the dev machine.
Verification = careful code reading + manual browser/DB checks. Plan accordingly.
