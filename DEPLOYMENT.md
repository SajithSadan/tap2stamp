# Deploying Tada Tap to Hostinger

How to get the app onto Hostinger shared hosting and keep it updated. Hostinger gives us no SSH,
so a GitHub Actions workflow builds the app and uploads it over FTP, and anything that would
normally be an `artisan` command is either done by hand once or triggered over HTTPS.

**In short:** set up Hostinger and GitHub once (sections 1–5). After that, every push to `main`
deploys automatically. The only recurring manual job is re-uploading `vendor/` when PHP packages
change (section 7).

---

## 0. The layout on the server

Only Laravel's `public/` folder may be reachable from the web. The rest of the project (code,
config, logs, `vendor/`) lives in a folder **outside** the web root.

| Name        | What it is                                                     | Example                               |
| ----------- | -------------------------------------------------------------- | ------------------------------------- |
| App domain  | The address customers and staff use                            | `app.tadatap.co.uk`                 |
| `APP_DIR`   | The whole Laravel project. **Not** web-reachable.              | `domains/tadatap.co.uk/tadatap-app` |
| `WEB_DIR`   | The app domain's web root. Gets the contents of `public/`.     | `domains/tadatap.co.uk/public_html/app` |

```
domains/tadatap.co.uk/
├── public_html/            ← WordPress marketing site (leave alone)
│   └── app/                ← WEB_DIR: index.php, .htaccess, build/, icons/, uploads/ …
└── tadatap-app/            ← APP_DIR: app/, bootstrap/, config/, routes/, storage/, vendor/, .env …
```

Write down your own two paths before you start — they're used throughout. The examples below
use the paths in the table.

`WEB_DIR/index.php` is edited once (section 5.3) so it loads Laravel from `APP_DIR` and treats
`WEB_DIR` as the public folder. That's what makes the Vite build and owner uploads (`/uploads`)
work from `WEB_DIR` — `UPLOADS_ROOT` doesn't need setting.

---

## 1. Hostinger: domain, SSL and PHP

In **hPanel**:

1. **Domain** — create the app's subdomain (Domains → Subdomains) or add its domain. Note the
   folder Hostinger creates for it; that's your `WEB_DIR`. Tick "custom folder" if you want to
   choose it.
2. **SSL** — Security → SSL → install the free certificate for the app domain and turn on
   "Force HTTPS". HTTPS is required: the staff camera scanner and the `/deploy/*` endpoints
   refuse to work without it.
3. **PHP** — Advanced → PHP Configuration → **PHP 8.2** (8.3 also works). Make sure these
   extensions are on: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `curl`, `xml`.
4. **Create `APP_DIR`** — Files → File Manager → create the empty `tadatap-app` folder next to
   `public_html`.

## 2. Hostinger: database

Databases → MySQL Databases → create a database and a user with a strong password. Note:

- database name (e.g. `u123456789_tadatap`)
- username (e.g. `u123456789_tadatap`)
- password
- host — usually `localhost`

## 3. Hostinger: FTP account for GitHub

GitHub uploads the app with its own FTP account, so it never uses your main hosting login and
you can revoke it on its own.

1. hPanel → **Files → FTP Accounts**.
2. At the top of that page, note the **FTP hostname / IP** (e.g. `ftp.tadatap.co.uk` or an IP
   address). Port is `21`.
