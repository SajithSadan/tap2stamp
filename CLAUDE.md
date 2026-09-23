# Loyalty Hub — Project Rules

This file is loaded automatically every session. It replaces pasting the Master Context by hand.

## Product

A web-based digital loyalty and social hub for UK high-street independents (cafes, bakeries,
barbers, pubs). Replaces paper punch cards and doubles as a customer engagement hub (in-app
ratings/reviews, Instagram, Wi-Fi).

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
  staff scanner is a PWA (Add to Home Screen). On top of that, each staff member signs in on
  the device with their name + PIN, so every stamp records who gave it (see "Staff accounts").
- Customer QR payload is exactly: `TOKEN:{customer_uuid}|SHOP:{shop_id}`
- Multi-tenant: every query touching cards/stamps is scoped by `shop_id`.
- Cooldown: one stamp per customer per shop per configurable window (config value, default
  8 hours).
- UK context: `Europe/London` timezone, UK mobile number validation/normalisation (+44).
  Indian mobiles (+91, 10 digits starting 6-9) are also accepted. The registration sheet's
  country picker (`COUNTRIES` in `RegistrationModal.jsx`) and the `RegisterCustomerRequest`
  regex must stay in sync when adding a country.

## In-house review & rating (deviates from the original doc — built)

The original doc's Stage 3 "Google Review" tile just opened `shops.google_review_url`
externally. That's been replaced: customers rate/review **in-app** instead — the rating (1-5
stars) + optional comment is saved to our own `reviews` table. The end goal is still Google
reviews, but not an automatic one-to-one post of every submission: the plan is for the shop
owner/admin to **selectively** choose which collected reviews get pushed/posted publicly (e.g.
to Google) — that curation step isn't built yet (pending Stage 4's dashboard), so nothing is
auto-published today. Don't tell customers it's "never shared with Google" — that's not
accurate to the plan, and it isn't information they need anyway; customer-facing copy should
just say the feedback goes to the shop, nothing about where it may or may not end up later.

- Table: `reviews` (`customer_id`, `shop_id`, `rating` 1-5, `comment` nullable, timestamps;
  unique `customer_id+shop_id` — resubmitting updates the existing review, not a duplicate).
- Card page: `App\Models\Review`, `ReviewController::store()`
  (`POST /s/{shop:slug}/card/{customer:uuid}/review`), `RatingTile.jsx` (star UI). `shops.
  google_review_url` still exists in the DB (unused by the customer-facing UI now) in case a
  future admin view wants it as a reference link.
- The tile is deliberately **not** labelled "Google Review" — nothing is posted automatically,
  so calling it that would overpromise. Labelled "Rate your visit" / "Update your rating"
  instead.
- Requires an existing `customer_shop_card` (i.e. the customer has registered at this shop)
  before a review can be submitted — 404s otherwise.

## Mobile bottom nav & cross-shop "My Cards" (additive — not in the original doc)

A fixed mobile footer nav (`BottomNav.jsx`) on every customer-facing page, with two tabs:
"My Card" (back to the last shop visited, tracked via `localStorage.loyalty_last_shop_slug`,
set by `Card.jsx` on mount) and "My Cards" (`/my-cards`), which lists **every** shop the
customer (identified by the same `loyalty_uuid` used everywhere else) has a card at — each
with its stamp progress and an expand-to-reveal QR code, so a customer with cards at several
shops doesn't need to keep re-finding each shop's individual link.

- Route: `GET /my-cards` (Inertia page, no props) + `GET /my-cards/{customer:uuid}` (JSON,
  `MyCardsController::index()`) — same "uuid is the only identity, read client-side from
  localStorage" pattern as the card routes.
- No new DB table — reads existing `customer_shop_cards` scoped to the one customer.
- Shared localStorage key constants live in `resources/js/lib/storage.js`
  (`CUSTOMER_UUID_KEY`, `LAST_SHOP_SLUG_KEY`) so `Card.jsx`, `MyCards.jsx`, and `BottomNav.jsx`
  can't drift out of sync on the key names.

## Admin panel (additive — expands Stage 4 beyond the original doc)

The doc's Stage 4 plan for creating owner accounts (`php artisan owner:create`) doesn't work in
production: Hostinger has no SSH access, same reason `/deploy/migrate` exists. Instead there's a
real admin role: `users.role` (`admin` | `owner`, `App\Enums\UserRole`), one login form for
both, role decides where `AuthenticatedSessionController::store()` redirects
(`/admin` vs `/dashboard`). No self-service registration for either role.

