# Loyalty Hub — Project Rules

This file is loaded automatically every session. It replaces pasting the Master Context by hand.

## Product

A web-based digital loyalty and social hub for UK high-street independents (cafes, bakeries,
barbers, pubs). Replaces paper punch cards and doubles as a customer engagement hub (Google
Review, Instagram, Wi-Fi).

## Stack (fixed — do not substitute)

- Laravel 12.x, PHP 8.2+, MySQL (MariaDB locally via XAMPP). Not Laravel 13.x — that requires
  PHP ^8.3, which the shared local XAMPP install (used by ~15 other projects) doesn't have.
- Inertia.js (`inertiajs/inertia-laravel` + `@inertiajs/react`) + React + Tailwind CSS v4 (via
  Vite, `@tailwindcss/vite`). **Client-side rendering only — no Inertia SSR**, since Hostinger
  shared hosting can't run a persistent Node SSR process.
- html5-qrcode for the staff camera scanner (wrapped in a thin React component when built).
- Pusher Channels for real-time (free tier).
- Deployment target: Hostinger shared hosting (no root, no long-running queue workers,
  no Redis, no Docker). So: `QUEUE_CONNECTION=sync`, CACHE/SESSION=file, no `artisan queue:work`,
  no websocket server of our own, no Inertia SSR process.

## Product rules

- Customers: no app, no passwords. First visit = name + UK mobile once; a persistent `uuid` is
  stored in `localStorage`. The browser is READ-ONLY: it can never change stamps.
- Staff: device authenticated via a long-lived bearer token stored in browser storage; the
  staff scanner is a PWA (Add to Home Screen).
- Customer QR payload is exactly: `TOKEN:{customer_uuid}|SHOP:{shop_id}`
- Multi-tenant: every query touching cards/stamps is scoped by `shop_id`.
- Cooldown: one stamp per customer per shop per configurable window (config value, default
  8 hours).
- UK context: `Europe/London` timezone, UK mobile number validation/normalisation (+44).

## Database (agreed schema)

Tables: `shops`, `customers`, `customer_shop_cards`, `stamp_logs`
(`action_type` enum: `stamp_added`, `reward_redeemed`). Use foreign keys, unique indexes
(`customers.uuid`, `customers.phone`, `shops.slug`, unique `customer_id+shop_id` on cards),
and a composite index `stamp_logs(shop_id, created_at)`.

## Working rules

- **Stage discipline**: work only on the current stage from
  `loyalty-hub-phase1-build-prompts.md`. Do not build features from later stages. Use the
  `/next-stage` skill to run the stage loop consistently.
- After finishing a stage: run migrations, run `php artisan test`, run `yarn run build`, and
  confirm the app boots with no errors. Fix anything failing before stopping.
- Write feature tests for the logic you add.
- Keep code simple and conventional (controllers, form requests, services where useful). No
  unnecessary packages.
- At the end of each stage, report: files changed, commands to run, and a manual test
  checklist (marking anything that needs Pusher keys or an HTTPS tunnel as pending until
  those stages).
- Never auto-continue to the next stage — stop and wait for explicit go-ahead.

## Reliability & performance practices

- Hard gate per stage: `php artisan migrate:fresh --seed`, `php artisan test`, and
  `yarn run build` must all pass before a stage is done.
- Git checkpoint (commit) at the end of each passing stage, message style `Stage N: <summary>`.
- Stamp/redeem path (Stage 6): use `lockForUpdate()` inside `DB::transaction()` to prevent
  double-scan races. Include a concurrent-double-scan test.
- Indexes exactly as specified above — no speculative extras.
- Pusher events (Stage 7): dispatch only after the DB transaction commits, wrapped in
  try/catch and logged. A broadcast failure must never break or roll back a stamp.
- The server trusts nothing from the client: strict QR payload regex, shop_id cross-check,
  uuid lookup — never derive authorization from client-supplied IDs alone.
- Deploy-time (Stage 8): `config:cache`, `route:cache`, `view:cache`. Confirm
  `QUEUE_CONNECTION=sync` everywhere; never run `queue:work`.
- Rate limit `register`, `scan`, and `staff/setup` routes.

## Conventions

- **Tests**: Pest. One feature test file per feature area, added in the same stage that
  introduces the behaviour. Feature tests hitting Inertia routes assert the rendered component
  via `assertInertia(fn ($page) => $page->component('...'))`, not just HTTP status.
- **Formatting**: Laravel Pint, run before each stage's commit.
- **Architecture**: thin controllers; business logic in Services (e.g. `StampService`);
  validation in Form Requests.
- **Frontend**: Pages live in `resources/js/Pages/`, one `.jsx` file per `Inertia::render()`
  call, PascalCase, mirroring the render path (e.g. `Inertia::render('Dev/Themes/Show')` →
  `resources/js/Pages/Dev/Themes/Show.jsx`). Shared UI in `resources/js/Components/`. No Blade
  `@extends`/`@yield` layouts for app pages — `resources/views/app.blade.php` is the single
  Inertia root template.
- **Package manager**: yarn, not npm — use `yarn add`/`yarn info` for anything touching
  `package.json` or querying the npm registry.
- **Commits**: `Stage N: <short summary>`.
- **Naming**: snake_case tables/columns, PascalCase models, kebab-case routes.
