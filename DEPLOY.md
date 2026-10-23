# Integrity Autocars — PHP + MySQL Production Deployment (cPanel / routeafrica.net)

Plain PHP 8.4 + MySQL port of the Next.js site. No frameworks, no Composer —
upload, create the database, fill in `.env`, import data, done.

Canonical production URL: **https://integrityautocars.co.tz** (configured once
via `APP_URL` — never hardcoded; used for sitemap, robots, share links,
password-reset links and email buttons).

---

## 1. What you need before starting

| Requirement | Notes |
|---|---|
| PHP **8.4.27** | Select in cPanel → MultiPHP Manager. Extensions: `pdo_mysql`, `curl`, `gd`, `mbstring`, `fileinfo`, `zlib` (standard on cPanel). |
| MySQL database | cPanel → **MySQL Databases**: create DB + user, grant ALL. |
| SSL certificate | cPanel → **SSL/TLS Status** → run AutoSSL (Let's Encrypt) for `integrityautocars.co.tz`. `.htaccess` already forces HTTPS. |
| Resend API key | https://resend.com → verify your domain → create API key. |
| Upload limits | cPanel → MultiPHP INI Editor: `upload_max_filesize=10M`, `post_max_size=15M` (the site's own checks also enforce 5 MB per image). Verify after deploy. |

## 2. Upload the code

The **web root must point at the `public/` folder**:

1. cPanel → File Manager: upload the whole `php-app` folder into your home
   directory (e.g. `/home/USERNAME/php-app`).
2. cPanel → **Domains** → `integrityautocars.co.tz` → **Document Root**:
   set to `/home/USERNAME/php-app/public`.
   (If you cannot change the docroot, move the *contents* of `public/` into
   `public_html/` and move `app/`, `sql/`, `storage/` one level *above*
   `public_html/` — then edit `public/index.php`'s `require` path and
   `config.php`'s `APP_ROOT` accordingly. The provided `.htaccess` in
   `public/` already blocks direct access to nothing else because
   `app/` + `storage/` live outside the web root.)

Resulting layout:

```
/home/USERNAME/
├── php-app/
│   ├── app/            ← code (NOT web-accessible)
│   ├── sql/            ← schema + migration scripts
│   ├── storage/        ← logs (writable)
│   ├── .env            ← secrets (NOT web-accessible)
│   └── public/         ← WEB ROOT (index.php, .htaccess, assets, uploads)
```

## 3. Create the database

1. cPanel → MySQL Databases → create e.g. `USERNAME_iac` + user `USERNAME_iac`.
2. Import the schema (either works):
   - phpMyAdmin → select DB → Import → `sql/schema.mysql.sql`, **or**
   - SSH: `mysql -u USERNAME_iac -p USERNAME_iac < sql/schema.mysql.sql`

## 4. Configure the app

```bash
cd ~/php-app
cp .env.example .env
nano .env
```

Fill in:

```ini
APP_ENV=production
APP_URL=https://integrityautocars.co.tz
APP_DEBUG=0

DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=USERNAME_iac
DB_USER=USERNAME_iac
DB_PASS=<the MySQL user password>

AUTH_SECRET=<php -r "echo bin2hex(random_bytes(32));">

RESEND_API_KEY=re_xxxxxxxxxxxx
RESEND_FROM="Integrity Autocars <noreply@integrityautocars.co.tz>"
ADMIN_NOTIFY_EMAIL=            # optional; defaults to settings.display_email
```

`config.php` contains the same values as commented **placeholders** — `.env`
overrides them, so upgrading never touches code.

Make `storage/` and `public/uploads/` writable:

```bash
chmod -R 775 storage public/uploads
```

## 5. Import your existing data (Next.js SQLite → MySQL)

The old app's data lives in `db/custom.db` of the Next.js project. Upload that
file (e.g. to `~/custom.db`) and run:

```bash
cd ~/php-app
php sql/migrate-from-sqlite.php /home/USERNAME/custom.db "YourNewPassword123"
```

This copies **everything** — vehicles, images, enquiries, notes, contact
messages, invoices, offers, subscribers, settings, email logs, audit logs and
visit statistics — and re-seeds all staff passwords with bcrypt (PHP cannot
verify the old Node scrypt hashes). Every staff member can log in with the
password you passed, then change it (Staff → Edit) or use Forgot-password.

The script is idempotent — re-running is safe.

## 6. Verify

- https://integrityautocars.co.tz → home page with your real vehicles
- https://integrityautocars.co.tz/inventory
- https://integrityautocars.co.tz/admin → staff login
- `php -r 'echo PHP_VERSION;'` → 8.4.x
- Submit a test enquiry → check cPanel webmail / email_logs (admin dashboard)
- Upload a photo on a vehicle → appears in `public/uploads/`
- Invoice → Share → open the public link → Download PDF

## 7. Security checklist (already built in)

- ✅ HTTPS forced via `.htaccess` (Let's Encrypt/AutoSSL)
- ✅ Cookies: `HttpOnly`, `SameSite=Lax`, `Secure` (auto when HTTPS)
- ✅ CSRF tokens on every POST form
- ✅ Prepared statements everywhere (PDO)
- ✅ Output escaped (`htmlspecialchars`) — no XSS from user data
- ✅ Passwords: bcrypt (`password_hash`)
- ✅ Rate limiting: login 10/min, forgot-password 5/10min, enquiry/contact 5/min, newsletter 3/min (DB-backed, works across PHP-FPM workers)
- ✅ Audit log of every staff action
- ✅ Uploads: MIME check (finfo), 5 MB cap, GD re-encode to ≤1600px WebP, PHP execution blocked in `uploads/.htaccess`
- ✅ `app/`, `storage/`, `.env` live outside the web root; `.env` additionally denied by `.htaccess`
- ✅ Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`, `Content-Security-Policy`
- ✅ `/api/analytics` (visitor counting) is rate-limited 60/min per IP — the only unauthenticated write endpoint
- ✅ Logout requires POST + CSRF (no drive-by logout via GET links)
- ✅ `session.use_strict_mode` enabled — attacker-chosen session ids are rejected

## 8. Emails (Resend)

- Templates + triggers are identical to the old app: enquiry admin+ack,
  contact admin, newsletter double opt-in + broadcast on new listings,
  password reset.
- Every attempt is stored in `email_logs` and shown in the admin under
  **Activity → Email Log** (green "Sent" = Resend accepted; red "Failed" shows
  the exact reason). After adding your `RESEND_API_KEY`, submit a test enquiry
  and confirm the row turns green.
- If `RESEND_API_KEY` is empty, sends are skipped and logged — the site never
  breaks.

## 9. Cron suggestions (optional)

```cron
# nothing is required by the app; example: nightly DB backup
0 2 * * * mysqldump -u USERNAME_iac -p'PASS' USERNAME_iac | gzip > ~/backups/iac-$(date +\%F).sql.gz
```

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| 500 on every page | `storage/logs/php-error.log`; usually DB credentials or unwritable `storage/`. |
| Images don't upload | MultiPHP INI Editor: `upload_max_filesize=10M`, `post_max_size=15M`; `chmod 775 public/uploads`. |
| Emails not arriving | Verify domain at resend.com; check `email_logs.error`; set `RESEND_FROM` to an address on the verified domain. |
| Pretty URLs 404 | Ensure `public/.htaccess` was uploaded (hidden file — enable "show hidden files" in File Manager) and AllowOverride is on (default on cPanel). |
| Wrong times | Everything uses `Africa/Dar_es_Salaam` (config.php `APP_TIMEZONE`). |

## 11. Local development

```bash
cd php-app
# .env: DB_DRIVER=sqlite, APP_URL=http://127.0.0.1:3100
php sql/seed-dev.php            # dev schema + demo data (sqlite only)
php -S 127.0.0.1:3100 dev-server.php
```

## 12. Feature parity map (Next.js → PHP)

| Next.js | PHP |
|---|---|
| `#/` home (hero, featured carousel, latest, motto, why-us, contact strip) | `/` |
| `#/inventory` + filters + brands + sort + pagination | `/inventory` (server-rendered GET filters — better for SEO) |
| `#/vehicle/<slug>` + gallery + similar | `/vehicle/<slug>` |
| `#/enquire?vehicle=` | `/enquire?vehicle=` |
| `#/about`, `#/why-us` | `/about`, `/why-us` |
| `#/contact` two-column | `/contact` |
| `#/invoice/<id>?t=<token>` | `/invoice/<id>?t=<token>` |
| `#/admin` SPA | `/admin/*` server-rendered pages |
| `#/newsletter-confirmed?token=` | `/newsletter-confirmed?token=` |
| `/api/vehicles`, `/api/brands`, … (JSON SPA) | folded into the pages themselves (no client-side JSON needed) |
| `/api/analytics` | `POST /api/analytics` (same semantics, JS beacon) |
| `/api/invoices/shared/pdf` | `/invoice/<id>?t=<token>&pdf=1` |
| `/api/invoices/pdf`, `/api/offers/pdf` | `/admin/invoice-pdf?id=`, `/admin/offer-pdf?id=` |
| sitemap (none before) | `/sitemap.xml` (dynamic, uses APP_URL) |
| dark-mode toggle | header light/dark toggle (persisted in `localStorage`, no-FOUC boot script) |
| — (new in PHP build) | EN / Kiswahili language switcher — globe button in the header (desktop + mobile), `/lang/{en|sw}` route, session + 1-year cookie, translated public pages incl. form validation |

Differences that are intentional: server-rendered multi-page routing (SEO +
works without JS), login rate limiting now shared across workers (DB-backed),
upload endpoint actually exists (it was missing in the Next.js app).

## 13. Performance notes (audited Task 34)

Already built in — no action needed:
- Output compression: `public/.htaccess` ships HTML/CSS/JS/XML/JSON/SVG gzipped
  via mod_deflate (IfModule-guarded). Static assets are cached 7–30 days via
  mod_expires. HTML pages measure ~4–5x smaller on the wire compressed.
- SQL: hot pages use batched/single queries (home 7 queries, dashboard KPIs in
  one round trip, analytics beacon 2–3 statements per visit). All WHERE/ORDER
  columns used by the app are indexed in `sql/schema.mysql.sql`.
- Sessions: the analytics beacon and sitemap.xml skip session_start() entirely
  (they use no session data), so beacon POSTs never hold a session-file lock.
- Uploads are re-encoded to WebP (1600px max side, q82) at upload time.

Verify on the hosting account (cPanel → MultiPHP INI Editor):
- `opcache.enable = 1` (usually on by default; biggest single PHP win if off)
- `opcache.validate_timestamps = 1`, `revalidate_freq = 60`
- `memory_limit = 256M`, `max_execution_time = 120`

Housekeeping (optional, safe): `rate_limits` and `visitor_seen` clean
themselves opportunistically; `audit_logs` / `email_logs` grow forever by
design — archive or trim with a cron if they get large:
`DELETE FROM rate_limits WHERE window_start < UNIX_TIMESTAMP() - 86400;`

## 14. RouteAfrica CloudNest — package-specific quick start

Same steps as §2–§6; CloudNest-specific pointers:

1. **PHP version**: panel → PHP Selector / MultiPHP Manager → **8.4.27**.
   The app refuses to run below 8.4 with a clear message (version floor in
   `app/bootstrap.php`). Verify in cPanel Terminal:
   `php -r 'echo PHP_VERSION;'`
2. **Web root**: point the domain's document root at `.../php-app/public`
   (§2). If the panel only exposes `public_html`, use the alternative layout
   described in §2.
3. **MySQL**: create DB + user (§3), import `sql/schema.mysql.sql` via the
   panel's phpMyAdmin (16 tables, utf8mb4, InnoDB), then import existing
   data with `sql/migrate-from-sqlite.php` (§5).
4. **Secrets**: `cp .env.example .env`, fill values (§4). `.env` lives
   outside the web root and is additionally denied by `public/.htaccess`.
5. **PHP limits**: the shipped `public/.user.ini` already sets
   `upload_max_filesize=10M`, `post_max_size=15M`, `max_execution_time=120`,
   `memory_limit=256M` (LiteSpeed / PHP-FPM read it automatically). If the
   panel has a MultiPHP INI Editor, the same values there are fine too.
6. **SSL**: run AutoSSL / Let's Encrypt for the domain (§1) — `.htaccess`
   forces HTTPS + HSTS automatically.
7. **Compression & caching**: `public/.htaccess` ships mod_deflate +
   mod_expires (LiteSpeed-compatible, IfModule-guarded).
8. **First-deploy check**: run the §6 verify list. Emails remain skipped
   (and logged, never silent) until `RESEND_API_KEY` is configured (§8).
9. **Security headers / CSRF / rate limiting** are all application-side and
   work as-is — no panel configuration required (§7).