- **Owner dashboard routes never take a shop param** — `/dashboard` always means
  `auth()->user()->shop`. One owner can't view another's data through this route by
  construction, not just because a policy happens to check it.
- **Admin** (`role:admin` middleware, `/admin`): `Admin\ShopOwnerController` — lists shops with
  their owner, and creates a new shop + owner together (`POST /admin/shops`). The owner's
  password is randomly generated (`Str::password(16)`) and flashed once via
  `session('generatedPassword')` — never chosen by the owner, never stored in plain text,
  never shown twice. The admin shares it with the owner out of band.
- **First admin account bootstrap**: same no-SSH problem as owner creation, solved the same way
  as `/deploy/migrate` — `POST /deploy/seed-admin` (same bearer token,
  `DeployController::seedAdmin()`) reads `ADMIN_EMAIL`/`ADMIN_PASSWORD` from env and
  `firstOrCreate()`s the admin. Idempotent: safe to call on every deploy, never overwrites an
  existing admin's password. Locally, `DatabaseSeeder` creates a dev admin instead
  (`admin@loyaltyhub.test` / `password`).
- **Staff devices** (Stage 4's other task, doc-scoped): `staff_devices` table, only
  `token_hash` (sha256) is ever persisted — the plain 64-char token is flashed once via
  `session('staffToken')` when a device is added, same one-time-reveal pattern as the owner
  password. `/staff/setup/{token}` (Stage 5) is what a staff phone actually lands on.

## Staff scanner & stamping (Stage 5 + 6, built together)

Built as one unit rather than two separate stages: Stage 5 alone (decode a QR, show the raw
payload) isn't independently useful — nothing lets a customer actually get a stamp until
Stage 6's endpoint exists too.

- **Onboarding**: `GET /staff/setup/{token}` (`StaffSetupController`) validates the raw token
  from the URL against `staff_devices.token_hash` — this route IS the login step, so unlike
  everything below it doesn't go through `AuthenticateStaffDevice`. The page stores the token
  into `localStorage.staff_token` (`STAFF_TOKEN_KEY` in `resources/js/lib/storage.js`) and
  redirects to `/staff`. Never a cookie.
- **Auth**: `AuthenticateStaffDevice` middleware (alias `staff.auth`) reads
  `Authorization: Bearer {token}`, hashes it, looks up a non-revoked `StaffDevice`, sets
  `$request->attributes->set('staffDevice', $device)`, updates `last_used_at`. 401 JSON
  otherwise. Registered on `api/staff/*`, which is bearer-token auth, not session/CSRF — those
  routes are in the CSRF-exempt list in `bootstrap/app.php`, same reasoning as `/deploy/*`.
- **Scanner shell**: `/staff` (now `Staff/Dashboard.jsx`'s Scan tab, see "Staff accounts" below) uses `html5-qrcode` directly (not the
  built-in `Html5QrcodeScanner` widget, to get a custom banner overlay instead of its default
  UI). Debounces identical decodes within 2s. Beep via Web Audio (no audio file asset) +
  `navigator.vibrate`, auto-dismiss after 3s. PWA: `public/manifest.webmanifest`, a minimal
  `public/sw.js` that caches only `/build/*` and `/icons/*` — **never** the HTML page or
  `/api/*`, so the scanner always sees fresh auth state and stamp counts. Icons generated via
  PHP's GD extension (see git history if regenerating).
- **`StampService::scan()`**: the only place stamp/redeem logic lives. Strict payload regex →
  shop match against the *authenticated device's* shop (never trust the QR's own SHOP: value
  alone) → `lockForUpdate()` inside `DB::transaction()` → full card redeems (resets to 0,
  `rewards_claimed++`, ignores cooldown) → cooldown check → otherwise stamps
  (`current_stamps++`, flags `reward_ready` if that fills the card). Returns `[httpStatus,
  body]` tuples, not exceptions — the controller just does
  `response()->json($body, $httpStatus)`.
- **`POST /api/staff/scan`**, **`GET /api/staff/me`**, **`GET /api/staff/summary`** — all thin,
  all delegate to the device on the request / to `StampService`.
