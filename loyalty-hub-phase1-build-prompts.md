# Digital Loyalty & Social Hub (MVP): Staged Build Prompts

How to use this file:
1. Paste the **Master Context** at the start of every new coding session (or save it as `CLAUDE.md` in the repo root).
2. Paste one **Stage Prompt** at a time, in order.
3. Do not start the next stage until the current stage's **Manual Test Checklist** passes and the work is committed to git.

Each stage ends with the app running with no errors and something you can test by hand.

---

## Master Context (paste first, every session)

```
You are helping me build an MVP: a web-based digital loyalty and social hub for UK
high-street independents (cafes, bakeries, barbers, pubs). It replaces paper punch cards
and doubles as a customer engagement hub (Google Review, Instagram, Wi-Fi).

STACK (fixed, do not substitute):
- Laravel 11.x, PHP 8.2+, MySQL
- Blade + Tailwind CSS (via Vite) + Alpine.js / vanilla JS
- html5-qrcode for the staff camera scanner
- Pusher Channels for real-time (free tier)
- Deployment target: Hostinger shared hosting (no root, no long-running queue workers,
  no Redis, no Docker). So: QUEUE_CONNECTION=sync, CACHE/SESSION=file or database,
  no artisan queue:work, no websockets server of our own.

PRODUCT RULES:
- Customers: no app, no passwords. First visit = name + UK mobile once; a persistent
  uuid is stored in localStorage. The browser is READ-ONLY: it can never change stamps.
- Staff: device authenticated via a long-lived bearer token stored in browser storage;
  the staff scanner is a PWA (Add to Home Screen).
- Customer QR payload is exactly: TOKEN:{customer_uuid}|SHOP:{shop_id}
- Multi-tenant: every query touching cards/stamps is scoped by shop_id.
- Cooldown: one stamp per customer per shop per configurable window
  (config value, default 8 hours).
- UK context: Europe/London timezone, UK mobile number validation/normalisation (+44).

DATABASE (already agreed): shops, customers, customer_shop_cards, stamp_logs
(action_type enum: stamp_added, reward_redeemed). Use foreign keys, unique indexes
(customers.uuid, customers.phone, shops.slug, and unique customer_id+shop_id on cards).

WORKING RULES:
- Work only on the stage I give you. Do not build features from later stages.
- After finishing: run migrations, run `php artisan test`, run `npm run build`, and
  confirm the app boots with no errors. Fix anything failing before you stop.
- Write feature tests for the logic you add.
- Keep code simple and conventional (controllers, form requests, services where useful).
  No unnecessary packages.
- At the end, give me: files changed, commands to run, and a manual test checklist.
```

---

## Stage 0: Project Skeleton and Environment

**Goal:** A clean Laravel 11 app that boots, with Tailwind working, correct timezone, and a health page.

```
STAGE 0: Project skeleton.

Tasks:
1. Create a new Laravel 11 project. Configure .env.example for MySQL, APP_TIMEZONE=Europe/London,
   QUEUE_CONNECTION=sync, SESSION_DRIVER=file, CACHE_STORE=file.
2. Install and configure Tailwind CSS with Vite. Add Alpine.js via yarn.
3. Create a base Blade layout (resources/views/layouts/app.blade.php) that is mobile-first,
   includes the viewport meta tag, and loads Vite assets.
4. Create a simple landing route "/" that shows the product name and a placeholder line.
5. Add config/loyalty.php with: stamp_cooldown_hours (env STAMP_COOLDOWN_HOURS, default 8),
   default_max_stamps (6).
6. Initialise git with a sensible .gitignore and make the first commit.

Acceptance:
- `php artisan serve` + `npm run dev` shows the landing page styled with Tailwind.
- `php artisan test` passes (include one smoke test that "/" returns 200).
- `npm run build` succeeds.
```

