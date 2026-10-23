<?php
/**
 * Integrity Autocars — application configuration (plain PHP 8.4).
 *
 * HOW TO CONFIGURE ON cPanel / PRODUCTION
 * ---------------------------------------
 * 1. Create a MySQL database + user in cPanel (MySQL Databases).
 * 2. Fill the DB_* placeholders below (or better: put real values in the
 *    `.env` file next to this `app/` folder — values there override these).
 * 3. Set APP_URL to the canonical production URL (no trailing slash).
 * 4. Generate a strong AUTH_SECRET (e.g. `php -r "echo bin2hex(random_bytes(32));"`).
 * 5. Add RESEND_API_KEY + RESEND_FROM once your domain is verified at resend.com.
 *
 * Secrets never live in public/ — .env is protected by public/.htaccess and
 * app/ + storage/ live OUTSIDE the web root.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// .env loader (KEY=VALUE lines, # comments, values may be quoted). No deps.
// ---------------------------------------------------------------------------
if (!function_exists('iac_load_env')) {
    function iac_load_env(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            // Real environment variables always win over .env file values.
            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
iac_load_env(dirname(__DIR__) . '/.env');

/** env(): get an environment value with fallback. */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? null;
    }
    return $value !== null && $value !== '' ? $value : $default;
}

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
define('APP_NAME',    env('APP_NAME', 'Integrity Autocars'));
define('APP_ENV',     env('APP_ENV', 'production'));           // production | development
define('APP_DEBUG',   env('APP_DEBUG', APP_ENV === 'development' ? '1' : '0') === '1');
define('APP_URL',     rtrim(env('APP_URL', 'https://integrityautocars.co.tz') ?? '', '/'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Dar_es_Salaam'));

// ---------------------------------------------------------------------------
// Database — FILL THESE (or override in .env). cPanel hosts usually use
// localhost with a user-prefixed db name, e.g. "cpaneluser_iac".
// ---------------------------------------------------------------------------
define('DB_DRIVER', env('DB_DRIVER', 'mysql'));                 // mysql | sqlite (dev only)
define('DB_HOST',   env('DB_HOST', 'localhost'));
define('DB_PORT',   env('DB_PORT', '3306'));
define('DB_NAME',   env('DB_NAME', 'yourcpaneluser_iac'));      // PLACEHOLDER
define('DB_USER',   env('DB_USER', 'yourcpaneluser_iac'));      // PLACEHOLDER
define('DB_PASS',   env('DB_PASS', 'CHANGE_ME_DB_PASSWORD'));   // PLACEHOLDER
define('DB_CHARSET', 'utf8mb4');
define('SQLITE_PATH', dirname(__DIR__) . '/storage/iac-dev.sqlite'); // dev only

// ---------------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------------
define('AUTH_SECRET', env('AUTH_SECRET', 'dev_only_secret_change_me'));
define('SESSION_NAME', 'iac_session');
define('SESSION_TTL', 43200); // 12 hours, mirrors the previous SPA behaviour

// ---------------------------------------------------------------------------
// Email (Resend) — keep the API key out of public files; set via .env
// ---------------------------------------------------------------------------
define('RESEND_API_KEY', env('RESEND_API_KEY', ''));
define('RESEND_FROM', env('RESEND_FROM', 'Integrity Autocars <noreply@yourdomain.tz>')); // PLACEHOLDER — use a verified domain address
define('ADMIN_NOTIFY_EMAIL', env('ADMIN_NOTIFY_EMAIL', '')); // falls back to settings.displayEmail

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------
define('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads');
define('UPLOAD_URL_PREFIX', '/uploads');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);            // 5 MB per file (matches upload_max_filesize=10M)
define('UPLOAD_MAX_DIM', 1600);                          // longest side px, no enlargement
define('UPLOAD_MAX_PER_VEHICLE', 12);
define('UPLOAD_ACCEPTED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ---------------------------------------------------------------------------
// Paths / helpers
// ---------------------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));                    // php-app/
define('STORAGE_PATH', APP_ROOT . '/storage');
date_default_timezone_set(APP_TIMEZONE);

/** Absolute URL for a given app path, always anchored at APP_URL. */
function app_url(string $path = '/'): string
{
    return APP_URL . '/' . ltrim($path, '/');
}