- **Test coverage gap, disclosed rather than silently skipped**: "concurrent double-scan only
  stamps once" is only tested as the *sequential*-call invariant (two scans in a row within
  cooldown → exactly one stamp), not genuine cross-connection concurrency. Pest wraps every
  test in `RefreshDatabase`'s outer transaction, so a second, truly independent DB connection
  wouldn't see a test's data at all — that approach was tried and doesn't work here. The
  `lockForUpdate()` protection is real and correctly placed; only the *test* of true concurrency
  is the gap.

## Real-time customer updates (Stage 7)

- **`App\Events\CardUpdated implements ShouldBroadcastNow`** — broadcasts on the PUBLIC channel
  `card.{customer_uuid}.{shop_id}` (no auth needed, so no `routes/channels.php` — public
  channels don't call back to the server), event name `card.updated`. Payload is deliberately
  minimal: `stamps`, `max_stamps`, `action` (`stamp_added` | `reward_redeemed`), `reward_ready`
  — no name, no phone.
- **Dispatch site**: `StampService::scan()`, *after* `DB::transaction()` returns (i.e. after
  commit), wrapped in try/catch → `Log::error()` on failure. Only for `stamp_added` and
  `reward_redeemed` — a rejected scan (cooldown, shop mismatch, etc.) never broadcasts. A
  broadcast failure **never** affects the HTTP response or rolls back the stamp — the stamp
  already committed before the broadcast is even attempted.
- **`config/broadcasting.php`** was hand-written (not via `php artisan install:broadcasting`,
  which pulls in Reverb scaffolding this project doesn't use) — just `pusher`, `log`, `null`
  connections. `PUSHER_APP_ID/KEY/SECRET/CLUSTER` and `VITE_PUSHER_APP_KEY/CLUSTER` were already
  present in `.env`/`.env.example` from Phase A.
- **Card.jsx**: subscribes via `pusher-js` directly (not Laravel Echo — one channel, one event,
  Echo would be pure overhead) once `card.uuid`/`card.shop_id` are known. Guards on
  `import.meta.env.VITE_PUSHER_APP_KEY` being set and wraps the subscribe in try/catch — if
  Pusher isn't configured or the connection fails, the page still works via the normal
  fetch-on-load path, nothing else depends on the socket. Also re-fetches the card on
  `visibilitychange` (tab/app returning to foreground) to self-correct from any missed events.
- **Chime**: one `AudioContext` created at the first tap of the "Tap to enable live sound
  updates" hint (`enableSound()`), stored in a ref and reused for every subsequent chime —
  mobile autoplay policy only requires the gesture for the context's *creation*, not for each
  sound played through it afterwards. If never tapped, `playChime()` just no-ops (ref is null) -
  the visual/stamp update still happens either way.
- **Confetti**: reuses the existing `Celebration` sparkle-burst component (built in the UI-polish
  pass, originally only for the reward-ready transition) - now triggered on *every* `card.updated`
  event via a shared `triggerCelebration()` helper, not a second particle system.
- **Reward-redeemed toast**: a separate transient banner (`redeemedToast` state, auto-hides
  after 3s) — redemption resets `stamps` to 0, so the persistent "reward ready" banner
  disappears the moment it happens, which would otherwise make a scan-triggered redemption
  invisible to the customer without something else announcing it.

## Hardening & QA (Stage 8 — built; hosting deliberately out of scope)

Stage 8's deployment half (DEPLOYMENT.md, hPanel layout) was skipped on purpose: the user is
handling Hostinger themselves. `shop:create` was also skipped, because the admin panel already
creates shops and an artisan command can't run without SSH.

- `SecurityHeaders` middleware is appended globally, not per group, so 404s for unmatched
  routes get the headers too. There's no CSP yet: it would need Google Fonts, Pusher and
  Vite allow-listed.
- The scan throttle is the named limiter `staff-scan` in `AppServiceProvider`, keyed by the
  sha256 of the bearer token, not by IP. Every phone in a shop shares one Wi-Fi IP.
- `URL::forceScheme('https')` applies in production only.
- Friendly errors: `bootstrap/app.php` renders `Pages/Error.jsx` for browser and Inertia
  requests. JSON callers keep JSON bodies. 500/503 only get the error page when
  `APP_DEBUG=false`.
- The client must never forget stored identity on a transient failure. `Card.jsx` only clears
  `loyalty_uuid` on a 404, and `Staff/Dashboard.jsx` only clears `staff_token` on a 401. Anything else
  shows a retry state.
- The dashboard's activity feed shows name, action and staff name only, never a phone number.

## Staff accounts & dashboards (additive — changes the Stage 5 device model)

Devices alone didn't say *who* gave a stamp, so staff now have their own accounts on top of the
owner-approved device.

- **Two layers**: the device is still approved once by the owner (setup QR →
  `localStorage.staff_token`, `AuthenticateStaffDevice`, unchanged). On that device a staff
  member then picks their name and enters a 4-6 digit PIN (`POST /api/staff/sign-in`,
  throttled by the `staff-pin` limiter: 5/min per device). The sign-in is stored on the device
  row (`staff_devices.staff_member_id` + `staff_signed_in_at`) and expires after
  `config('loyalty.staff_session_hours')` (default 12). `StaffDevice::activeStaffMember()` is
  the single check for this.
- **`EnsureStaffSignedIn`** (alias `staff.signed-in`) guards scan, summary and customer lookup.
  It answers **403 `staff_signed_out`**, not 401, so the client goes back to the PIN screen
  without forgetting the device token.
- **Attribution**: `StampService::scan()` takes the signed-in `StaffMember` and writes
  `stamp_logs.staff_member_id`. It's nullable, because older logs have no staff member.
- **Owner manages staff** at `/dashboard/staff` (`StaffMemberController`): add (name unique
  per shop + PIN), reset PIN, remove. Removing sets `deactivated_at` (a soft delete, so past
  stamps keep the name) and signs them out of every device. PINs are hashed (`pin_hash`); the
  owner tells staff their PIN in person.
- **Staff dashboard** (`/staff` → `Staff/Dashboard.jsx`): PIN sign-in, then three tabs: Scan
  (camera only mounted while open), Customers (read-only lookup by name or ≥3 phone digits via
  `GET /api/staff/customers`, this shop only, phone shown as last 3 digits) and Today (my
  numbers vs the whole shop's). Lookup can't stamp; stamping still needs the customer's QR.
- **Owner dashboard** is now sidebar + sections, one Inertia page each, all in
  `DashboardController`: Overview (stat tiles, 14-day stamps-per-day chart, last 5 activity),
  Customers (search, no phones), Activity (paginated 15), Reviews (average + breakdown +
  list), Staff, Settings (form + counter QR download). Shell: `Components/Dashboard/OwnerLayout.jsx`.
  Still never a shop param in the URL.
- Dev seeder: Artisan Cafe has staff `Sam` (PIN 1234) and `Alex` (PIN 5678).

## Per-shop themes (additive)

- The 50-theme catalog lives in `App\Support\ThemeCatalog` (moved out of the dev-only
  `ThemePreviewController`, which now just reads it). `ThemeCatalog::DEFAULT` is
  `monochrome-barber` = the site look in `app.css`.
- `shops.theme` (nullable slug) is set on `/dashboard/theme` (`Dashboard/Theme.jsx`: filters,
  live phone preview, `PUT /dashboard/theme` validated with `Rule::in` the catalog keys).
  `ThemeCatalog::forShop()` falls back to the default for null/unknown slugs.
- Applied **only on the customer card page** (`/s/{slug}`, incl. the registration sheet):
  `CardController::show()` passes `theme`, `Card.jsx` calls `useDocumentTheme()` from
  `resources/js/lib/theme.js`, which re-points the `--color-brand-*`, `--radius-brand` and font
  variables on `<html>` and loads the theme's Google Fonts. The owner dashboard, staff app and
  cross-shop `/my-cards` keep the default look.
- Because themes can be dark, customer-page code must not use `brand-text` as a "dark"
  colour (it's light on dark themes) - use a fixed neutral (e.g. `neutral-900/950`) for
  always-dark surfaces like the registration backdrop and card banner.
- Dev-only custom themes (`custom_themes` table) are not offered to owners.
- **Reset**: `DELETE /dashboard/theme` sets `shops.theme` back to null (not to the default
  slug), so a future change of `ThemeCatalog::DEFAULT` still applies to those shops.
- **Dashboard opt-in**: `shops.theme_in_dashboard` (bool, default false), toggled via
  `PUT /dashboard/theme/dashboard`. `DashboardController::shopSummary()` sends
  `dashboard_theme` (the theme, or null) on every section, and `OwnerLayout` passes it to
  `useDocumentTheme()`. The staff app never uses the shop theme.
- **Owner customisation** (Theme page → Customise tab): `shops.theme_custom` (JSON, null =
  catalog theme as-is) holds 7 colours (`ThemeCatalog::CUSTOM_COLORS`), heading/body font
  (`CuratedFonts`) and card radius (`CustomTheme::RADIUS_PRESETS`), validated by
  `ThemeCatalog::customRules()`. It's layered on top of `shops.theme` by
  `ThemeCatalog::forShop()`; always read the look via `Shop::appliedTheme()`. Picking a new
  catalog theme or "Reset to default" clears it; `DELETE /dashboard/theme/custom` clears only
  the tweaks. The client-side preview twin is `withCustomisation()` in `lib/theme.js`.
  Separate from the dev tool's global `custom_themes` table - owners' tweaks belong to their
  own shop only.
- **Stamp icon**: `shops.stamp_icon` (null = tick), keys in `App\Support\StampIcons::KEYS`,
  mapped to react-icons in `resources/js/lib/stampIcons.jsx` - **keep the two lists in sync**.
  Shown in filled stamps on `Card.jsx` and `MyCards.jsx` (`MyCardsController` sends it per card).
- **Banner image** (Theme page → Banner tab, `ShopBannerController`): `shops.banner_path` on
  the **`uploads` disk** (`config/filesystems.php`), which writes straight into
  `public/uploads` - not the `public` disk, because that needs `artisan storage:link` and
  Hostinger has no SSH. URLs are relative (`/uploads/...`); if the host's web root isn't
  the project's `public/`, set `UPLOADS_ROOT`. JPG/PNG/WebP only (never SVG), ≤ 4 MB,
  ≥ 600×200, stored under a random name; replacing or removing deletes the old file.
  Read it via `Shop::bannerUrl()`. Shown as the card page header (`Card.jsx`) and, blurred
  and darkened, as the registration backdrop (`RegistrationModal` `bannerUrl` prop).
  `public/uploads` is gitignored.


## Database (agreed schema)

Tables: `shops`, `customers`, `customer_shop_cards`, `stamp_logs`
(`action_type` enum: `stamp_added`, `reward_redeemed`). Use foreign keys, unique indexes
(`customers.uuid`, `customers.phone`, `shops.slug`, unique `customer_id+shop_id` on cards),
and a composite index `stamp_logs(shop_id, created_at)`. Plus `reviews` (see above) — additive,
not part of the original 4-table design. Plus (Stage 4, see "Admin panel" above): `users.role`,
`shops.user_id` (nullable FK — shops created before the admin panel existed have no owner),
`staff_devices` (`shop_id`, `name`, `token_hash` unique, `last_used_at`, `revoked_at`).
Plus (staff accounts, see above) `staff_members` (`shop_id`, `name` unique per shop,
`pin_hash`, `deactivated_at`), `staff_devices.staff_member_id` + `staff_signed_in_at`, and
`stamp_logs.staff_member_id` (nullable FK).
Plus `customer_shop_cards.marketing_consent` (bool, default false) and `marketing_consent_at`
(timestamp). This is an optional opt-in to texts from **that one shop**, unticked by default,
and customers can register without it. `CustomerRegistrar` only ever turns it on: an unticked
box on a repeat registration is not a withdrawal. There's no opt-out UI and no SMS sending yet.

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
- **Never run `git commit` (or `git push`).** Once work is verified (tests/build green),
  stage the changes with `git add` if helpful, then give the user a ready-to-use commit
  message (following the `Stage N: <summary>` / descriptive style already used in this repo)
  and stop. The user commits manually. This applies to every change from here on, not just
  stage work.

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
- **Icons**: use `react-icons` for every icon. Don't hand-write inline `<svg>` icons. The only
  exception is artwork that `react-icons` doesn't have, such as the country flags in
  `RegistrationModal.jsx`.
- **Package manager**: yarn, not npm — use `yarn add`/`yarn info` for anything touching
  `package.json` or querying the npm registry.
- **Commits**: `Stage N: <short summary>`.
- **Naming**: snake_case tables/columns, PascalCase models, kebab-case routes.