3. Under **Create a new FTP account**:
   - **Directory** — the folder that contains **both** `APP_DIR` and `WEB_DIR`
     (here: `domains/tadatap.co.uk`). The account can't reach anything outside it.
   - **Username** — e.g. `deploy`. Hostinger adds a prefix, so the real username looks like
     `u123456789.deploy`; copy the full one shown in the list.
   - **Password** — a long random one (a password manager's generator is ideal). You'll only
     paste it into GitHub.
4. **Check it once with an FTP client** (e.g. FileZilla, "FTP over TLS" if offered). Log in with
   the new account and look at what `/` is. Your two folders must be visible from there; their
   paths relative to `/` are what goes into GitHub, e.g.:
   - `FTP_APP_DIR` = `tadatap-app/`
   - `FTP_WEB_DIR` = `public_html/app/`

   Both **must end with `/`**.

If you ever think the password leaked: delete the FTP account in hPanel, create a new one, and
update the GitHub secrets.

## 4. GitHub: repository secrets

On GitHub: the repository → **Settings → Secrets and variables → Actions → New repository
secret**. Add each of these:

| Secret                    | Value                                                                  |
| ------------------------- | ---------------------------------------------------------------------- |
| `FTP_SERVER`              | FTP hostname / IP from section 3 (no `ftp://`)                         |
| `FTP_USERNAME`            | full FTP username, e.g. `u123456789.deploy`                            |
| `FTP_PASSWORD`            | the FTP account's password                                             |
| `FTP_APP_DIR`             | e.g. `tadatap-app/`                                                    |
| `FTP_WEB_DIR`             | e.g. `public_html/app/`                                                |
| `APP_URL`                 | e.g. `https://app.tadatap.co.uk` (no trailing `/`)                   |
| `DEPLOY_MIGRATE_TOKEN`    | same value as in the server `.env` (section 5.2)                       |
| `VITE_APP_NAME`           | `Tada Tap`                                                             |
| `VITE_PUSHER_APP_KEY`     | Pusher app key (same as `PUSHER_APP_KEY`)                              |
| `VITE_PUSHER_APP_CLUSTER` | Pusher cluster, e.g. `eu`                                              |

The `VITE_*` values are baked into the JavaScript at build time — if the Pusher ones are missing,
live stamp updates on the customer card silently won't work.

Secrets are never shown again after saving and GitHub hides them in logs. Never `echo` them in
a workflow step.

## 5. One-time setup on the server

Do these **before** the first deploy, in this order.

### 5.1 Upload `vendor/`

The workflow doesn't upload `vendor/` (too many files over FTP), so it goes up by hand as one zip.
On your Mac, in the project folder:

```bash
composer install --no-dev --optimize-autoloader   # production packages only
zip -rq vendor.zip vendor
composer install                                  # put your dev packages back
```

File Manager → `APP_DIR` → upload `vendor.zip` → right-click → **Extract** → delete the zip.
You should now have `APP_DIR/vendor/autoload.php`.

### 5.2 Create `APP_DIR/.env`

File Manager → `APP_DIR` → new file `.env`. Start from `.env.example` and change at least:

```dotenv
APP_NAME="Tada Tap"
APP_ENV=production
APP_KEY=            # run `php artisan key:generate --show` locally and paste the output
APP_DEBUG=false
APP_TIMEZONE=Europe/London
APP_URL=https://app.tadatap.co.uk

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_tadatap
DB_USERNAME=u123456789_tadatap
DB_PASSWORD=...

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=eu

# Long random value; same as the DEPLOY_MIGRATE_TOKEN GitHub secret.
# php artisan tinker --execute="echo Str::random(64);"
DEPLOY_MIGRATE_TOKEN=...

# The first admin login, created by /deploy/seed-admin. Never overwritten later.
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=...

# Optional: "Continue with Google". Leave blank to hide the button.
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=/auth/google/callback

# "Find address" on the shop address forms (findaddress.io). Server-side only;
# leave blank to hide it - owners then type the address.
FINDADDRESS_API_KEY=...

# Owners ordering the counter display online (section 5.4). Leave blank to
# hide online ordering - the admin can still record bank transfer / cash orders.
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Keep `APP_KEY` safe and never change it after going live — it encrypts sessions and cookies.
The file must never be inside `WEB_DIR`.

### 5.3 Upload the edited `index.php` to `WEB_DIR`

The workflow deliberately never uploads `index.php`, so this hand-edited copy stays put. Create
`WEB_DIR/index.php` with the content below. Adjust `$appRoot` to the path from `WEB_DIR` to
`APP_DIR` — in the example, `public_html/app` → up two levels → `tadatap-app`.

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The Laravel project (APP_DIR), relative to this web root.
$appRoot = __DIR__.'/../../tadatap-app';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

// This folder is the public folder, not APP_DIR/public: the Vite build and
// owner uploads (/uploads) live here.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
```

### 5.4 Stripe (online orders)

Owners pay for the counter display on Stripe's own checkout page, so only the **secret key**
and a **webhook** are needed — no card details ever touch our server.

1. Sign up / log in at **dashboard.stripe.com** and finish activating the account (business
   details, bank account for payouts). Until then use **Test mode** keys (`sk_test_…`) and the
   test card `4242 4242 4242 4242`.
2. **Developers → API keys** → copy the **Secret key** into `STRIPE_SECRET`.
3. **Developers → Webhooks → Add destination**:

   | Setting          | Value                                                                  |
   | ---------------- | ---------------------------------------------------------------------- |
   | Events from      | Your account                                                           |
   | Payload style    | **Snapshot** (the full event — not "Thin")                             |
   | API version      | leave the default                                                      |
   | Events           | `checkout.session.completed` and `checkout.session.async_payment_succeeded` |
   | Destination type | Webhook endpoint                                                       |
   | Endpoint URL     | `https://app.tadatap.co.uk/stripe/webhook` (your app domain, HTTPS)    |

   Then open the endpoint, reveal its **Signing secret** (`whsec_…`) and put it in
   `STRIPE_WEBHOOK_SECRET`.
4. Test and live mode are **separate** in Stripe — each has its own secret key, webhook and
   signing secret. If you test first, create the webhook in Test mode too, and switch both
   `.env` values together when going live.
5. **Check it**: make a test payment, then Stripe → the endpoint → **Event deliveries** should
   show **200**. A **400** means `STRIPE_WEBHOOK_SECRET` doesn't match that endpoint. The order
   shows as paid under Admin → Orders.

`.env` on the server:

```dotenv
STRIPE_SECRET=sk_live_...        # Developers → API keys → Secret key
STRIPE_WEBHOOK_SECRET=whsec_...  # the webhook endpoint's signing secret
```

The webhook confirms a payment even if the owner closes the tab before coming back from
Stripe; the page they return to confirms it too, whichever comes first. Prices are set in the
app (Admin → Orders → Products), not in Stripe.

**Testing locally**: Stripe can't reach `localhost`. Either skip the webhook (the return page
still confirms payments), or install the Stripe CLI and run

