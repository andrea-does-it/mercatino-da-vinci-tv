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

## Status snapshot (2026-06)
The transaction-based sales management is live (replacing the old per-item "venduto"
flow), the `donate_books` profile preference exists, product admin has hidden/esaurimento
columns + CSV/Excel export + filters, and all DB migrations through `202606220001` are
applied in production. History is on `main`.
