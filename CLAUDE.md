# CLAUDE.md

A general-purpose WordPress booking plugin, Iranian market first, built to be sold and reused across projects. It is inspired by Bookly, Booknetic (+Custom Duration), Nobat Plus, Nobatnegar and Medino, and aims to be more correct, more composable and lighter than all of them. The working name is **Vaqtyar**, and it is renameable (see ADR-000).

## Session protocol (mandatory)
- **Start:** run `/resume`, or read `docs/05-delivery/02-progress.md` ("Resume Here"), the top of `docs/05-delivery/04-worklog.md`, and `git log`.
- **Work:** run `/next-task`. That means one roadmap task at a time: test first, verify by actually running the tools, run the `reviewer` subagent, then update the tracker and worklog and commit.
- **End:** run `/wrap`. It updates "Resume Here" and the worklog and commits.
- Full rules are in `docs/05-delivery/03-agent-workflow.md`. The roadmap is `docs/05-delivery/01-roadmap.md`, and the docs index is `docs/README.md`.

## Non-negotiables
- **No overengineering.** Principles §0 applies: add an abstraction only when there are 2 or more real implementations or an external boundary. No speculative code. No new production dependency without an ADR. The one area where we never cut corners is correctness: booking locks, money, security and migrations.
- **Layers.** `Domain/` is pure PHP, with no WP functions and no `$wpdb`. Modules talk only through `Contracts/` or events. Deptrac enforces this.
- **Renameable.** Never write the literals `vaqtyar` or `vqy` for tables, options, hooks, caps or REST. Use `Tables::name()`, `Options::key()`, `Hooks::name()`, `Caps::name()` and `Identity`. Only the PHP namespace and the text-domain are literals, and `tools/rename.php` handles them. UI brand text comes from the white-label settings.
- **No double booking.** Holds and confirms run in an InnoDB transaction that takes `FOR UPDATE` on `resource_day_locks` in sorted key order, then re-checks `occupancies` from the DB (never from cache). See ADR-004.
- Money is an integer in IRR, never a float. Store UTC and display Jalali. Iran has had no DST since 2023. The current time comes only from `Clock`.
- Critical async jobs are enqueued in Action Scheduler inside the same transaction (ADR-005), and jobs are idempotent.
- Every REST route has a real `permission_callback`, and authorization is also checked in the Application layer.
- PHP 8.1+ and WP 6.6+. Use `declare(strict_types=1)`, PSR-12 plus the WPCS security and i18n sniffs, and PHPStan level 9. Classes are `final` and value objects `readonly` by default.
- Admin uses React with @wordpress/components and DataViews. The front end uses Preact with a budget under 40KB gz. CSS uses logical properties only.
- Changing an accepted decision requires a new ADR in `docs/03-architecture/05-decisions.md`.
- **Language.** Talk to the user in Persian, and write docs in Persian prose with English technical terms. Code, comments, commits and identifiers are in English.