**Manual test checklist**
- [ ] Landing page loads at `http://localhost:8000` and Tailwind styles are visible.
- [ ] Page looks right at phone width.
- [ ] `php artisan tinker` → `now()->timezoneName` returns `Europe/London`.

---

## Stage 1: Database, Models, Seed Data

**Goal:** All four tables, models with relationships, and demo data to test against.

```
STAGE 1: Database schema, models, seeders.

Tasks:
1. Create migrations for shops, customers, customer_shop_cards, stamp_logs exactly per the
   agreed schema:
   - shops: id, name, slug (unique), max_stamps (tinyint, default 6), reward_title,
     google_review_url (text, nullable), instagram_url (nullable), wifi_ssid (nullable),
     wifi_password (nullable), timestamps
   - customers: id, uuid (char 36, unique), name, phone (unique), timestamps
   - customer_shop_cards: id, customer_id FK, shop_id FK, current_stamps (tinyint default 0),
     rewards_claimed (int default 0), last_stamped_at (nullable timestamp),
     unique(customer_id, shop_id), timestamps
   - stamp_logs: id, customer_id FK, shop_id FK, action_type enum(stamp_added,
     reward_redeemed), created_at, index on (shop_id, created_at)
2. Create Eloquent models with relationships (Shop hasMany cards/logs, Customer hasMany cards,
   Card belongsTo customer & shop, etc.), fillable/casts, and a model-level auto-generated
   uuid for Customer.
3. Create factories for all models and a DatabaseSeeder that creates 2 demo shops
   ("Artisan Cafe" slug artisan-cafe, "Urban Barber" slug urban-barber) with realistic
   values (reward_title, fake Google/Instagram URLs, wifi details) plus 5 demo customers.
4. Feature/unit tests: relationships work, unique constraints hold, uuid is auto-generated.

Acceptance:
- `php artisan migrate:fresh --seed` runs clean.
- `php artisan test` passes.
```

**Manual test checklist**
- [ ] `migrate:fresh --seed` completes without errors.
- [ ] In `tinker`: `Shop::first()->cards`, `Customer::first()->uuid` all work.
- [ ] Inspect tables in a DB client: foreign keys and unique indexes exist.

---

## Stage 2: Customer Registration and Loyalty Card

**Goal:** The full customer journey from QR scan to a rendered card, with no stamping yet.

```
STAGE 2: Customer registration + loyalty card page.

Routes:
- GET  /s/{shop:slug}          -> customer card page (404 for unknown slug)
- POST /s/{shop:slug}/register -> JSON: registers or restores customer
- GET  /s/{shop:slug}/card/{uuid} -> JSON: current card state (read-only)

Behaviour:
1. The page loads with JS. On load, JS reads localStorage key `loyalty_uuid`.
   - No uuid -> show a first-time modal asking Name and UK Mobile Number.
   - Has uuid -> call the card endpoint and render the card with no forms.
   - If the uuid is not recognised by the server (404), clear localStorage and show the modal.
2. Register endpoint (Form Request validation):
   - name required, max 150
   - phone: valid UK mobile, normalised to +447XXXXXXXXX format (accept 07..., +447..., 447...)
   - Creates customer (or finds an existing one by normalised phone) and creates the
     customer_shop_card for this shop if missing. Returns uuid + card state.
   - Rate-limit this route (e.g. 10/min per IP).
3. Card UI (mobile-first, Tailwind, uses the shop name and reward_title):
   - Stamp grid showing current_stamps out of max_stamps (filled vs empty circles)
   - Progress text (e.g. "2 / 6, free coffee at 6")
   - Rewards claimed count
   - A QR code rendered client-side (use a small JS lib such as qrcode via npm) whose
     payload is EXACTLY: TOKEN:{customer_uuid}|SHOP:{shop_id}
4. The card endpoint returns only what the card needs: stamps, max_stamps, reward_title,
   rewards_claimed, shop_id. No write capability anywhere in the customer flow.

Tests: registration validation (bad phone, missing name), phone normalisation, existing phone
returns the same customer, card created per shop, unknown uuid returns 404, unknown slug
returns 404.

Acceptance: complete the first-visit flow, reload, and see the card with no modal.
```

