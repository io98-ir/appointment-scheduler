---
name: resume
description: Start-of-session protocol. Loads project state from the progress tracker, the worklog and git, then reports where the work stands and what comes next. Use at the beginning of every new session or when the user says "continue" or "ادامه بده".
---

# Resume session

Follow `docs/05-delivery/03-agent-workflow.md` §1 exactly:

1. Read `docs/05-delivery/02-progress.md`, especially the "Resume Here" section and any 🟨 or ⛔ tasks.
2. Read the latest 3 entries at the top of `docs/05-delivery/04-worklog.md`.
3. Run `git status` and `git log --oneline -10`. If there are uncommitted changes, work out which task they belong to before doing anything else.
4. Find the next task in `docs/05-delivery/01-roadmap.md` and read its "Done when" criteria.
5. Read only the design sections relevant to that task. Do not load all of docs/.
6. Reply to the user in Persian, briefly:
   - current milestone and task
   - last thing completed
   - blockers or open decisions
   - what you will do now

Then continue with `/next-task` unless the user says otherwise.
