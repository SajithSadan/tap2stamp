# Tada Tap — Project Rules

This file is loaded automatically every session. It replaces pasting the Master Context by hand.

## Product

A web-based digital loyalty and social hub called Tada Tap for UK high-street independents (cafes, bakeries,
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
  8 hours) - unless the shop has the **"Multiple stamps a day"** feature switch on (platform
  default off, per-shop override): then only `loyalty.multi_stamp_gap_minutes` (2) apart, so a
  double scan still never counts twice.
- UK context: `Europe/London` timezone, UK mobile number validation/normalisation (+44).
  The customer sign-up's country picker (`RegistrationModal.jsx`) offers **every country**
  (`Countries::options()`, sent as `phoneCountries` by `CardController`), with a search box,
  starting on the shop's own country (`shop.country`); flags are flagcdn.com images (Windows
  can't show flag emoji). Numbers: a UK mobile (+447…) and Indian mobile (+91, 10 digits
  starting 6-9) exactly, any other known code + 4-14 digits (8-15 in all).
  `RegisterCustomerRequest::validMobile()` ↔ `RULES` in `RegistrationModal.jsx` — keep in sync.

## In-house review & rating (deviates from the original doc — built)

By default, customers rate/review **in-app** — the rating (1-5 stars) + optional comment is
saved to our `reviews` table and, when `shops.google_review_url` is configured, they can
optionally share it on Google after submitting. Owners can instead enable
`shops.google_review_direct` in Settings: in that mode the customer card action opens the
configured Google review URL directly and skips in-app feedback. This is a per-shop mode for
all customers, never selected based on an individual rating; do not implement rating-based
review gating. Google reviews must be submitted by customers from their own Google accounts.

- Table: `reviews` (`customer_id`, `shop_id`, `rating` 1-5, `comment` nullable, timestamps;
  unique `customer_id+shop_id` — resubmitting updates the existing review, not a duplicate).
- Card page: `App\Models\Review`, `ReviewController::store()`
  (`POST /s/{shop:slug}/card/{customer:uuid}/review`), `RatingTile.jsx` (star UI).
  The default **Share feedback** action opens the in-app rating dialog and persists the feedback;
  it may then offer a separate **Share on Google too** action. When `google_review_direct` is on
  and a URL exists, the action links directly to Google instead. The post-stamp feedback prompt
  is only used in in-app feedback mode.
- Never condition Google-link visibility on rating: showing it only for high ratings is review
  gating. In direct mode the same Google link is shown to all customers regardless of any
  previous feedback.
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
(`User::homeUrl()`: `/admin`, `/dashboard`, or `/onboarding` for an owner with no shop yet).
Admins are never self-registered; owners can be created by the admin **or** sign up themselves
(see "Self-service sign-up" below).

- **Owner dashboard routes never take a shop param** — `/dashboard` always means
  `auth()->user()->shop`. One owner can't view another's data through this route by
  construction, not just because a policy happens to check it.
- **Admin** (`role:admin` middleware, `/admin`): `Admin\ShopOwnerController` — lists shops with
  their owner, and creates a new shop + owner together (`POST /admin/shops`). "Add shop" is a
  button on the Shops page, not a menu item (the Shops item stays active on it).
- **Data grids** use `Components/Dashboard/DataTable.jsx` on **TanStack Table v8** (headless —
  chosen over AG Grid for size (~15 KB vs 300 KB+) and so it wears our own design). One search
  box (no per-column filter inputs — the user removed them), multi-sort, column show/hide +
  page size remembered in localStorage, sticky header/first column, CSV export of the rows
  shown (formula-looking cells neutralised). Search is `type="text"` on purpose —
  `type="search"` adds a second ✕. The Shops grid
  is client-side (all rows sent) — fine for hundreds of shops; switch to server-side paging if
  it ever reaches thousands. Shop status: active / quiet (no stamps for 14 days) / not started.
- **Add shop** (`Admin/Create.jsx`, redone 2026-10-01): shop name, card link, owner email +
  password, stamp card (count, reward, stamp icon) and theme, with a live themed preview.
  No owner-name field — `users.name` starts as the shop name. The **admin** sets the password
  (typed, or "Generate" = 14 chars, client-side, no look-alikes; min 8); it's flashed once via
  `session('generatedPassword')` so it can be copied with the email, never stored in plain
  text, never shown twice. The card link follows the shop name until edited and is checked
  live via `GET /admin/shops/slug` (`Shop::suggestSlug()` → `name`, `name-2`, …): a taken link
  is swapped for the free one automatically while the admin hasn't typed their own. Picking
  the default theme / tick icon stores null (same reason as theme reset).
- **First admin account bootstrap**: same no-SSH problem as owner creation, solved the same way
  as `/deploy/migrate` — `POST /deploy/seed-admin` (same bearer token,
  `DeployController::seedAdmin()`) reads `ADMIN_EMAIL`/`ADMIN_PASSWORD` from env and
  `firstOrCreate()`s the admin. Idempotent: safe to call on every deploy, never overwrites an
  existing admin's password. Migrations can also be run by opening
  `GET /api/deploy/migrate` in a browser (`routes/api.php`, the file for outward-facing
  routes; in-app routes stay in `web.php`). No token and no sign-in, by the user's choice:
  a pending migration can break admin login itself. Still only `migrate --force`,
  throttled 5/min (`DeployController::browserMigrate()`). Locally, `DatabaseSeeder` creates a dev admin instead
  (`admin@loyaltyhub.test` / `password`).
