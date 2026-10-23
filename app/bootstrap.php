<?php
/**
 * Bootstrap — includes config, registers the autoloader for lib/*.php,
 * configures error handling, sessions and the request context.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// PHP version floor — the app targets the 8.4 series (production: 8.4.27,
// selected in the hosting panel). Fail fast and clearly on anything older.
// ---------------------------------------------------------------------------
if (PHP_VERSION_ID < 80400) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This application requires PHP 8.4 or newer (found ' . PHP_VERSION . '). Select PHP 8.4 in your hosting control panel.');
}

// ---------------------------------------------------------------------------
// Libraries (flat requires — small app, no composer needed). db.php provides
// the IacDb singleton, pdf.php the IacPdf writer; everything else is plain
// function libraries loaded in dependency order.
// ---------------------------------------------------------------------------
foreach (['helpers.php', 'i18n.php', 'db.php', 'ratelimit.php', 'audit.php', 'auth.php', 'mailer.php', 'settings.php', 'vehicles.php', 'images.php', 'numbering.php', 'pdf.php', 'invoice-pdf.php', 'validate.php'] as $lib) {
    $file = __DIR__ . '/lib/' . $lib;
    if (is_file($file)) {
        require_once $file;
    }
}

// ---------------------------------------------------------------------------
// Error handling: never leak paths/secrets to visitors in production
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>';
        echo '<div style="font-family:Arial,sans-serif;max-width:560px;margin:80px auto;text-align:center">';
        echo '<h1 style="color:#d0202e">Something went wrong</h1><p>Please try again in a moment.</p>';
        echo '<p><a href="/" style="color:#d0202e">Back to home</a></p></div>';
    }
    exit;
});

// ---------------------------------------------------------------------------
// Session — secure cookie flags (Secure only when HTTPS, HttpOnly, SameSite=Lax)
// PERF: the analytics beacon (fires on every page view) and sitemap.xml use no
// session features; skipping session_start() there avoids the session-file
// read + exclusive lock on the hottest unauthenticated paths, so beacon POSTs
// never serialize against page loads. HTML pages keep their session (CSRF,
// flash messages, staff auth all need it).
// ---------------------------------------------------------------------------
$https = http_is_https();
ini_set('session.use_strict_mode', '1'); // reject attacker-supplied session ids
$sessionless = in_array(current_path(), ['/api/analytics', '/sitemap.xml'], true);
if (!$sessionless) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TTL,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,     // set automatically behind Let's Encrypt / HTTPS redirect
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Security headers on every response (also reinforced by .htaccess)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// HSTS is ignored by browsers over plain HTTP, so it is safe to always send —
// it arms as soon as the site is served over HTTPS in production.
header('Strict-Transport-Security: max-age=31536000');
// Baseline CSP. Inline scripts/styles are required by the no-FOUC theme boot
// snippet and the small admin page scripts ('unsafe-inline'); everything else
// is same-origin. Remote images are allowed because staff may link external
// photos. object-src 'none' + base-uri + form-action block common injection
// gadgets even where inline scripts are permitted.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: http: https:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
