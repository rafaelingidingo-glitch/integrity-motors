<?php
/**
 * Development server router for `php -S 127.0.0.1:3100 dev-server.php`.
 * Serves real files directly (assets, uploads); forwards everything else
 * to public/index.php. Not used in production (Apache handles this — see
 * the mod_deflate block in public/.htaccess).
 *
 * NOTE: streams static files itself because some static PHP builds fail
 * with "Bad file descriptor" when returning false for existing files.
 * PERF: negotiates gzip for text responses (dev-only mirror of what Apache
 * does in production) — HTML/CSS/JS shrink ~4-5x on the wire.
 */

declare(strict_types=1);

/** True when the client sent Accept-Encoding: gzip and zlib is available. */
function iac_client_accepts_gzip(): bool
{
    return function_exists('gzencode')
        && str_contains(strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath(__DIR__ . '/public' . $path);

// serve only real files that live inside public/
if ($path !== '/' && $file !== false && is_file($file) && str_starts_with($file, __DIR__ . '/public')) {
    $mime = [
        'css' => 'text/css; charset=utf-8',
        'js'  => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain; charset=utf-8',
        'woff2' => 'font/woff2',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=3600');

    // Compress text-ish assets on the fly (images are already compressed).
    $compressible = in_array($ext, ['css', 'js', 'svg', 'txt'], true) && iac_client_accepts_gzip();
    if ($compressible) {
        $data = gzencode((string) file_get_contents($file), 6);
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        header('Content-Length: ' . (string) strlen($data));
        echo $data;
    } else {
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
    }
    exit;
}

// dynamic pages: gzip the whole HTML response (dev mirror of mod_deflate)
if (iac_client_accepts_gzip()) {
    ini_set('zlib.output_compression', '0'); // avoid double compression
    ob_start('ob_gzhandler');
}

require __DIR__ . '/public/index.php';