```bash
stripe listen --forward-to http://localhost:8000/stripe/webhook
```

and put the `whsec_…` it prints into your **local** `.env` as `STRIPE_WEBHOOK_SECRET`, with
your `sk_test_…` key as `STRIPE_SECRET`.

## 6. First deploy

Push (or merge) to `main`. The workflow in `.github/workflows/deploy.yml` then:

1. builds the frontend with Vite (`yarn build`),
2. uploads the project to `FTP_APP_DIR` — without `vendor/`, `node_modules/`, `tests/`, `.env`
   and `public/`,
3. uploads the contents of `public/` (including the fresh `build/`) to `FTP_WEB_DIR` — without
   `index.php` and `uploads/`, so the edited entry file and owners' banners/logos are never
   touched,
4. calls `POST {APP_URL}/deploy/migrate` and `POST {APP_URL}/deploy/seed-admin` with the
   `DEPLOY_MIGRATE_TOKEN`, so the database is migrated and the admin account exists.

The first run uploads everything and takes a few minutes; later runs only upload changed files.
Watch it under the repository's **Actions** tab.

Then check on a phone and a computer:

- [ ] `https://<app domain>` opens over HTTPS with no errors
- [ ] `https://<app domain>/.env` and `/../tadatap-app` give 404 — nothing private is reachable
- [ ] you can log in at `/login` with `ADMIN_EMAIL` / `ADMIN_PASSWORD` and see the admin dashboard
- [ ] create a test shop + owner; log in as that owner
- [ ] upload a banner on Theme → Banner & logo; it shows on the customer card page
- [ ] register as a customer on `/s/<shop-slug>`; the QR shows
- [ ] add a staff device, open its setup link on a phone, sign in with a staff PIN, scan the
      customer QR — the stamp appears on the customer's card **live** (Pusher)
- [ ] "Add to Home Screen" works for the staff scanner

## 7. Day-to-day

- **Deploying** = merging into `main`. Nothing else to do.
- **When `composer.lock` changes** (a PHP package was added or updated), the server needs the
  new `vendor/` **before** the code that uses it, or the site crashes with "class not found":
  1. repeat section 5.1 (build the zip, upload, extract over the old `vendor/`),
  2. in File Manager, delete `APP_DIR/bootstrap/cache/packages.php` and `services.php` (Laravel
     rebuilds them),
  3. then merge to `main`.
- **Changing `.env`** — edit it in File Manager. It takes effect on the next request.
- **Logs** — `APP_DIR/storage/logs/laravel.log`.
- **Running migrations by hand** (e.g. a failed deploy step):

  ```bash
  curl -fsS -X POST -H "Authorization: Bearer <DEPLOY_MIGRATE_TOKEN>" https://<app domain>/deploy/migrate
  ```

## 8. Troubleshooting

| Symptom                                       | Likely cause                                                                                     |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Workflow fails at the FTP step                | Wrong `FTP_*` secret, or the dir doesn't end in `/`. Log in with FileZilla using the same values. |
| `500` / blank page                            | Check `storage/logs/laravel.log`. Often a missing `APP_KEY`, wrong DB details or missing `vendor/`. |
| "Class not found" after a deploy              | `vendor/` is older than `composer.lock` — see section 7.                                          |
| Page loads without styling                    | `index.php` is missing `usePublicPath(__DIR__)`, or `build/` wasn't uploaded to `WEB_DIR`.         |
| Uploaded banners/logos 404                    | Same as above — uploads must land in `WEB_DIR/uploads`.                                           |
| `/deploy/migrate` returns 403                 | Token differs between `.env` and the GitHub secret, or the call wasn't over `https://`.           |
| Card doesn't update live after a scan         | `VITE_PUSHER_*` secrets missing at build time, or `PUSHER_*` wrong in `.env`.                      |
| Camera won't start on the staff scanner       | The site isn't on HTTPS.                                                                          |
| Owner's "Order now" button is missing         | `STRIPE_SECRET` is empty — the banner then says "get in touch" instead.                           |
| Paid, but the order still says "Awaiting payment" | Webhook not set up, wrong `STRIPE_WEBHOOK_SECRET`, or test/live keys mixed. Stripe → Webhooks shows each delivery attempt. |
