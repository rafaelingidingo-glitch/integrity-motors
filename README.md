# Integrity Autocars — PHP 8.4 + MySQL

"OUR PROMISE DELIVERED" — plain-PHP rewrite of the dealership website
(public site + staff/admin area + enquiry→invoice workflow). Targets
**https://integrityautocars.co.tz** on cPanel shared hosting (routeafrica.net)
with PHP 8.4 and MySQL.

> **Start here:** [`DEPLOY.md`](DEPLOY.md) — full step-by-step deployment,
> data migration (`sql/migrate-from-sqlite.php`) and security checklist.

## Stack

- **PHP 8.4**, plain (no framework, no Composer)
- **MySQL** (PDO, prepared statements) — SQLite driver included for local dev
- **Tailwind CSS v4** — precompiled to `public/assets/css/app.css`
  (rebuild: `bunx @tailwindcss/cli -i app/assets-src/app.css -o public/assets/css/app.css --minify`)
- **Resend** API for transactional email (key via `.env`, never in code)
- Zero-dependency PDF writer (`app/lib/pdf.php`) for invoices & offers
- Vanilla JS only (`public/assets/js/app.js` — progressive enhancements)

## Layout

```
public/          ← WEB ROOT (deploy doc root here)
  index.php      front controller / pretty URLs
  .htaccess      HTTPS redirect, routing, security headers
  assets/        css / js / img (logo, login background)
  uploads/       vehicle photos (WebP, auto-resized)
app/
  config.php     DB/APP_URL/secrets (placeholders; .env overrides)
  bootstrap.php  autoload, sessions (Secure/HttpOnly/SameSite), error log
  lib/           db, auth, helpers, mailer, ratelimit, audit, pdf,
                 invoice-pdf, vehicles, images, numbering, validate, settings
  pages/         public pages (home, inventory, vehicle, enquire, contact, …)
  admin/         staff area (login, dashboard, vehicles, enquiries,
                 messages, invoices, offers, staff, settings, audit)
  views/layout/  site header/footer + admin shell
sql/
  schema.mysql.sql    production schema (InnoDB / utf8mb4)
  schema.sqlite.sql   dev schema
  migrate-from-sqlite.php   Next.js SQLite → MySQL one-shot migration
  seed-dev.php        dev-only demo data
storage/         logs (writable)
```

## Security

HTTPS forced · secure cookies · CSRF on all POSTs · PDO prepared statements ·
escaped output · bcrypt passwords · DB-backed rate limits · audit logging ·
MIME-checked uploads (≤5 MB, re-encoded ≤1600px WebP) · secrets outside web
root · security headers. Details in `DEPLOY.md §7`.

## Configuration

All environment-driven via `.env` (see `.env.example`):

`APP_URL` (canonical https://integrityautocars.co.tz) · `APP_ENV` · `APP_DEBUG`
· `DB_*` · `AUTH_SECRET` · `RESEND_API_KEY` · `RESEND_FROM` ·
`ADMIN_NOTIFY_EMAIL`. Nothing is hardcoded — dev and prod differ only in
`.env`.
