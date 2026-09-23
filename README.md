# Loyalty Hub

Digital loyalty cards and a small customer hub for UK high-street independents (cafes,
bakeries, barbers, pubs). Customers scan a QR at the counter, register once with a name and
UK mobile, and get a stamp card in the browser with no app and no password. Staff stamp cards
with a phone camera scanner. Owners manage their shop from a dashboard.

**Stack:** Laravel 12 (PHP 8.2+), MySQL/MariaDB, Inertia.js + React, Tailwind CSS v4 (Vite),
html5-qrcode, Pusher Channels.

## Local setup

Requirements: PHP 8.2+, Composer, MySQL or MariaDB (XAMPP works), Node 20+ and Yarn.

```bash
git clone <repo-url> loyalty-hub
cd loyalty-hub

composer install
yarn install

cp .env.example .env
php artisan key:generate
```

Create an empty database called `loyalty_hub`, then check the `DB_*` values in `.env`. The
defaults match a stock XAMPP install (`root`, no password).

```bash
php artisan migrate:fresh --seed
yarn run build        # or `yarn dev` for hot reload while you work
php artisan serve
```

Open http://localhost:8000.

### Seeded accounts and shops

All seeded passwords are `password`.

| Who | Login | Where |
|---|---|---|
| Admin | `admin@loyaltyhub.test` | `/login` → `/admin` |
| Owner of Artisan Cafe | `owner@artisan-cafe.test` | `/login` → `/dashboard` |
| Owner of Urban Barber | `owner@urban-barber.test` | `/login` → `/dashboard` |

Customer cards: http://localhost:8000/s/artisan-cafe and http://localhost:8000/s/urban-barber

### Trying the staff scanner

1. Log in as an owner, go to **Staff devices** on the dashboard and add a device.
2. Open the one-time setup link it shows, `/staff/setup/{token}`. The token is saved in the
   browser and you're sent to `/staff`.
3. The camera only works over **HTTPS**, or on `localhost`. To test on a real phone, run a
   tunnel such as `cloudflared tunnel --url http://localhost:8000` or
   `ngrok http 8000`, and open the tunnel URL on the phone.

### Real-time updates (optional)

Fill in `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` and `PUSHER_APP_CLUSTER` in
`.env`, then rebuild assets (`yarn run build`) so the `VITE_PUSHER_*` values are picked up.
Without Pusher keys everything still works. The customer card just updates on refresh, or
when the tab comes back into focus, instead of live.

## Tests

```bash
php artisan test
```

Pest feature tests live in `tests/Feature`, one file per feature area. Format code with
`vendor/bin/pint` before committing.

## Useful config

| `.env` key | Default | What it does |
|---|---|---|
| `STAMP_COOLDOWN_HOURS` | `8` | Minimum gap between two stamps for one customer at one shop |
| `APP_TIMEZONE` | `Europe/London` | All displayed times |
| `QUEUE_CONNECTION` | `sync` | Keep this. The app never needs a queue worker. |

## Project notes

Product rules, architecture decisions and stage history are in [`CLAUDE.md`](CLAUDE.md).
The original build plan is in
[`loyalty-hub-phase1-build-prompts.md`](loyalty-hub-phase1-build-prompts.md).
