---
name: next-stage
description: Build the next stage of the Loyalty Hub app from loyalty-hub-phase1-build-prompts.md, following the project's stage-by-stage cadence. Use when the user says "next stage", "start stage N", "continue the build", or similar for this project.
---

# Next Stage

Runs one stage of the Loyalty Hub build (see `loyalty-hub-phase1-build-prompts.md` and
`CLAUDE.md` at the repo root) and stops for sign-off before continuing.

## Steps

1. **Identify the stage.** If the user named a stage number, use it. Otherwise, determine the
   next unbuilt stage from git history / existing code (check commit messages like
   `Stage N: ...` and what's already implemented) and confirm with the user before proceeding
   if it's ambiguous.
2. **Read context.** Read `CLAUDE.md` (Master Context, product rules, reliability practices,
   conventions) and the target stage's section in `loyalty-hub-phase1-build-prompts.md`
   (its Tasks list and Acceptance criteria).
3. **Implement only that stage's tasks.** Do not build anything from later stages, even if it
   seems convenient. Follow the conventions in `CLAUDE.md` (thin controllers, Services, Form
   Requests, Pest tests colocated with the stage).
4. **Verify:**
   - `php artisan migrate:fresh --seed` (once the DB exists) — must run clean.
   - `php artisan test` — must be green, including the tests this stage's spec calls for.
   - `yarn build` — must succeed.
   - Confirm the app boots with no errors.
   - Fix any failures before stopping. Do not report a stage done with failing checks.
5. **Report back:**
   - Files changed (grouped, not a raw diff dump).
   - Commands to run to see it locally.
   - The stage's Manual Test Checklist from the doc, reproduced — mark any item that needs
     Pusher keys or an HTTPS tunnel as "pending — needs X, not blocking".
6. **Commit** with message `Stage N: <short summary>` (only after the user confirms the
   report looks right, unless they've said to commit automatically).
7. **Stop.** Never auto-continue into the next stage — wait for explicit go-ahead.

## Notes

- If a stage's acceptance criteria can't be fully verified locally (e.g. needs a real phone,
  Pusher dashboard, or Hostinger deploy), say so explicitly rather than claiming it passed.
- If work from a stage was already partially done in a prior session, read what exists first
  and reconcile rather than redoing it.
