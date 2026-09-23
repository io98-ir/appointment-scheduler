---
name: wrap
description: End-of-session protocol. Records exact state so the next session can resume. It updates the progress tracker's Resume Here section, writes a worklog entry and commits finished work. Use when the user ends the session, says "wrap up" or "پایان", or when context is running low.
---

# Wrap up session

Follow `docs/05-delivery/03-agent-workflow.md` §3:

1. For any unfinished task, keep it 🟨. In the notes column write exactly where the work stopped and the next concrete step, such as a file name, a function, or which failing test to look at.
2. Rewrite the "Resume Here" table in `docs/05-delivery/02-progress.md`.
3. Add a new entry at the top of `docs/05-delivery/04-worklog.md` using the template in that file. Include the verification commands you ran and their results, and every assumption the user should know about.
4. Commit finished work and `git push` to `origin`. `main` must stay green. If work is unfinished and breaks tests, commit it with a `wip:` prefix on a separate branch, push that branch, and name it in "Resume Here". Never force-push unless the user asks.
5. Give the user a short Persian summary: what was done, what remains, and any decision needed from them.