**Manual test checklist**
- [ ] Visit `/s/artisan-cafe` in a fresh browser → modal appears.
- [ ] Submit invalid phone → friendly validation error. Submit valid → card appears with 0/6 and a QR.
- [ ] Reload → card appears instantly, no modal.
- [ ] Clear localStorage → modal again; registering with the same phone gives the same card back.
- [ ] Visit `/s/urban-barber` with the same browser → separate card for that shop.
- [ ] Open on your actual phone via your LAN IP or a tunnel (ngrok/Cloudflare Tunnel) and check the layout.

> Known MVP trade-off: restoring a card by phone number alone means anyone who knows a number could restore that card on another device. This is acceptable for a stamp card, but note it for later (an SMS or PIN check would fix it).

---

## Stage 3: Hub Engagement Tiles

**Goal:** Action tiles under the card: Google Review, Instagram, Wi-Fi.

```
STAGE 3: Hub engagement tiles on the customer card page.

Tasks:
1. Below the stamp card, render tiles ONLY for fields the shop has set:
   - "Leave a Google Review" -> opens google_review_url in a new tab (rel="noopener")
   - "Follow us on Instagram" -> opens instagram_url
   - "Free Wi-Fi" -> tapping reveals SSID and password in a panel with a
     "Copy password" button (navigator.clipboard with a graceful fallback)
2. Return the wifi and link data in the card JSON only when present. Do not expose
   anything else about the shop.
3. Tiles must be large, tappable, and accessible (aria labels, sufficient contrast).
4. Update the seeder so Artisan Cafe has all three, Urban Barber has only Instagram, so
   both display cases can be tested.
5. Tests: card JSON omits null fields; tiles are absent in the rendered data when null.

Acceptance: both shops display the correct set of tiles and all links work.
```

**Manual test checklist**
- [ ] Artisan Cafe shows 3 tiles; Urban Barber shows only Instagram.
- [ ] Wi-Fi reveal shows the SSID and password; the copy button works on mobile Safari and Chrome.
- [ ] Links open in a new tab.

---

## Stage 4: Shop Owner Dashboard

**Goal:** Owners can log in, edit shop settings, and generate the staff onboarding link and QR.

```
STAGE 4: Owner dashboard.

Tasks:
1. Add an `owner_id`-style relation: create a `users` table use (Laravel default) and add
   shops.user_id (FK). Implement simple email+password login for owners ONLY
   (use Laravel Breeze-style controllers manually or install Breeze with the Blade stack and
   remove anything unneeded; no registration page: owners are created via seeder/artisan
   command `php artisan owner:create`).
2. Protected /dashboard area (auth middleware, policy so an owner can only see their own shop):
   - Overview: shop name, customer count, stamps today, rewards redeemed (simple counts
     from stamp_logs; will show 0 for now)
   - Settings form: name, max_stamps (4-12), reward_title, google_review_url,
     instagram_url, wifi_ssid, wifi_password. Validate URLs.
   - "Customer QR poster" section: shows the shop's counter QR (URL /s/{slug}) with a
     download/print-friendly view.
3. Create a `staff_devices` table: id, shop_id FK, name, token_hash (sha256 of the token),
   last_used_at, revoked_at, timestamps. In the dashboard: "Add staff device" creates a
   device with a random 64-char token, shows a ONE-TIME onboarding URL
   /staff/setup/{token} plus its QR code (the plain token is never stored, only the hash).
   List devices with a Revoke button.
4. Tests: owner cannot access another owner's shop; settings validation; token is hashed;
   revoke sets revoked_at.

Acceptance: log in as the seeded owner, change the reward text, see the customer card update,
and generate a staff onboarding QR.
```

