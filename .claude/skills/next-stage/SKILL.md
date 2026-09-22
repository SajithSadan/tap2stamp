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
   Requests, Pest tests colocated with the stage). Pages render via `Inertia::render()` from
   controllers into `resources/js/Pages/**/*.jsx` — no Blade views for app pages.
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
6. **Do not commit.** Stage the changes with `git add` if helpful, then give the user a
   ready-to-use commit message in the `Stage N: <short summary>` style — as its own clearly
   marked block they can copy — and stop there. Never run `git commit` or `git push` yourself.
7. **Stop.** Never auto-continue into the next stage — wait for explicit go-ahead.

## Notes

- If a stage's acceptance criteria can't be fully verified locally (e.g. needs a real phone,
  Pusher dashboard, or Hostinger deploy), say so explicitly rather than claiming it passed.
- If work from a stage was already partially done in a prior session, read what exists first
  and reconcile rather than redoing it.
- **Stage 3 (Hub Tiles) and Stage 4 (Owner Dashboard)**: check `CLAUDE.md`'s "In-house review
  & rating" section before touching either. The Google Review tile is already replaced with an
  in-app rating/review flow (`reviews` table, `ReviewController`, `RatingTile.jsx`) — this
  deviates from the original doc, it's not a gap to "fix" by re-adding the external link.
  Stage 4's owner dashboard should include a place to view collected reviews per customer
  (not built yet as of Stage 4 landing — the dashboard shipped with settings + staff devices
  only; reviews-per-customer is still open).
- **Stage 4 built an admin panel beyond the original doc** — see CLAUDE.md's "Admin panel"
  section before touching auth, `/admin`, or `/dashboard`. Owner accounts are created by an
  admin (`POST /admin/shops`), not `php artisan owner:create` (doesn't work without SSH on
  Hostinger). Dashboard routes never take a shop param - don't add one.
- **Stage 5 and Stage 6 were built together** — see CLAUDE.md's "Staff scanner & stamping"
  section before touching `/staff`, `AuthenticateStaffDevice`, or `StampService`. Onboarding
  (`/staff/setup/{token}`), the scanner shell + PWA basics, the bearer-token auth middleware,
  and the actual stamp/redeem endpoint (`StampService`) all already exist. `staff_devices` and
  the dashboard's add/revoke UI are from Stage 4, also don't recreate those.
- **Stage 7 is built** — see CLAUDE.md's "Real-time customer updates" section before touching
  `CardUpdated`, `StampService::scan()`'s post-transaction block, or Card.jsx's Pusher
  subscription. `config/broadcasting.php` was hand-written (no Reverb scaffolding). Don't
  re-add a `routes/channels.php` or broadcasting auth route - the channel is public, doesn't
  need one.
- **Stage 8 (Hardening/Deploy)**: `QUEUE_CONNECTION=sync` is already the default everywhere
  (see `.env.example`) and `ShouldBroadcastNow` (not `ShouldBroadcast`) was used specifically
  so Stage 7 never depended on a queue worker to begin with - nothing to change there. Deploy
  already has an SSH-free path (`/deploy/migrate`, `/deploy/seed-admin`) from Stage 4; Stage 8
  is mainly `config:cache`/`route:cache`/`view:cache` and the remaining hardening/QA checklist
  items, not new deploy infrastructure.