- **View as owner** (admin support): eye button on the Shops grid / "View as owner" on a shop's
  settings page → `POST /admin/shops/{shop}/view-as-owner` puts `view_as_shop_id` in the admin's
  session. The `ViewAsOwner` web middleware then, **on `dashboard.*` routes only**, swaps in the
  shop's owner for that request (`Auth::setUser`, never written to the session, restored to the
  admin afterwards), so every owner screen is the real one. Starts **read-only** (anything but
  GET → `view_as` validation error / 403 JSON); the banner's **Allow changes** switch
  (`PUT /admin/view-as-owner/editing`, `view_as_editing`, reset on every new view) lets the admin
  set up staff, devices, settings, theme for a non-technical owner. Even then `OWNER_ONLY`
  routes stay blocked: Stripe checkout / coupon (use Record order) and the owner scanner (stamps
  would be credited to the owner). Every change made as the owner is in the activity log as
  the admin, `as_owner` (the middleware's `viewAsAdmin` request attribute). Shared `viewAs` prop
  (incl. `editing`) → amber (read-only) / red (editing) banner in `OwnerLayout` with
  "Back to admin" (`POST /admin/view-as-owner/stop`). Owner dashboard routes still take no shop param.
- **Users** (`/admin/users`, `Admin\UserController`, `Pages/Admin/Users.jsx`, menu "Users",
  desktop menu only): every login, incl. **owners with no shop** - sign-up (`/register`) saves the
  user first and the shop only on the last `/onboarding` step (in a transaction), so an abandoned
  or failed setup leaves a user without a shop, invisible on the Shops grid. Owner `setup` =
  `live` / `business` (step 1 saved in `onboarding_draft`; its business name, phone and town are
  shown to follow up) / `not_started`. Never sends password, remember token, Google id or the raw
  draft. Those owners resume setup with their details when they log in again.
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
  `/api/*`, so the scanner always sees fresh auth state and stamp counts. Icons (`public/icons/`) are
  made from the app icon `public/images/favicon-512.png` with PHP GD: `icon-192/512` = the icon
  as-is (`purpose: any`); `icon-maskable-192/512` and `apple-touch-icon` (180, opaque, also the
  site-wide one) = the logo lifted off its background onto the same navy → black gradient,
  logo at 80% for maskable (Android's safe zone). Bump `CACHE_NAME` in `sw.js` when they change
  - the SW serves `/icons/*` cache-first.
- **`StampService::scan()`**: the only place stamp/redeem logic lives. Strict payload regex →
  shop match against the _authenticated device's_ shop (never trust the QR's own SHOP: value
  alone) → `lockForUpdate()` inside `DB::transaction()` → a full card is **not** redeemed by a
  scan: it answers `reward_ready` (nothing changes) and the staff member / owner confirms with
  "Mark reward as given" = the same payload with `redeem: true` → redeem (resets to 0,
  `rewards_claimed++`, ignores cooldown); `redeem` on a card that isn't full → 409 `no_reward`,
  never a stamp (so a double scan / double tap can't lose a reward) → cooldown check → otherwise stamps
  (`current_stamps++`, flags `reward_ready` if that fills the card). Returns `[httpStatus,
body]` tuples, not exceptions — the controller just does
  `response()->json($body, $httpStatus)`.
- **Scanner UI** (`Components/CardScanner.jsx`, used by the staff Scan tab and the owner's
  `OwnerScanner`): one scan at a time - the camera closes after a decode and a result screen
  (✓ / ⚠ / ✕, customer, stamp dots) offers **Scan next**; a full card shows a **Reward ready**
  screen (reward title, "Mark reward as given" / "Not now"); a stamp that fills the card also
  offers "Give the reward now". The customer's card page shows a **"Your reward is ready!"**
  popup when the card is full (on opening, or after a live stamp's animation; that stamp skips the
  review prompt) with "Show my QR code" (the full-screen QR) / "Later"; it closes itself once the
  reward is given.
- **`POST /api/staff/scan`**, **`GET /api/staff/me`**, **`GET /api/staff/summary`** — all thin,
  all delegate to the device on the request / to `StampService`.
- **Test coverage gap, disclosed rather than silently skipped**: "concurrent double-scan only
  stamps once" is only tested as the _sequential_-call invariant (two scans in a row within
  cooldown → exactly one stamp), not genuine cross-connection concurrency. Pest wraps every
  test in `RefreshDatabase`'s outer transaction, so a second, truly independent DB connection
  wouldn't see a test's data at all — that approach was tried and doesn't work here. The
  `lockForUpdate()` protection is real and correctly placed; only the _test_ of true concurrency
  is the gap.

## Real-time customer updates (Stage 7)

- **`App\Events\CardUpdated implements ShouldBroadcastNow`** — broadcasts on the PUBLIC channel
  `card.{customer_uuid}.{shop_id}` (no auth needed, so no `routes/channels.php` — public
  channels don't call back to the server), event name `card.updated`. Payload is deliberately
  minimal: `stamps`, `max_stamps`, `action` (`stamp_added` | `reward_redeemed`), `reward_ready`
  — no name, no phone.
- **Dispatch site**: `StampService::scan()`, _after_ `DB::transaction()` returns (i.e. after
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
  mobile autoplay policy only requires the gesture for the context's _creation_, not for each
  sound played through it afterwards. If never tapped, `playChime()` just no-ops (ref is null) -
  the visual/stamp update still happens either way.
- **Confetti**: reuses the existing `Celebration` sparkle-burst component (built in the UI-polish
  pass, originally only for the reward-ready transition) - now triggered on _every_ `card.updated`
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
- **Card page preview**: `/s/{slug}?preview=1` (every admin / owner "open customer page"
  link) renders the real look with a sample card from `CardController::previewCard()`. It
  never reads or writes `loyalty_uuid` / `loyalty_last_shop_slug`, never shows sign-up or
  join, doesn't post feedback, skips Pusher and hides `BottomNav`. Wi-Fi details are
  included only for that shop's owner or an admin (anyone can add `?preview=1`). Shareable
  card links (copy buttons, counter QR) stay without it.

## Staff accounts & dashboards (additive — changes the Stage 5 device model)

Devices alone didn't say _who_ gave a stamp, so staff now have their own accounts on top of the
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

## Overseas shops: QR download in a design (additive)

Physical products (counter display, stickers) are UK only (`Shop::canOrderProducts()` =
country is UK). Owners elsewhere get **QR codes** in their menu (`dashboard.qr-codes`) to
download (PNG / sticker-size PDF, drawn by the same renderer as admin prints).

- **Issued automatically**: the first time an overseas owner opens that page with no codes,
  `QrCodeGenerator::issueFor()` creates one permanent `/qr/{code}` (batch "Overseas shops",
  mapped to their card link, `shop_id` set) - under a row lock so two tabs can't issue two.
  The admin sees / remaps / reprints it like any sticker; the card link is never shown.
- **Design**: `Shop::downloadDesign()` = the shop's own `qr_design_id`, else the **default
  design** (`qr_designs.is_default`, at most one; ★ on Admin → QR codes → Designs,
  `PUT /admin/qr-codes/designs/{qrDesign}/default`), else a plain QR.

## Feature switches (additive - Menu, WhatsApp, Multiple stamps a day)

`App\Support\Features` is the registry (`ALL`: key → label, description). Each feature has a
**platform default** (Admin → Settings → Features, `Setting::FEATURES` = `{menu: bool, ...}`,
**never set = on**, so adding the switches changed nothing) and an optional **per-shop override**
(`shops.features` JSON, missing key = default; Admin → shop settings → Features tab:
Default / On / Off). Always ask `Shop::hasFeature($key)` (override ?? default).

- Off = gone from the owner's menu (`'feature' => Features::X` on the Navigation entry), its owner
  routes 404 (`feature:menu` / `feature:whatsapp` middleware, `EnsureShopFeature`), and for Menu
  the public `/menu/{slug}` 404s too (nothing is deleted - switching on brings it all back).
  WhatsApp unsubscribe links (`/u/{token}`) are outside the gate and always work.
- The **admin** can still open a shop's menu editor while Menu is off (it shows a notice) to
  prepare a menu before switching it on.
- Switching a default **off** gives every shop already using it (`Features::inUse()`: has menu
  sections / has sent a campaign) its own `on` override, so nobody loses what they built.
- Each entry in `Features::ALL` has its own `default` (used until the admin sets the platform
  default: Menu / WhatsApp `true`, Multiple stamps `false`) and `keep_in_use` (only Menu /
  WhatsApp keep shops already using them on when the default is switched off; `inUse()` is null
  for the rest). **Multiple stamps a day** isn't a page - `StampService::scan` reads
  `Shop::hasFeature(Features::MULTIPLE_STAMPS)` to swap the 8-hour wait for a 2-minute gap.
- Adding a feature: `Features::ALL` + `inUse()` + the nav entry's `feature` + `feature:` on its
  routes (+ any public page).

## Owner Insights (additive)

`/dashboard/insights` (`DashboardController::insights`, `Pages/Dashboard/Insights.jsx`, nav
"Insights"); all the maths lives in `App\Services\ShopInsights` (one shop's `stamp_logs` +
cards, no new tables). A **visit = a day a customer was scanned** (stamp or reward). Per-customer
figures are grouped in SQL - one row per customer, never per scan.

- **Sections** (each a lazy prop; `regulars`, `loyalty`, `busy` have their own 30/90/365-day
  switch via `?regulars=30`, reloading only that card, like the admin dashboard):
  regulars (top 10 by visits), close to a reward (ready + 1-2 to go), **due back / drifting**,
  loyalty (second-visit rate, first vs returning visits per day/week/month, card health),
  busy times (weekday x hour scans; the Heatmap `hours` prop trims it to the shop's hours).
- **Due back / drifting** come from each regular's own rhythm over the last 365 days: 3+ visits
  and usually back within 45 days; average gap = days between first and last visit / (visits-1).
  Drifting = away >= max(2 x gap, 14 days) and <= 120 days (beyond = gone). Due = next expected
  visit within 7 days (incl. a little late), not if they came in today.
- **Second-visit rate**: customers whose first visit fell in the period *ending 30 days ago*
  (so all had the full 30 days), who came back within 30 days.
- **Card health**: median days to fill a card (from the previous reward, or first visit), share
  who ever earned a reward, where cards untouched for 60+ days stopped; suggests fewer stamps
  only with 20+ customers, < 25% ever earning and most stalls in the first half.
- Every customer row shows the phone (as on Customers) and marks **marketing opt-in**
  (`marketing_consent`) - the list a future WhatsApp Business feature should message.

## Per-shop themes (additive)

- The 50-theme catalog lives in `App\Support\ThemeCatalog` (moved out of the dev-only
  `ThemePreviewController`, which now just reads it). `ThemeCatalog::DEFAULT` is
  `tap2stamp` (the persisted legacy slug for the Tada Tap brand: navy + mint green, Poppins —
  the look of the marketing site currently at tadatap.co.uk) = the site look in `app.css` and
  the fonts in `app.blade.php`. **Keep all
  three in sync** when the brand colours change.
- `shops.theme` (nullable slug) is set on `/dashboard/theme` (`Dashboard/Theme.jsx`: filters,
  live phone preview, `PUT /dashboard/theme` validated with `Rule::in` the catalog keys).
  `ThemeCatalog::forShop()` falls back to the default for null/unknown slugs.
- Applied on the **customer card page** (`/s/{slug}`, incl. the registration sheet):
  `CardController::show()` passes `theme`, `Card.jsx` calls `useDocumentTheme()` from
  `resources/js/lib/theme.js`, which re-points the `--color-brand-*`, `--radius-brand` and font
  variables on `<html>` and loads the theme's Google Fonts. And on **`/my-cards`**:
  `MyCardsController` sends each card's `theme`; the page wears the last-visited shop's theme
  (`LAST_SHOP_SLUG_KEY`, else the first card's) and each tile is scoped to its own shop's theme
  via `themeVars()`. The owner dashboard (unless opted in) and staff app keep the default look.
- Because themes can be dark, customer-page code must not use `brand-text` as a "dark"
  colour (it's light on dark themes). For always-dark brand surfaces (card banner,
  registration backdrop, owner/admin sidebar, sign-up panel) use **`brand-deep`** — the theme's
  `deep` colour, always paired with white text. Tada Tap sets it to its navy; every other
  theme falls back to `ThemeCatalog::DEFAULT_DEEP` (near-black, the old look), added in
  `ThemeCatalog::all()` so every theme array always has it. Owners can customise it (it's in
  `CUSTOM_COLORS`). The owner/admin **sidebar and mobile tab bar** use the calmer
  `brand-deep-soft` (`--color-brand-deep` tinted with 7% of the accent — **never mixed with
  white or made translucent**, that reads as a milky overlay) and, for the sidebar, the
  `bg-brand-nav` utility (same colour + a faint accent glow), all in `app.css`. Use them via the
  shared `navSurface` / `sideLinkClass` / `tabLinkClass` in `Components/Dashboard/Ui.jsx` so
  desktop and phone stay the same colour.
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
  Icons are **solid** (Phosphor fill, plus 4 solid game-icons Phosphor lacks). Every stamp grid
  (card page, My Cards, Theme preview) is drawn by `Components/StampGrid.jsx`: pressed-ink
  filled stamps (accent gradient, inner ring, fixed per-stamp tilt), dashed empty slots with a
  ghost icon, balanced rows (`stampColumns()`) as a real grid (every row starts from the left).
  **Every slot is a stamp**; the **last slot shows a gift** (user's choice, 2026-09-30): empty =
  accent-tinted circle with a gift, filled = ink stamp with a gift. It's still the Nth stamp
  that fills the card - the reward is claimed on the scan after it.
  Card page header with no logo shows the shop's stamp icon (storefront for generic ones:
  check/star/heart/sparkles/gift).
- **Sign-up icon**: `shops.signup_icon` (null = neutral `sparkles`), the small decorative icon
  under the registration form (`RegistrationModal` `signupIcon` prop), picked on Theme →
  Sign-up icon. Separate from the stamp icon, from its own set of solid game-icons (same style
  and size as the old hard-coded coffee beans), grouped by business type. Keys in
  `App\Support\SignupIcons::KEYS` ↔ `resources/js/lib/signupIcons.jsx` — **keep in sync**.
- **Banner image** (Theme page → Banner tab, `ShopBannerController`): `shops.banner_path` on
  the **`uploads` disk** (`config/filesystems.php`), which writes straight into
  `public/uploads` - not the `public` disk, because that needs `artisan storage:link` and
  Hostinger has no SSH. URLs are relative (`/uploads/...`); if the host's web root isn't
  the project's `public/`, set `UPLOADS_ROOT`. JPG/PNG/WebP only (never SVG), ≤ 4 MB,
  ≥ 600×200, stored under a random name; replacing or removing deletes the old file.
  Read it via `Shop::bannerUrl()`. It fills the card page header (`Card.jsx`), under a solid
  dark tint, with the logo (white rounded square), shop name and reward beside it - no motion;
  no banner = plain `brand-deep`. No location / member number (we don't have them). Below it,
  in order: the progress card (overlapping the header: count, "N to go", bar, stamps), the QR
  card (tap to enlarge full-screen white), then the Follow / Rate us / Wi-Fi tiles. Under a solid dark tint (no blur, no
  glows), it's also the registration backdrop (`RegistrationModal` `bannerUrl` prop; no
  banner = plain `brand-deep`). The sign-up header shows the logo (only if uploaded - no
  placeholder icon), shop name and the reward title as a plain line (no pill). `public/uploads` is gitignored.
- **Header text** (Theme → Banner & logo → Header text, `PUT /dashboard/theme/header`):
  `shops.header_style` (JSON, null = defaults) = title/reward `text_color`, banner `tint`
  (0-80 % black over the photo) and title `shadow`. `App\Support\HeaderStyle` validates +
  resolves it (read via `Shop::headerStyle()`); `resources/js/lib/headerStyle.js` applies it on
  `Card.jsx` and the Theme page's phone preview — **keep the defaults in sync**. For banners
  where white text doesn't read.
- **Sidebar colours** (admin, app-wide): Admin → Settings → Sidebar colours
  (`PUT /admin/settings/sidebar`) stores `Setting::SIDEBAR_COLORS` = `{bg, text}` (either null =
  default); shared to every page as `sidebarColors`. `useSidebarColors()` (`Dashboard/Ui.jsx`)
  sets `--nav-bg` / `--nav-text` on `<html>`, read by `bg-brand-nav`, `bg-nav` and
  `text-nav-text` (`app.css`). Sidebar / tab-bar code must use those, never `text-white` or
  `bg-brand-deep-soft`. Owners who put their shop theme on the dashboard keep their theme's colours.
- **Logo** (Theme → Banner & logo, `ShopLogoController`, `shops.logo_path`, same `uploads` disk
  under `logos/{shop}`, JPG/PNG/WebP ≤ 2 MB, ≥ 120×120, uploads on pick): shown in the round
  badge in the card page header and on the sign-up screen (`Shop::logoUrl()`, `logo_url`)
  instead of the store icon; no logo = the store icon on the card page, nothing on sign-up. Card page sections are solid panels
  (`SURFACE` in `Card.jsx`, hairline border, no shadows). Don't bring back blurred backdrops,
  glows or translucent panels over the plain page - the user found them smoky.

## Bulk QR stickers (additive — admin only)

Printable QR stickers whose destination is decided later. The QR only ever holds the permanent
link `/qr/{code}`, never the destination itself, so remapping never needs a reprint.

- **Tables**: `qr_batches` (`name` nullable; the batch number is its id) and `qr_codes`
  (`qr_batch_id`, `code` unique, `destination_url` nullable = unmapped, `mapped_at`).
- **Stickers print the QR only — no code label** (customers see them). The admin identifies and
  maps a sticker by **scanning it while logged in**: `QrRedirectController` renders
  `Admin/QrCodes/Scan` for an admin instead of redirecting. Everyone else (incl. owners) gets
  the normal redirect / Nothing found. Login uses `redirect()->intended()` so an admin bounced
  to `/login` returns to where they were. The admin can also scan **inside the app**: "Scan
  sticker" (QR codes page) / "Scan next sticker" (mapping screen) / the raised **Scan** button
  in the middle of the admin's phone footer (`AdminLayout`, menu split around it) open
  `Components/QrStickerScanner.jsx` (html5-qrcode, like the staff scanner), which accepts only
  this site's `/qr/{code}` links and visits that page; a typed code works as a fallback.
  Camera access needs https (or localhost).
- **Codes**: 6 random chars from `QrCodeGenerator::ALPHABET` (no 0/O/1/I), unguessable rather
  than sequential. Shown in the admin UI only. `QrCodeGenerator::generate()` is the only place codes are made: it skips
  existing codes and tops up, and the unique index is the final guarantee. Max
  `MAX_PER_BATCH` (1000) per request.
- **Scan**: `GET /qr/{code}` (`QrRedirectController`, `throttle:60,1`, case-insensitive) →
  302 + `no-store` to the destination (never 301: browsers would cache the old target), else
  `Pages/Qr/NotFound.jsx` with a 404 status. Unknown codes are never echoed back.
- **Admin**: `/admin/qr-codes` (`Admin\QrCodeController`): generate, search/filter
  (batch, mapped/unmapped), map/unmap (`url:http,https` only — the URL is redirected to),
  via `Components/Dashboard/QrDestinationField.jsx` (also on the scan-to-map screen): **A shop**
  (searchable `ShopPicker` → fills in this site's `/s/{slug}` link) or **Web address**. Both
  save `destination_url`; the server assigns `shop_id` when it's one of our shop links,
  select + print. Success messages use the shared `flash.status`. **Delete batch**
  (`DELETE /admin/qr-codes/batches/{qrBatch}`) removes the batch and all its codes in one
  transaction — printed stickers from it then show "Nothing found", so the page confirms, and
  asks to type DELETE when any code in it is mapped.
  **Delete codes** (`DELETE /admin/qr-codes`, `ids[]`): "Delete selected" on the selection
  bar or "Delete this code" in a code's dialog; batches left empty are deleted too. Asks to
  type DELETE when any is mapped (or selected on another page).
- **Sticker designs** (`/admin/qr-codes/designs`, `Admin\QrDesignController`, `qr_designs`
  table): a background image (JPG/PNG/WebP ≤ 5 MB, on the `uploads` disk under `qr-designs/`)
    - where the QR "block" goes, stored as **fractions of the image** (`qr_x`/`qr_size` of its
      width, `qr_y` of its height) + the printed sticker `width_mm` + **`style`** (JSON: colours,
      dot/corner shapes, frame/quiet zone/radius, centre text or logo (`logo_path`, ≤ 1 MB),
      caption above/below, font, error correction, `box_ratio` = the QR box's own height / width,
      null = auto). The QR itself always stays **square** — in a wider/taller box it's centred at the
      largest size that fits (never stretched: that breaks scanning). `App\Support\QrStyle` validates/normalises it
      (null = plain defaults; a centre forces ECC `H`) and has the block geometry
      (`blockAspect`, `qrFraction`) — **keep it in sync with `resources/js/lib/qrStyle.js`**.
      `SaveQrDesignRequest` checks the block sits on the image and the QR itself prints ≥ 15 mm.
      Pages: `Designs.jsx` (grid) and `DesignEditor.jsx` (top toolbar for the design as a whole:
      name, width, QR size, centring, replace background, print stats; canvas; right-hand tabs for
      styling: Colours / Shape / Frame / Middle / Caption / Safety; "Test print" PDF). **One renderer**, `lib/qrRender.js` (canvas), draws the block for the editor,
      thumbnails, PDF and PNG, so what you see is what prints; `QrDesignStage.jsx` does the drag /
      corner-resize / arrow keys. Every Print button on the QR page opens a **print dialog**
      (`PrintDialog` in `Admin/QrCodes/Index.jsx`): pick the design (thumbnail cards, or Plain QR)
      and the layout (with page counts); the last choices are remembered in localStorage and
      pre-selected. Layouts: a **sheet** (A4, as many as fit, header + cut
      lines) or **one per page** — for a design, each PDF page _is_ the sticker (page size =
      exactly its W × H, artwork edge to edge, nothing else); plain QRs go one per A4 page at
      140 mm. The editor's "Test print" is always one sticker-size page.
      **Double-sided** (dialog option, remembered): every page is followed by its back page with
      the same codes' QRs — on the same design, or another design of the **same print size**
      (`canBeBackOf()`); sheet backs are mirrored left-to-right so a long-edge flip lines each
      back up behind its front. 100 codes one-per-page = 200 pages.
- **Print sizes**: each design has an exact size — a preset from `QrDesign::PRESETS` (**Stand
  90 × 140 mm**, **Table 60 × 60 mm**; the server fills in the numbers, never trusting the
  browser's) or custom `width_mm` × `height_mm` (null height = follow the artwork). Designs
  always print at that exact size (2 stands / 12 table cards per A4 sheet); the artwork is
  fitted inside, never stretched (`artRect()` in `qrPrint.js` ↔ `QrDesign::artWidthMm()` —
  keep in sync; the QR's position and the 15 mm check are relative to the artwork).
- **Serial numbers**: a design can print each code's serial (style `serial_*`: on/off, prefix,
  size, distance from the bottom, colour) at the bottom centre of the artwork. The serial is
  the code's number in its batch, **stored** in `qr_codes.serial` (set by
  `QrCodeGenerator`; a one-off create gets the batch's next number), formatted by
  `formatSerial()` as 001, 002 …, so a reprint of one code keeps its number. Stored rather
  than counted so deleting a code never renumbers the stickers after it. Drawn as PDF text (helvetica/times/courier for the design's
  font) and on the PNG canvas.
- **Print view**: every Print button opens the PDF in a new tab with the print dialog
  (`openPrintWindow()` in the click, then `printQrPdf()`), falling back to a download if
  popups are blocked. Print at **100% / Actual size** or the sizes won't be exact.
- **PDF**: built **client-side** with jsPDF (`resources/js/lib/qrPrint.js`, lazy-loaded) from
  `POST /admin/qr-codes/print` JSON — A4, 4×5 grid, code printed under each QR. No
  server-side PDF library, for the Hostinger CPU/memory limits and to avoid dompdf's attack
  surface.

## Shop menus (additive — admin and owner, read by Gemini)

One menu per shop, edited by the **admin** for any shop (Admin → shop settings → **Menu**,
`Admin\ShopMenuController`, `Pages/Admin/Menu/Edit.jsx`) or by the **owner** for their own
(`/dashboard/menu`, nav "Menu", `OwnerMenuController`, `Pages/Dashboard/Menu.jsx`, no shop
param). Both pages are thin wrappers around one editor, `Components/Menu/MenuEditor.jsx`
(`layout` + `urls.base` where it saves, `/read`, `/theme`); the props come from
`ShopMenuController::editorProps()`. Unsaved edits survive a reload (sessionStorage draft).

- **Tables**: `menu_sections` (`shop_id`, `name`, `position`) → `menu_items`
  (`menu_section_id`, `name`, `description`, `price` = display text as printed, e.g.
  "Reg £3.20 · Lg £3.80", `tags` JSON, `position`). `Shop::menuSections()` / `menuItems()`.
  Saving replaces the whole menu in one transaction (`App\Services\ShopMenu::replace()`).
- **Tags are free text** (each shop's menu has its own: "Vegan", "Halal", "New", "Bestseller"…),
  ≤ 6 per item, ≤ 24 chars. `MenuItem::tidyTags()` cleans them on save and from Gemini (which
  copies the menu's own labels, expanding abbreviations from its key). The editor suggests the
  menu's own tags first (`tagSuggestions()` in `lib/menuTags.js`, limits mirrored there).
  Don't bring back a fixed list.
- **AI reading**: `POST /admin/shops/{shop}/menu/read` (≤ 5 JPG/PNG/WebP/PDF, throttled)
  → `App\Services\MenuReader` → Gemini `generateContent` with a JSON `responseSchema`
  (`GEMINI_API_KEY`, `GEMINI_MODEL`; key sent server-side only). It **saves nothing**: the
  result loads into the editor (replace or append) and the admin checks it, then saves. The
  browser shrinks photos to ≤ 2400 px JPEG first (Gemini's inline limit is 20 MB). Unreadable
  → 422, key/quota/outage → 503 (logged, never echoed). No key = reader hidden, typing still works.
- **Public page**: `GET /menu/{menu_slug}` (`MenuController`, `Pages/Menu.jsx`, shop header).
  `shops.menu_slug` = name + 4-char code (`Shop::uniqueMenuSlug()`, set on create, e.g.
  `bean-there-7k2q`), **never the card slug**: owners see their menu link, and it mustn't
  give them the card link (see below). Old `/menu/{id}` links 302 to it. Read via
  `Shop::menuUrl()`. Sticky section chips (scroll-tracked), search from 12 items.
- **Menu themes** (separate from the card's `ThemeCatalog`): `App\Support\MenuThemes` — each
  is colours + fonts + a **layout** (`list` / `classic` / `cards`). `shops.menu_theme`
  (null = `MenuThemes::DEFAULT`), read via `Shop::menuTheme()`. Picked with the editor's
  **Theme** button (`PUT /admin/shops/{shop}/menu/theme`, saved immediately; the picker
  renders every theme with the shop's own items). One renderer, `Components/MenuView.jsx`,
  draws the public page, the editor's live phone preview and the picker cards — add a
  layout there and in `MenuThemes::LAYOUTS`.
- **Item photos** (`menu_items.image_path/image_status/image_confidence/image_reason`): only
  when asked (no automatic run after save / on open - the user's choice), the editor calls `POST {base}/images` (admin `/admin/shops/{shop}/menu/images`, owner
  `/dashboard/menu/images`) until `remaining` is 0 — `App\Services\MenuItemImages` looks each
  never-tried item up in the product catalog (`ProductCatalog`, HMAC-signed GETs to
  `PRODUCT_API_URL`, keys server-side; full name → hyphenated → main word, since it matches text
  as typed), `MenuImageVerifier` (Gemini) must accept a candidate (dish = any clear photo of it,
  branded = brand + size; `MENU_IMAGE_MIN_CONFIDENCE`), and only then it's stored on the
  `uploads` disk (`menu-items/{shop}/…`). Gemini down → nothing saved, status stays null (retried).
  Statuses: null / found / not_found / rejected / removed. 3 items per request (no queue).
  Progress is shown by `Components/Menu/PhotoFinder.jsx`: the editor asks one item per request
  (`limit: 1`) so it knows which item is in flight; the Search → Download → AI check steps
  advance on a clock, the outcome and the step it stopped at are the server's real answer.
  Responses carry `outcome` / `outcome_reason` (this search's result, shown with the reason).
  **Select** (toolbar) picks items for a bulk run, one `{item}` request each; an item that
  already has a photo keeps it (and its status) when nothing better is found.
  Triggers: toolbar **Find photos** (untried items; with none left it sends `retry` = look
  again for not_found/rejected, never `removed`), and per saved item the thumbnail /
  "Find photo" / "Change photo" (`{item, query}`) and "Remove photo".
- **Who checks the photos** (Admin → Settings → Menu photos, `Setting::MENU_PHOTO_CHECK`,
  `PUT /admin/settings/menu-photos`): `ai` (default, the Gemini flow above) or `manual` = no
  Gemini - the editor's `PhotoReview` dialog asks "Is this {item}?" for each catalog photo
  (Yes keeps it, No shows the next, Skip / Stop; Y / N keys). `MenuItemImages::mode()` is `ai`
  only when chosen **and** there's a Gemini key, else `manual`; photos are offered whenever the
  catalog is configured (`enabled()`), and each mode's endpoints 404 in the other. Manual flow,
  per item: `POST {base}/images/review {item, query?}` downloads up to `max_candidates` and holds
  them in the cache for 30 min under random 40-char tokens (`menu-photo-review:{token}`, shop +
  item recorded; the catalog keys never reach the browser) → `GET {base}/images/review/{token}`
  shows one (own shop only) → `POST {base}/images/confirm {item, token}` stores it (confidence
  null, reason "Confirmed by {name}"); `token: null` = no to all → `rejected`. The editor gets
  `photoCheck` from `ShopMenuController::editorProps()`.
- **Upload photo** (per saved item, always offered - no catalog or Gemini needed): the browser
  picks a photo (camera / library), `Components/Menu/PhotoCropper.jsx` crops it square (drag,
  pinch / wheel / slider zoom) and compresses it to 800 x 800 WebP (JPEG where the browser can't
  make WebP) - redrawing also drops EXIF / GPS - then `POST {base}/images/upload {item, photo}`
  (jpg/png/webp, <= 4 MB, >= 200 x 200). `MenuItemImages::upload()` re-encodes it with GD as
  WebP (<= 1200 px, refuses > 16 MP so GD can't run out of memory) and stores it like catalog
  photos with status **`uploaded`** ("Uploaded by {name}"); bulk Find photos never touches it
  (only `null` items are untried).
  **Whole-menu saves keep photos**: `ShopMenu::replace()` only accepts an `image_path` this
  shop's items already had, null → `removed`, no key → same-named item's photo; unused files are
  deleted. The catalog (imgapi.techsasolutions.com) is a grocery/retail catalog: branded drinks
  and snacks are found, made-to-order dishes mostly aren't. `MenuView` shows the photo in every
  layout; no photo = the layout as before. Off (`imagesEnabled` false, 404) without catalog keys.
- **QR stickers**: `QrDestinationField` has a **Menu** tab (fills `/menu/{menu_slug}`);
  `QrCodeController::shopIdForDestination()` assigns those stickers (and old `/menu/{id}`
  ones) to the shop too.

## WhatsApp marketing (additive — owner dashboard)

Owners send offers to customers who ticked "send me offers from {shop} on WhatsApp" at sign-up.

- **Page**: `/dashboard/marketing` (`MarketingController`, `Dashboard/Marketing.jsx`, nav
  "WhatsApp"): audience (everyone / regulars = stamp in 30 days / win back), message (one
  paragraph, ≤ 500), live WhatsApp preview, history (counts only, never phone numbers).
- **Delivery**: WhatsApp Cloud API via `App\Services\WhatsAppGateway` (thin, mockable). One
  Tada Tap business number sends for every shop with **one Meta-approved marketing template**
  (`WHATSAPP_TEMPLATE`): body = `WhatsAppGateway::TEMPLATE_BODY` ({{1}} first name, {{2}} shop,
  {{3}} message — parameters can't contain line breaks, so `tidyMessage()` flattens them) +
  a URL button "Unsubscribe" → `{APP_URL}/u/{{1}}`. The owner's preview renders the same constant.
- **Poster** (optional, JPG/PNG ≤ 5 MB, big photos shrunk client-side): stored on the `uploads`
  disk under `marketing/{shop}` (`marketing_campaigns.image_path`) and sent as an **image header**
  by its absolute URL (`MarketingCampaign::posterUrl()` — Meta downloads it, so it must be public
  https). A template's header type is fixed at approval, so poster campaigns use a second
  template, `WHATSAPP_TEMPLATE_IMAGE` (same body + button, Image header).
- **No queue workers**, so `MarketingService::start()` stores one `marketing_messages` row per
  recipient and the open page calls `POST /dashboard/marketing/{campaign}/send` until done
  (`marketing_batch_size` per call). Rows are claimed `pending → sending` under
  `lockForUpdate()`; claims left by a dead batch (> 5 min) become **failed, never resent** — a
  customer must not get an offer twice. Consent is re-checked per message.
- **Limits**: one campaign per shop per `marketing_cooldown_hours` (24). Sending is
  `OWNER_ONLY` in `ViewAsOwner` — an admin can't send for a shop.
- **Unsubscribe**: `GET /u/{token}` only shows the page (link previews fetch it);
  `POST` sets `marketing_consent = false` + `marketing_opted_out_at` for that one card.
- Not built: delivery/read receipts (Meta status webhook) and replies.

## Owners don't get their card link (additive — protects counter display sales)

Owners could otherwise print their own QR of `/s/{slug}` instead of buying our counter
display. So, by default, nothing in the owner dashboard reveals it:

- `shops.show_card_link` (bool, **default false**), switched per shop by the admin
  (Admin → shop settings → Loyalty card → "Show the card link to the owner").
- While off: `DashboardController::shopSummary()` sends `slug: null` (so it isn't even in the
  page data), the "Customer page" nav item's `href` closure returns null (hidden), Settings
  shows a "Counter display" panel (→ Orders) instead of the downloadable QR, and Theme hides
  "Open your live card page". The customers CSV is named from the shop name, not the slug.
- **Sign-up no longer asks for a link**: `Shop::uniqueSlug($name)` makes it (`bean-there`,
  then `-2`, `-3`…); any `slug` sent is ignored. Only the admin's "Add shop" still picks one.
- The card page itself stays public (customers, My Cards and QR stickers rely on it) — this
  removes the easy route, it can't stop someone who already knows the URL.

## Shop country: UK orders, elsewhere downloads its QR codes (additive)

Enquiries come from all over the world, but we only ship hardware in the UK.

- `shops.country` (ISO alpha-2, default `GB`; the migration backfilled `+91` / 6-digit-PIN shops
  to `IN`). The list is `App\Support\Countries` (name + dialling code), server-only, sent to
  forms as `countries` (`Countries::options()`, UK first) and drawn by `Components/CountrySelect.jsx`.
  Collected at onboarding step 1, on owner Settings → Contact, admin shop settings → Business
  and admin "Add shop" (`shop_country`).
- **Contact number = two columns**: `shops.contact_phone_code` (dialling code, digits, e.g. `44`)
  + `contact_phone` (the number without it, e.g. `7700900123`), picked/typed in
  `Components/PhoneField.jsx` (code picker showing "+44" + number box) and **shown together**
  via `Shop::contactPhone()` ("+44 7700900123") / `contactPhoneTel()` for `tel:` links. The code
  defaults to the country's and follows a country change unless picked by hand; a number typed
  as `+91…` / `0091…` switches to that code. The **code** picks the number rules (+44 UK
  landline/mobile, +91 Indian mobile, else 4-14 digits); the **country** picks the postcode rules
  (UK / India exact, elsewhere optional). `ShopContact` ↔ `lib/validation.js` — keep in sync.
  The address finder and the delivery address are UK-only in the forms.
- **`Shop::canOrderProducts()`** = country is GB. Not UK: no order banner, `/dashboard/orders`
  redirects to `/dashboard/qr-codes`, checkout and coupon 404, the menu shows **QR codes**
  instead of **Orders** (both entries have `href` closures in `Navigation`), Settings shows
  "Your QR code" instead of "Counter display". `shopSummary()` sends `can_order`.
- **Owner QR codes** (`/dashboard/qr-codes`, `DashboardController::qrCodes`, `Dashboard/QrCodes.jsx`):
  the codes mapped to the shop (`qr_codes.shop_id`), in the design the **admin** picked for the
  shop (`shops.qr_design_id`, Admin → shop settings → Loyalty card → "Owner's QR download";
  null = plain QR). Preview, PNG and PDF (one sticker-size page each, or "Download all") are
  drawn in the browser by `lib/qrPrint.js` (`qrPngDataUrl()`, `printQrPdf(…, null)` downloads),
  the same renderer as the admin's prints. Never shows the card link itself, only what the
  code opens.

## Landing page at "/" (additive — replaces the old redirect to log in)

- **`/`** (`LandingController`): signed-out visitors get `Pages/Landing.jsx`. Signed-in users are
  still redirected to `homeUrl()`; an admin can view it with `/?preview=1`. Sections, top to
  bottom: **hero** (badge `kicker`, `title` H1, `description`, `primary_cta` → `/register`,
  `demo_cta` → scrolls to the video and plays it), **video** (`#demo`), **trust banner**
  (`trust_title`, `trust_description`, `trust_points` with ticks; hidden when all empty),
  **pricing** (`pricing_title`, `pricing_subtitle`, the plan cards). Every
  plan button → `/register`.
- **Edited by the admin** at `/admin/landing-page` (`Admin\LandingPageController`,
  `Admin/LandingPage.jsx`, nav "Landing page", desktop only), stored as one JSON setting
  (`Setting::LANDING_PAGE`). `LandingPage::content()` = the saved page **merged over**
  `LandingPage::defaults()`, so a page saved before a field existed gets that field's default.
  The editor's **"Load default content"** fills the form with the defaults (saved only on Save).
  Up to 4 plans: name, badge (short pill), "who it's for" (`description`), offer line (gift
  icon), price per currency, text after/under the price ("/ year", "Billed annually after …"),
  features (one per line, ≤ 200 chars: `Title (detail)` shows the detail smaller, a line ending
  in `:` is a heading), button text, highlighted (dark, mint-ringed, raised card).
- **Currency**: prices are **typed per currency** by the admin (no conversion; every plan needs a
  price in every offered currency). A visitor sees **only their own country's currency** - no
  switch, and **other currencies' prices never reach the page or its data** (so nobody can
  compare countries and sign up as the cheapest one). There is **no currency note** either - the
  "Prices in {currency}" line in `Landing.jsx` is commented out on purpose; don't show it again
  unless the user asks.
  The country: `App\Services\VisitorCountry` (Cloudflare `CF-IPCountry` first, else ipinfo.io
  Lite (`IPINFO_TOKEN`, server-side, 2 s timeout, cached per IP hash for 7 days; private IPs
  never looked up)) → `Currencies::forCountry()`; not offered → the default currency.
  `?currency=USD` works **only in the admin's preview** (`?preview=1`, signed in as admin);
  the public's is ignored. Whole numbers are shown grouped (₹2,999).
- **Video**: `LandingPage::youtubeId()` accepts watch / youtu.be / shorts / embed / live links;
  the page shows the thumbnail and loads the `youtube-nocookie.com` player only on click.

## Self-service sign-up (additive — the landing page's "Start Free")

The marketing site is WordPress (currently at tadatap.co.uk); its "Start Free" links to this app's
`/register`. Sign-up always creates an **owner**, never an admin, and the shop is live as soon
as it's set up (no approval step).

- `/register` (`RegisteredUserController`, email + password) and "Continue with Google"
  (`GoogleAuthController`, Laravel Socialite — one flow for sign-up and log-in). Shared UI:
  `Components/AuthShell.jsx` (also used by `Auth/Login.jsx`).
- **Google**: `config/services.php` `google` + `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI`. The
  button and routes only exist when both credentials are set **and** the admin hasn't switched
  it off (`GoogleAuthController::enabled()` = `configured()` && the `google_auth_enabled`
  setting, default on; 404 otherwise).
- **Admin settings** (`/admin/settings`, `Admin\SettingsController`): app-wide switches in the
  `settings` table (`App\Models\Setting::get/set`, JSON values). Never secrets — API keys stay
  in `.env`. Currently just the Google switch: it can't be turned on without the keys, and the
  page warns how many Google-only owners (`password` null) would be locked out if it's off. An account is matched by `users.google_id`, then by email — but only a
  Google-**verified** email may create or claim an account, and a Google account is **never
  linked to an admin**. Google-only owners have `password = null`.
- **Business contact & location** (collected at shop setup, so Google sign-ups give it too):
  `shops.contact_name/contact_email/contact_phone`, `address_line1/address_line2/town/state/postcode`,
  `delivery_address` (null = same as the shop). One place validates + tidies it:
  `App\Support\ShopContact` (phone → `+44…` for any UK landline/mobile format or `+91` mobile;
  postcode upper-cased with its space; `delivery_same` tick box). Required in the forms,
  nullable in the table (older shops). Owners edit it on Settings in its **own** form
  (`PUT /dashboard/settings/contact`) so the main settings form still saves for older shops.
  **State**: asked (a dropdown, required) only for countries with a list in
  `Countries::STATES` - India's 36 states / UTs; sent to the forms with each country
  (`Countries::options()` → `states`, `statesOf()` in `lib/validation.js`, `StateSelect.jsx`);
  dropped for other countries (`ShopContact::normalise`). Part of `Shop::deliveryAddress()`.
  The admin Shops grid shows Contact + Location columns. The contact person/email **default to
  the owner's login** (sign-up pre-fills them; admin "Add shop" copies them; a data migration
  backfilled older shops; `Shop::contactDetails()` falls back for the forms) but stay separate
  fields — a manager can be the contact. Both settings forms show "Same as your login" or
  "Your login: … · Use this".
- **Find address** (findaddress.io, postcode + house number/name → one address): the
  `AddressLookup.jsx` box above the address fields on onboarding, owner Settings and the admin
  shop settings page fills them
  in (still editable). The browser only calls our **proxy** `GET /address-lookup`
  (`AddressLookupController` → `App\Services\AddressFinder`, `auth` + `throttle:20,1`), which
  sends `FINDADDRESS_API_KEY` as `x-api-key` server-side — the key never reaches the client.
  Found addresses are cached 30 days (every uncached lookup costs a credit). Provider errors
  (key, credits) → 503 "type it in"; bad postcode → 404. No key = shared `addressLookup` false
  and the box isn't shown.
- **Shop setup**: `/onboarding` (`ShopOnboardingController`, `Pages/Onboarding/Shop.jsx`)
  creates the owner's one shop, in two steps: "Your business" (name, contact, location — checked
  by `POST /onboarding/business`, which creates nothing but keeps the validated step on the account
  as `users.onboarding_draft` (JSON, hidden) so a reload, failed submit, logout or another
  device resumes on step 2 with it filled in; cleared in the same transaction that creates
  the shop) then "Loyalty card"
  (stamps, reward + preview — the card link is generated, see above). The final `POST /onboarding` re-validates everything and
  sends the owner back to step 1 if that's where an error is. `EnsureOwnerHasShop` (alias `shop.ready`) on the whole
  dashboard group sends an owner without a shop there — every dashboard action assumes
  `auth()->user()->shop` exists. Shared form pieces with the admin "Add shop":
  `Components/Dashboard/ShopFields.jsx`.
- **Password reset** ("Forgot password?" on Login): `PasswordResetLinkController` (`/forgot-password`,
  throttled; always answers `SENT` - never reveals whether an email has an account) emails
  Laravel's reset link (broker: 60 min, one per minute per account; wording in
  `AppServiceProvider` via `ResetPassword::toMailUsing`) → `NewPasswordController`
  (`/reset-password/{token}`) sets it (min 8, confirmed), rotates `remember_token`, logs the
  user in and logs `auth.password_reset`. Also how Google-only owners add a password.
  Mail: Hostinger SMTP in production, `log` locally.
- Not built yet: email verification.

## Products & orders (additive — the counter display)

Owners order hardware from us — today one product, the £40 counter display stand (NFC + QR),
later maybe more (table stickers). Prices and copy live in our DB, not in Stripe.

- **Tables**: `products` (`name`, `description`, `price_pence`, `is_active`, `is_featured`) and
  `orders` (one product per order, `quantity` 1–`Order::MAX_QUANTITY` (20, mirrored in
  `lib/money.js`); `product_name`, `unit_price_pence`, `total_pence` copied at order time;
  `payment_method` `stripe | bank_transfer | cash | free` = `App\Enums\PaymentMethod`;
  `status` `pending | paid`; `stripe_session_id` unique; `delivery_address` snapshot; `note`;
  fulfilment timestamps `paid_at` → `processing_at` → `dispatched_at` → `delivered_at`;
  `courier`, `tracking_number`, `tracking_url`). The launch product is inserted **by the migration** (production
  can't run seeders). Products are never deleted — switch them off.
- **Price breaks**: `products.price_tiers` (JSON, null = one price) = `[{from, price_pence}]`;
  each item is charged the price for its position (£40 + `{from: 2, £20}` → 3 items = £80).
  `Product::priceBreakdown()` / `totalFor()` is the only real calculation (Stripe gets one
  line item per price step); `priceBreakdown()` / `priceFor()` in `lib/money.js` mirror it for
  display — **keep the two in sync**. Max 5 breaks, `from` 2–`MAX_QUANTITY`.
- **The flag**: `shops.product_ordered_at`, set by the shop's first paid order (either way) in
  `OrderService::markPaid()` and never cleared. While it's null and a product is featured +
  active, the owner's Overview shows `OrderOffer.jsx` ("What's next? Order your counter
  display", `orderOffer` prop). Once set, the banner is gone for good — including when the
  admin recorded the order, so owners who paid by phone/bank transfer never see it.
- **Online (Stripe Checkout)**: `POST /dashboard/orders` (`OrderController::checkout`,
  `product_id` + `quantity`; ordering again any time is allowed) creates or reuses the shop's
  pending order for that product and returns `Inertia::location()` to Stripe's hosted page
  (`price_data` built from our price, GBP). Paid is confirmed by **both** the success redirect
  (`GET /dashboard/orders/{order}/success`, order looked up through the owner's own shop) and the
  webhook (`POST /stripe/webhook`, `StripeWebhookController`, signature-checked, CSRF-exempt),
  whichever comes first; `markPaid()` locks the row and is idempotent. Needs `STRIPE_SECRET`
  (+ `STRIPE_WEBHOOK_SECRET`); with no secret the banner says "get in touch" and checkout 404s.
  Ordering needs an address (`Shop::deliveryAddress()`: delivery address, else the shop address).
  An order keeps a copy of the address; orders with none (made before the shop had one) pick it
  up when the shop's address is saved (`Shop::booted()` `saved` hook), unless already dispatched.
  Both settings forms have "delivered to the shop address" (`delivery_same`); the admin form
  tidies phone/postcode via `ShopContact::normalise()` like the owner's.
- **All Stripe calls** go through `App\Services\StripeGateway` (thin, array-based) so tests mock
  it; the webhook test signs a real payload instead.
- **Fulfilment stages** (`App\Enums\OrderStage`: received / processing / dispatched /
  delivered): the stage is the furthest timestamp set (`Order::stage()`), never a separate
  column. `OrderService::moveTo()` moves forwards (dating skipped stages) or back (clearing
  later ones and the tracking). Admin: `PUT /admin/orders/{order}/stage`, via the shared
  `OrderStageEditor.jsx` dialog (courier datalist, tracking number, tracking **link pasted by
  the admin** — we don't guess courier URLs). Only the tracking fields sent are changed.
  Newly reaching Dispatched / Delivered emails the shop owner (`App\Mail\OrderDispatched` /
  `OrderDelivered`, markdown views in `resources/views/mail/orders/`), sent synchronously
  in try/catch — a mail failure is logged and never undoes the change.
- **Owner**: `/dashboard/orders` (`DashboardController::orders`, nav "Orders",
  `Dashboard/Orders.jsx`) = "Your orders" (paid only — abandoned checkouts aren't orders —
  each with `OrderTracker.jsx`) + "Order more" (every product on sale, `QuantityStepper`).
  `Order::summary()` is the owner-safe shape (no admin note / placed-by). After ordering,
  Overview shows a slim `activeOrder` line for the latest undelivered order.
- **Admin**: Orders (`/admin/orders`, nav item; to do / dispatched / delivered / awaiting
  payment, "Update" per row) with a **Products** button (`/admin/products`: add/edit, price in £,
  on sale, "offer on owner dashboards" = featured, only one at a time). Each shop's settings
  page has "Counter display & orders": its orders (with "Update") + **Record order** (product,
  quantity, bank transfer / cash / free, amount, note) → `POST /admin/shops/{shop}/orders`. The Shops grid has a Display column
  and a "No display ordered" filter.
- **Orders arranged by the admin** (phone, visit, promotion): "New order" on Admin → Orders
  (any shop, `ManualOrderForm.jsx`, also on each shop's page) → `POST /admin/shops/{shop}/orders`
  with an **agreed price** (`total_pence`; the normal price is kept in `list_total_pence`, so
  discounts show as "£10 off" / "Free") and `paid`: already received (bank transfer / cash),
  free (always paid, £0), or **awaiting bank transfer** (`status` pending, non-Stripe method =
  `Order::awaitingManualPayment()`). Awaiting orders show the owner the bank details
  (`Setting::BANK_DETAILS`, Admin → Settings) + reference `TADA-{id}`, and hide the order
  banner. The admin then **Confirm payment** (`PUT /admin/orders/{order}/paid` →
  `markPaid()`) or **Cancel** (`PUT …/cancel`, unpaid only → `status` cancelled; the banner
  comes back). Owners never see abandoned Stripe checkouts or cancelled orders.
- **Editing an order** (`OrderEditDialog.jsx` on the Orders grid + shop page → `PUT
  /admin/orders/{order}`, `UpdateOrderRequest`, `OrderService::update()`): arranged orders can
  change product, quantity, price, method (list price recomputed; an unpaid one switched to
  Free goes ahead); **Stripe orders only their delivery address and note** — the amount is
  what Stripe charged. Cancelled orders and unfinished Stripe checkouts 422 (checked in
  `prepareForValidation`, before the rules).
- **Coupons** (`coupons` table, `App\Models\Coupon`, Admin → Orders → **Coupons** button,
  `Admin\CouponController`): `percent` (1-100) or `fixed` (pence) off the order total, optional
  product, valid-until day (end of day UK), total uses, once per shop, on/off. Never deleted.
  **Only paid orders count as a use.** Owners apply a code on the order forms
  (`CouponField.jsx` → `POST /dashboard/orders/coupon`, preview only, throttled); checkout
  re-checks it. Stripe gets a one-off Stripe coupon for the exact discount (`createCoupon`), so
  its page and receipt show it. A coupon that makes the order £0 skips Stripe (`placeFree()`,
  paid, method Free); one that leaves under 30p is refused. Orders keep `coupon_id` +
  `coupon_code`; the discount is `list_total_pence - total_pence`. `Coupon::discountFor()` ↔
  `couponDiscount()` in `lib/money.js` — **keep in sync**.
- No subscription billing exists yet — "first year free" is only copy in the product
  description.

## Admin dashboard & charts (additive)

- `/admin/dashboard` (`Admin\DashboardController`, `Pages/Admin/Dashboard.jsx`): platform-wide
  KPIs, growth (running total + new per day), popularity (active customers split first-time vs
  returning, stamps vs rewards, weekday × hour heatmap), a this-period-vs-previous table, a
  sortable shop comparison, ratings, health ratios, owner activation funnel, quiet shops.
  **Each card has its own 7/30/90-day filter** (default 30): every section is a lazy Inertia
  prop whose name is also its URL param (`?visits=7&comparison=90`), and a card's switch does
  `router.reload({ only: [prop], data: {prop: range} })` — only that section is recomputed.
  `periodMetrics()` returns the same keys for current and previous periods (memoised per
  request) so every figure compares like for like. "When customers visit" switches between
  stamps (mint), rewards (blue) and **redemption rate** (rewards ÷ visits per slot; slots with
  < 5 visits show as "not enough data", never 0% / 100%). "All visits" was dropped: rewards
  are a small share of visits at the same times, so it looked identical to stamps. Heatmap
  shading is relative to its own max, so the legend always prints the real scale (0 … max).
- **Owner activation is a cohort**: owners who _signed up_ in the chosen range → set up their
  shop → gave a first stamp → still stamping (last 14 days), plus the median days from sign-up
  to first stamp. Every bar is the same group, so the filter changes all of them (an earlier
  all-time version only moved the last bar and looked broken).
- **Ratings are per shop**, so the headline is the average _shop_ rating (each shop's own
  average, averaged), not the mean of all reviews — one busy shop can't dominate. "Lowest
  rated" only ranks shops with ≥ 3 reviews. Daily series are `GROUP BY DATE(created_at)` in SQL (MySQL/MariaDB functions:
  `DATE`, `HOUR`, `DAYOFWEEK`) — a fixed number of queries regardless of platform size.
- Chart kit: `Components/Dashboard/Charts.jsx` (`KpiTile` + `ChangePill`, `StackedLines` —
  two measures as stacked bands with their own scales sharing one x-axis/crosshair, the
  answer to "two lines on one chart" without a dual axis — `ColumnChart`, `Heatmap`,
  `BarList`, `Legend`; all labels are clamped inside their chart) + `resources/js/lib/charts.js`. Colours were validated with
  the dataviz palette checker: mint `#12A877` (a deeper step of the brand accent — the brand
  `#17C68B` is too faint for marks on white) and blue `#2A78D6` for a second series; navy
  failed as a series colour. Rules: one y-axis per chart (never dual-axis), legend only for
  2+ series, solid hairline grid, hover/keyboard tooltip + screen-reader table on every chart.
  No chart library — plain SVG/HTML.

## Admin activity & logs (additive)

- **Activity** = an audit log of what **admins, owners and staff** do — never customers.
  Table `activity_logs` (actor_type admin|owner|staff, user_id / staff_member_id, `actor_name`
  snapshot, `as_owner`, shop_id, `action` like `shop.updated`, subject, description, `changes`
  JSON `{field: [before, after]}`). One writer: `App\Services\ActivityLogger::record()`; it works
  out the actor (signed-in staff on a staff device → the view-as admin → the signed-in user)
  and writes **nothing** when there's none (customer pages, webhooks, console, factories), and a
  write failure is logged, never thrown.
  - **Automatic**: the `RecordsActivity` trait (Models/Concerns) logs created/updated/deleted
    with before/after on Shop, User, StaffMember, StaffDevice, Product, Coupon, Order, QrCode,
    QrDesign, Setting, MarketingCampaign. Per model: `$activityIgnore` (bookkeeping, e.g.
    `last_used_at`), `$activitySecret` (password / pin_hash / token_hash → "changed", never the
    value), `$activityEvents`, `activityLabel()`, `activityShopId()`. Mass query updates aren't
    seen — log those explicitly. Don't load relations in these hooks (`$user->shop` cached null
    mid sign-up once) — query ids.
  - **Explicit**: stamps/rewards (who stamped which customer, by name, `stamps` before/after) in
    `StampService`, sign-in/out (Login/Logout listeners), sign-up, staff PIN sign-in/out, menu
    save/clear with an item diff (`ShopMenu::diff()`), QR codes generated/deleted, view-as
    start/stop/editing, customer CSV export, log cleared.
  - Page: `/admin/activity` (`Admin\ActivityController`, `Pages/Admin/Activity.jsx`) in the
    `DataTable` grid; period (today/7/30/90) + role + shop filter server-side, newest 2000 rows;
    search/sort/columns/CSV client-side; "N changes" opens the before → after dialog.
- **Logs** (`/admin/logs`, `Admin\LogController`, `Pages/Admin/Logs.jsx`): the last N lines of
  the `single` channel's file (read backwards in blocks), level counts/filters, search, live
  refresh every 5 s, copy, download, clear (writes "Log cleared by admin" as the first line).
  Built for this app - not the MembersApp log viewer. Tests point the path at a temp file.
- Both are desktop-sidebar only (`'mobile' => false` in `Navigation`), like the admin's Orders.

## Navigation & page access (one registry)

`App\Support\Navigation::items()` is the **single list of dashboard menu pages and which roles
may open each**. Never hard-code links in a layout.

- Menus: shared to every page as the `navigation` prop (`{main, footer}`, only the signed-in
  user's items, with `href` resolved and `active` computed server-side via `routeIs`).
  `AdminLayout` / `OwnerLayout` render it through `Components/Dashboard/NavMenu.jsx`
  (`SidebarLinks`, `TabLinks`); icon keys map to react-icons in `resources/js/lib/navIcons.js`
  — **keep the two in sync**. Optional `group` renders a sidebar section heading.
- Access: the `nav.access` middleware (`EnsureNavigationAccess`) on the admin and owner route
  groups 403s any role not in a menu page's `roles`. It only covers routes listed in the
  registry; form posts / JSON endpoints keep their own guards. The groups' `role:*`
  middleware stays as the outer lock, so giving a role a page in another area also needs that
  route moved/opened — `NavigationTest` fails if the registry and real access disagree.
- Adding a page: route → registry entry (`route`, `label`, `icon`, `roles`, optional
  `active` patterns / `section` / `group` / `href` closure / `external`) → icon key.

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
Plus `users.google_id` (nullable, unique) with `users.password` now nullable (Google-only
owners), `users.onboarding_draft` (nullable JSON, shop setup in progress), and `qr_batches` / `qr_codes` / `qr_designs` (see "Bulk QR stickers").
Plus `shops.header_style` (nullable JSON, card page header text/tint/shadow).
Plus `shops.country` (char 2, default GB), `shops.qr_design_id` (nullable FK) and
`shops.contact_phone_code` (dialling code; `contact_phone` is now the number without it) (see "Shop country").
Plus `products`, `orders`, `shops.product_ordered_at`, `coupons` and `orders.coupon_id/coupon_code` (see "Products & orders").
Plus `menu_sections` / `menu_items`, `shops.menu_theme` and `shops.menu_slug` (see "Shop menus").
Plus `activity_logs` (see "Admin activity & logs").
Plus `customer_shop_cards.marketing_consent` (bool, default false) and `marketing_consent_at`
(timestamp). This is an optional opt-in to **WhatsApp** offers from **that one shop**, unticked by
default, and customers can register without it. `CustomerRegistrar` only ever turns it on: an
unticked box on a repeat registration is not a withdrawal. Withdrawal is the unsubscribe link
(see "WhatsApp marketing"). Plus `marketing_unsubscribe_token`, `marketing_opted_out_at`,
`marketing_campaigns`, `marketing_messages`.

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
- **No browser dialogs**: never `alert()` / `confirm()` / `prompt()`. Use
  `useConfirm()` from `Components/ConfirmDialog.jsx` (`await confirm({ title, message,
confirmLabel, danger, requireText })`, render `{confirmDialog}`); `requireText: 'DELETE'` for
  hard-to-undo actions.
- **Icons**: use `react-icons` for every icon. Don't hand-write inline `<svg>` icons. The only
  exception is artwork that `react-icons` doesn't have, such as the country flags in
  `RegistrationModal.jsx`.
- **Package manager**: yarn, not npm — use `yarn add`/`yarn info` for anything touching
  `package.json` or querying the npm registry.
- **Commits**: `Stage N: <short summary>`.
- **Naming**: snake_case tables/columns, PascalCase models, kebab-case routes.