**Manual test checklist**
- [ ] Log in with the seeded owner credentials; wrong password is rejected.
- [ ] Edit the reward title → refresh the customer card page → the change shows.
- [ ] The poster QR opens the customer page when scanned.
- [ ] "Add staff device" shows the QR/link once; reloading the dashboard does NOT show the token again.
- [ ] Revoke works. A second owner can't open the first owner's shop pages.

---

## Stage 5: Staff Device Auth and PWA Scanner Shell

**Goal:** A staff phone can be onboarded once, opens as a home-screen app, and the camera scans a QR and displays the decoded value (no stamping yet).

```
STAGE 5: Staff device onboarding + PWA scanner shell.

Tasks:
1. GET /staff/setup/{token}: validate the token against staff_devices.token_hash (not revoked),
   then render a page that stores the token in localStorage (`staff_token`) and redirects to
   /staff. Show a clear success message. Do NOT put the token in a cookie.
2. Create middleware `AuthenticateStaffDevice`: reads `Authorization: Bearer {token}`,
   hashes it, looks up a non-revoked device, sets the device + shop on the request, updates
   last_used_at. Return 401 JSON otherwise.
3. GET /staff: the scanner page. On load, if no staff_token in localStorage, show
   "This device is not set up" with instructions. Otherwise call GET /api/staff/me
   (protected by the middleware) to confirm the token and show the shop name.
4. Integrate html5-qrcode: start the rear camera, scan, and on decode show the raw payload
   in a result panel plus parse it client-side (TOKEN:...|SHOP:...) and show the parts.
   Handle camera permission denied with a friendly message. Prevent duplicate rapid decodes
   (debounce ~2s).
5. PWA basics: manifest.webmanifest (name, short_name, display: standalone, icons,
   start_url /staff, theme colour), a minimal service worker that caches only the static
   shell (never caches API calls), and meta tags for iOS home screen.
6. Tests: middleware accepts a valid token, rejects revoked/invalid/missing tokens; setup
   route rejects a bad token.

Acceptance: onboard a phone through the QR from Stage 4, add to home screen, open it, and
scan a customer QR from another device. The decoded payload is shown.
```

**Manual test checklist**
- [ ] Scan the onboarding QR on a phone → success message → redirected to `/staff`.
- [ ] `/staff` shows the shop name; a revoked device shows an error on next load.
- [ ] Camera opens (note: camera needs HTTPS, so use a tunnel like ngrok/Cloudflare Tunnel when testing on a phone).
- [ ] Scanning a customer's card QR displays the correct payload.
- [ ] Add to Home Screen works and opens full-screen.

---

## Stage 6: Stamp and Redeem Logic (Core Business Logic)

**Goal:** Scanning actually stamps. Cooldown, shop isolation, and reward redemption all enforced server-side.

