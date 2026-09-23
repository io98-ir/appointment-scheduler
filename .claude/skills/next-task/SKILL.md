---
name: next-task
description: Execute exactly one roadmap task end-to-end. The steps are mark in progress, plan, test first, implement, verify by running the tools, review, update the tracker and worklog, then commit. Use when continuing implementation work. Optional argument is a task ID such as T1.4.
---

# Execute one task

Task: `$ARGUMENTS`. If empty, take the first ⬜ or 🟨 task in `docs/05-delivery/02-progress.md`.

Follow `docs/05-delivery/03-agent-workflow.md` §2:

1. Set the task to 🟨 in `02-progress.md` and update "Resume Here".
2. Read the task's "Done when" criteria in `01-roadmap.md` and the relevant design docs. Ask the user only about decisions that are genuinely theirs to make. Otherwise pick a sensible default and record it in the worklog.
3. For work that touches more than about 5 files, make a short step plan first.
4. Write the tests first for Domain and Application code.
5. Implement following `docs/04-engineering/01-principles.md`. Re-check §0 (anti-overengineering) before adding any abstraction or dependency.
6. Verify by running the tools and reading their output: `composer lint`, `composer stan`, `composer test`, plus the relevant JS or E2E tests. Never claim success without real output. If a tool is unavailable, say exactly what could not be verified.
7. Run the `reviewer` subagent on the diff. Fix the findings that hold up.
8. Update the tracker (✅ + commit hash), the worklog entry, and any ADR, CHANGELOG or module README the task affects.
9. Commit with Conventional Commits and the task ID, for example `feat(booking): hold with row locks (T2.2)`. Do not push unless the user asks.

Stop after one task and give the user a short Persian summary. Offer to continue with the next task.
