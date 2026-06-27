# CLAUDE.md — Mercatino Da Vinci

Web app for the used-textbook market of *Liceo Scientifico Da Vinci, Treviso*
(*Comitato Genitori*). PHP, no framework, MySQL.

## Read first
Load **[context/INDEX.md](context/INDEX.md)** at the start of work — it's the consolidated
knowledge base (overview, architecture/routing, codebase map, database, domain workflows,
conventions & gotchas). For anything non-trivial, read the relevant `context/*.md` file.

The `context/` folder and this file live at the repo root and are **not** deployed (only
`web/htdocs/` is synced to the server).

## Hard rules (details in context/06-conventions-and-gotchas.md)
1. **Dual-tree:** mirror every change in `web/htdocs/<path>` to `web/htdocs/staging/<path>`
   (SQL to both `sql/` dirs). If a file differs between trees, hand-apply — don't blind-copy.
2. **Commit messages must NOT mention Claude/AI** — no "Claude/Anthropic/Generated with…"
   and **no `Co-Authored-By` AI trailer**. Messages are usually Italian.
3. **Migrations are applied to the DB by hand, per environment** — a committed `.sql` file
   does NOT mean the column exists on the server. (Common "works on staging, errors on prod".)
4. **CSRF** on every POST/admin AJAX (`csrf_field()` + `CSRF::validateToken()`; AJAX sends
   `csrf_token` from `CSRF::getTokenForAjax()`, endpoint uses `CSRF::validateAjaxOrDie()`).
5. **UI text in Italian, informal "tu"**. Escape with `esc()`/`esc_html()`; cast ids to int.
6. **No test suite; `php` often not on PATH** — verify by reading + manual checks, don't
   claim "tested" when you couldn't run it.

## Known bug traps (see context/06)
Object-cast inserts + `NOT NULL` columns; checkbox value-not-`isset` (`postUnchecked` JS);
don't HTML-escape URLs used in JS strings; FPDF `Cell` overflow; server-side exports.

## Keep this knowledge base current (standing instruction)
When a change to the site alters something the knowledge base describes — architecture,
routing, a class/page/API, the DB schema, a domain workflow, a convention, or a new
gotcha — **update the relevant `context/*.md` file(s) (and this file if a hard rule
changes) in the same piece of work**, and keep `context/INDEX.md`'s status snapshot
accurate. Mirror code to both trees; docs live only here (not in `web/htdocs/`).