```
STAGE 6: Stamping and redemption.

Endpoint: POST /api/staff/scan (behind AuthenticateStaffDevice)
Body: { "payload": "TOKEN:{uuid}|SHOP:{id}" }

Implement a StampService with a single transactional method that:
1. Parses the payload strictly (regex). Invalid format -> 422 with code `invalid_qr`.
2. Verifies SHOP id equals the authenticated device's shop_id, otherwise 403 with code
   `shop_mismatch` and message e.g. "This card belongs to a different business."
3. Finds the customer by uuid (404 `customer_not_found`) and the card for that shop
   (create it if the customer has never visited this shop's page, so scans still work).
4. Locks the card row (lockForUpdate inside DB::transaction) to prevent double-scan races.
5. Cooldown: if last_stamped_at is within config('loyalty.stamp_cooldown_hours'), reject with
   409 `cooldown` and message "Already stamped today at 11:42 AM" (format the time in
   Europe/London), plus the time when the next stamp is allowed.
6. If current_stamps >= max_stamps (card full): treat the scan as REDEMPTION: set
   current_stamps = 0, rewards_claimed += 1, log reward_redeemed. Response says reward
   redeemed. (Redemption does not add a stamp and does not use the cooldown.)
7. Otherwise add a stamp: current_stamps += 1, last_stamped_at = now(), log stamp_added.
   If this stamp fills the card, the response flags `reward_ready: true`.
8. Responses are JSON: { status, code, message, stamps, max_stamps, customer_name }.
   Never trust anything from the client except the payload string.

Staff UI (on /staff):
- After a scan, call the endpoint and show a full-width GREEN banner for success (with
  customer first name and "3/6"), an AMBER banner for cooldown, RED for errors, plus
  a success beep (Web Audio or a short audio file) and vibration where supported.
  Auto-dismiss after ~3s and resume scanning. Show the special "REWARD READY, redeem on next
  scan" state clearly, and "REWARD REDEEMED" after redemption.
- Add a today's-stamps counter on the staff screen (GET /api/staff/summary).

Dashboard: make the Stage 4 overview counts real (stamps today, rewards redeemed).

Tests (important): successful stamp, cooldown rejection, exact-boundary of cooldown, shop
mismatch, unknown uuid, malformed payload, full card -> redemption resets and increments
rewards_claimed, concurrent double-scan only stamps once, logs written correctly.

Acceptance: end-to-end stamp from a real customer phone to a real staff phone.
```

**Manual test checklist**
- [ ] Scan a customer → green banner and beep, the count goes up; reload the customer page and it shows the new count.
- [ ] Scan the same customer again immediately → amber "Already stamped today at HH:MM".
- [ ] Set `STAMP_COOLDOWN_HOURS=0` temporarily (or edit `last_stamped_at` in the DB) and stamp up to the max → "reward ready".
- [ ] One more scan → "reward redeemed", the card resets to 0, and rewards_claimed goes up by 1.
- [ ] Scan an Urban Barber customer QR with the Artisan Cafe staff device → red shop-mismatch error.
- [ ] Scan a random QR code (e.g. a URL) → invalid QR error.
- [ ] The dashboard counts match what you did.

---

## Stage 7: Real-Time Feedback (Pusher)

**Goal:** The customer's screen updates instantly with a chime and confetti when staff stamp their card.

```
STAGE 7: Real-time customer updates with Pusher.

Tasks:
1. Install pusher/pusher-php-server and pusher-js. Configure broadcasting for Pusher via .env
   (PUSHER_APP_ID/KEY/SECRET/CLUSTER=eu). Use BROADCAST_CONNECTION=pusher. Keep
   QUEUE_CONNECTION=sync so events broadcast immediately without workers.
2. Create a `CardUpdated` event implementing ShouldBroadcastNow, broadcast on the PUBLIC
   channel `card.{customer_uuid}.{shop_id}`, event name `card.updated`, payload:
   { stamps, max_stamps, action ('stamp_added'|'reward_redeemed'), reward_ready }.
   Only minimal data, no phone numbers or names. Dispatch it from StampService AFTER the
   DB transaction commits, and never let a Pusher failure break or roll back a stamp
   (wrap in try/catch and log).
3. Customer page: subscribe to that channel with pusher-js once the card is loaded. On event:
   - animate the newest stamp filling in ("bump" animation)
   - lightweight confetti (small library or CSS/canvas, no heavy dependencies)
   - play a chime (audio unlocked by a first user tap, since mobile browsers block autoplay;
     add a small "Enable sound" hint that disappears after the first tap)
   - special celebration when the card is full / reward redeemed
4. Resilience: if Pusher is not configured or the connection fails, the page still works
   (the customer can refresh). Also re-fetch the card on `visibilitychange` (tab returns
   to foreground) so stale screens correct themselves.
5. Tests: the event is dispatched with the correct channel/payload (Event::fake); a
   broadcast failure does not affect the stamp result.

Acceptance: the customer phone visibly reacts within ~1s of the staff scan.
```

