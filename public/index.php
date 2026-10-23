<?php
/**
 * Front controller — all pretty URLs route through here.
 * Production (cPanel): .htaccess rewrites everything non-static to this file.
 * Development:         `php -S 127.0.0.1:3100 dev-server.php` (dev-server.php
 *                      serves real files directly and forwards the rest here).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$path = current_path();
// 301 canonicalise trailing slashes (checked against the RAW request path —
// current_path() already trims, so this must look at REQUEST_URI directly).
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($rawPath !== '/' && str_ends_with($rawPath, '/')) {
    $target = app_url(rtrim($rawPath, '/')) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: ' . $target, true, 301);
    exit;
}

// ---------------------------------------------------------------------------
// Admin area (own router)
// ---------------------------------------------------------------------------
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require APP_ROOT . '/app/admin/router.php';
    exit;
}

// ---------------------------------------------------------------------------
// Public routes
// ---------------------------------------------------------------------------
$PAGES = APP_ROOT . '/app/pages';

match (true) {
    $path === '/'                         => require $PAGES . '/home.php',
    $path === '/inventory'                => require $PAGES . '/inventory.php',
    (bool) preg_match('#^/vehicle/([A-Za-z0-9-]+)$#', $path, $m) => (function () use ($m, $PAGES) { $GLOBALS['vehicle_slug'] = $m[1]; require $PAGES . '/vehicle.php'; })(),
    $path === '/enquire'                  => require $PAGES . '/enquire.php',
    $path === '/about'                    => require $PAGES . '/about.php',
    $path === '/why-us'                   => require $PAGES . '/why-us.php',
    $path === '/contact'                  => require $PAGES . '/contact.php',
    $path === '/newsletter'               => require $PAGES . '/newsletter.php',
    $path === '/newsletter-confirmed'     => require $PAGES . '/newsletter-confirmed.php',
    (bool) preg_match('#^/lang/([a-z]{2})$#', $path, $m) => (function () use ($m, $PAGES) { $GLOBALS['lang_code'] = $m[1]; require $PAGES . '/lang-switch.php'; })(),
    (bool) preg_match('#^/invoice/([A-Za-z0-9-]+)$#', $path, $m) => (function () use ($m, $PAGES) { $GLOBALS['invoice_id'] = $m[1]; require $PAGES . '/invoice-public.php'; })(),
    // JSON-ish endpoints consumed by JS / external systems
    $path === '/api/analytics'            => require APP_ROOT . '/app/pages/analytics-endpoint.php',
    $path === '/sitemap.xml'              => require $PAGES . '/sitemap.php',
    default                               => require $PAGES . '/not-found.php',
};
