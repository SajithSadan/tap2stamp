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
  (`POST /s/{shop:slug}/card/{customer:uuid}/review`), `RatingTile.jsx` (star UI).
  **One rating flow, no separate Google button**: the card page's quick-action row is
  **Follow** (Instagram) · **Rate** · **Wi-Fi** (each only when set up); Rate / Wi-Fi open a
  panel under the row. Rating saves to our `reviews` table; then, if the shop set
  `shops.google_review_url` (Settings, `url:http,https`), a **"Post it on Google too"** button
  copies the comment and opens the shop's Google review page. Google only accepts reviews from
  the customer's own account (no API posts for them), and it's offered after **every** rating,
  good or bad - showing it only for high ratings is "review gating", against Google's rules.
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
  it ever reaches thousands. Shop status: active / quiet (no stamps for 14 days) / not started. The owner's
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
  `tap2stamp` (navy + mint green, Poppins — the brand look of the WordPress landing page at
  tap2stamp.co.uk) = the site look in `app.css` and the fonts in `app.blade.php`. **Keep all
  three in sync** when the brand colours change.
- `shops.theme` (nullable slug) is set on `/dashboard/theme` (`Dashboard/Theme.jsx`: filters,
  live phone preview, `PUT /dashboard/theme` validated with `Rule::in` the catalog keys).
  `ThemeCatalog::forShop()` falls back to the default for null/unknown slugs.
- Applied **only on the customer card page** (`/s/{slug}`, incl. the registration sheet):
  `CardController::show()` passes `theme`, `Card.jsx` calls `useDocumentTheme()` from
  `resources/js/lib/theme.js`, which re-points the `--color-brand-*`, `--radius-brand` and font
  variables on `<html>` and loads the theme's Google Fonts. The owner dashboard, staff app and
  cross-shop `/my-cards` keep the default look.
- Because themes can be dark, customer-page code must not use `brand-text` as a "dark"
  colour (it's light on dark themes). For always-dark brand surfaces (card banner,
  registration backdrop, owner/admin sidebar, sign-up panel) use **`brand-deep`** — the theme's
  `deep` colour, always paired with white text. Tap2Stamp sets it to its navy; every other
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
  **Every slot is a stamp** - no slot is drawn as the reward: the reward comes *after* the last
  stamp ("Free coffee after 6 stamps"), so a gift in slot 6 read as "only 5 needed".
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
  Read it via `Shop::bannerUrl()`. It fills the card page header (`Card.jsx`), under a dark
  bottom fade, with the logo (rounded square), shop name and reward inside it bottom-left - no
  motion; no banner = the theme's colour gradient in the same layout. The page content then
  overlaps it as a sheet with rounded top corners. Blurred and darkened, it's also the
  registration backdrop (`RegistrationModal` `bannerUrl` prop). `public/uploads` is gitignored.
- **Logo** (Theme → Banner & logo, `ShopLogoController`, `shops.logo_path`, same `uploads` disk
  under `logos/{shop}`, JPG/PNG/WebP ≤ 2 MB, ≥ 120×120, uploads on pick): shown in the round
  badge in the card page header and on the sign-up screen (`Shop::logoUrl()`, `logo_url`)
  instead of the store icon; no logo = the store icon. Card page sections are solid panels
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
  select + print. Success messages use the shared `flash.status`. **Delete batch**
  (`DELETE /admin/qr-codes/batches/{qrBatch}`) removes the batch and all its codes in one
  transaction — printed stickers from it then show "Nothing found", so the page confirms, and
  asks to type DELETE when any code in it is mapped.
- **Sticker designs** (`/admin/qr-codes/designs`, `Admin\QrDesignController`, `qr_designs`
  table): a background image (JPG/PNG/WebP ≤ 5 MB, on the `uploads` disk under `qr-designs/`)
  + where the QR "block" goes, stored as **fractions of the image** (`qr_x`/`qr_size` of its
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
  lines) or **one per page** — for a design, each PDF page *is* the sticker (page size =
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
  the code's **position in its batch** (`QrCode::scopeWithSerial()`, a correlated count in the
  same query — no extra queries), formatted by `formatSerial()` as 001, 002 …, so a reprint
  of one code keeps its number. Drawn as PDF text (helvetica/times/courier for the design's
  font) and on the PNG canvas.
- **Print view**: every Print button opens the PDF in a new tab with the print dialog
  (`openPrintWindow()` in the click, then `printQrPdf()`), falling back to a download if
  popups are blocked. Print at **100% / Actual size** or the sizes won't be exact.
- **PDF**: built **client-side** with jsPDF (`resources/js/lib/qrPrint.js`, lazy-loaded) from
  `POST /admin/qr-codes/print` JSON — A4, 4×5 grid, code printed under each QR. No
  server-side PDF library, for the Hostinger CPU/memory limits and to avoid dompdf's attack
  surface.

## Self-service sign-up (additive — the landing page's "Start Free")

The marketing site is WordPress (tap2stamp.co.uk); its "Start Free" links to this app's
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
  `shops.contact_name/contact_email/contact_phone`, `address_line1/address_line2/town/postcode`,
  `delivery_address` (null = same as the shop). One place validates + tidies it:
  `App\Support\ShopContact` (phone → `+44…` for any UK landline/mobile format or `+91` mobile;
  postcode upper-cased with its space; `delivery_same` tick box). Required in the forms,
  nullable in the table (older shops). Owners edit it on Settings in its **own** form
  (`PUT /dashboard/settings/contact`) so the main settings form still saves for older shops.
  The admin Shops grid shows Contact + Location columns.
- **Shop setup**: `/onboarding` (`ShopOnboardingController`, `Pages/Onboarding/Shop.jsx`)
  creates the owner's one shop, in two steps: "Your business" (name, contact, location — checked
  by `POST /onboarding/business`, which creates nothing but keeps the validated step on the account
  as `users.onboarding_draft` (JSON, hidden) so a reload, failed submit, logout or another
  device resumes on step 2 with it filled in; cleared in the same transaction that creates
  the shop) then "Loyalty card"
  (link, stamps, reward + preview). The final `POST /onboarding` re-validates everything and
  sends the owner back to step 1 if that's where an error is. `EnsureOwnerHasShop` (alias `shop.ready`) on the whole
  dashboard group sends an owner without a shop there — every dashboard action assumes
  `auth()->user()->shop` exists. Shared form pieces with the admin "Add shop":
  `Components/Dashboard/ShopFields.jsx`.
- Not built yet: email verification and password reset (mail is `log` locally).

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
- **Owner activation is a cohort**: owners who *signed up* in the chosen range → set up their
  shop → gave a first stamp → still stamping (last 14 days), plus the median days from sign-up
  to first stamp. Every bar is the same group, so the filter changes all of them (an earlier
  all-time version only moved the last bar and looked broken).
- **Ratings are per shop**, so the headline is the average *shop* rating (each shop's own
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