**Manual test checklist**
- [ ] Open the customer card on one device and the staff scanner on another. Scan → the customer screen animates, plays the chime, and shows the updated count without a refresh.
- [ ] Filling the last stamp shows the "reward ready" celebration.
- [ ] Set wrong Pusher keys → staff scanning still stamps successfully; the customer sees the update after a refresh.
- [ ] Put the customer phone to sleep and wake it → the count is correct.
- [ ] Check the Pusher dashboard debug console shows the events.

---

## Stage 8: Hardening, QA, and Hostinger Deployment

**Goal:** Production-ready MVP, deployable to shared hosting.

```
STAGE 8: Hardening + deployment readiness.

Tasks:
1. Security review and fixes:
   - Rate limiting: register (per IP), scan (per device token), staff setup (per IP)
   - CSRF correct on web forms; API routes for staff use bearer tokens only
   - Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy) via
     middleware; a sensible CSP is optional
   - Confirm no endpoint lets the customer side modify stamps; confirm all queries are
     scoped by shop_id; confirm no sensitive data in JSON responses or logs
   - Force HTTPS in production (AppServiceProvider / trusted proxies)
2. UX polish: friendly 404/419/429/500 pages, loading states, offline message on the customer
   and staff pages, empty states in the dashboard.
3. Add an artisan command `php artisan shop:create` (name, slug, owner email/password) so
   new businesses can be onboarded without seeding.
4. Add database indexes review (stamp_logs by shop/date, cards by customer/shop) and a
   basic pagination-based "recent activity" list on the dashboard.
5. Hostinger deployment guide (write DEPLOYMENT.md):
   - Build assets locally (`npm run build`) and commit/upload public/build
   - Folder layout for hPanel: app outside public_html with public/ contents in
     public_html, or a symlink/.htaccess approach. Show the exact index.php path edits
   - .env production values, `php artisan key:generate`, `migrate --force`,
     `config:cache`, `route:cache`, `view:cache`, storage:link alternatives
   - PHP version selection in hPanel (8.2+) and required extensions
   - Notes on no queue worker (we use sync) and no scheduler needed
   - A post-deploy smoke-test checklist
6. Full test pass: `php artisan test` must be green. Add a README with local setup steps.

Acceptance: fresh clone -> README steps -> working app; DEPLOYMENT.md steps are complete
and unambiguous.
```

**Manual test checklist (full end-to-end regression)**
- [ ] Create a new shop with `shop:create`, log in, configure it, and print the poster QR.
- [ ] Onboard a staff device, register a new customer on a real phone, stamp them through the full card, and redeem.
- [ ] Hit the rate limits deliberately and see a friendly message.
- [ ] Deployed on Hostinger over HTTPS: the camera works, Pusher events arrive, and the app survives a cache clear.

---

## Suggested Order and Rough Effort

| Stage | Focus | Depends on |
|---|---|---|
| 0 | Skeleton | none |
| 1 | Database and models | 0 |
| 2 | Customer card | 1 |
| 3 | Hub tiles | 2 |
| 4 | Owner dashboard and device tokens | 1 |
| 5 | Staff auth and scanner shell | 4 |
| 6 | Stamp/redeem logic | 2, 5 |
| 7 | Real-time | 6 |
| 8 | Hardening and deploy | all |

Stages 3 and 4 are independent of each other, so you can swap them if you prefer.

## Design Notes to Keep in Mind
- **Camera needs HTTPS.** For phone testing use a tunnel (Cloudflare Tunnel or ngrok) from Stage 5 onwards.
- **Static QR risk:** a customer's QR contains a permanent uuid, so a screenshot could be shared. The cooldown limits the damage. A rotating short-lived token is a sensible post-MVP upgrade.
- **Public Pusher channel:** the channel name includes the secret-ish uuid and the payload carries only counts, which is fine for MVP. Private channels can come later.
- **Shared hosting:** everything here avoids workers, Redis and websockets servers, which is why events use `ShouldBroadcastNow`.
