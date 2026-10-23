<?php
/**
 * GET /lang/{code}?next=/path — switch the public-site language (en|sw),
 * persist it in the session + a 1-year HttpOnly cookie, then redirect back
 * to the page the visitor came from.
 *
 * GET is appropriate here: the action is idempotent and changes nothing but
 * a display preference. 'next' is restricted to same-site absolute paths
 * (open-redirect guard). Unknown codes fall through to the 404 page.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$code = strtolower(trim((string) ($GLOBALS['lang_code'] ?? '')));
if (!isset(IAC_LANGS[$code])) {
    http_response_code(404);
    require APP_ROOT . '/app/pages/not-found.php';
    exit;
}

$_SESSION['_lang'] = $code;
setcookie(IAC_LANG_COOKIE, $code, [
    'expires'  => time() + 31_536_000, // 1 year
    'path'     => '/',
    'secure'   => http_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Same-site relative redirect target only (rejects //host and /\host forms).
$next = (string) ($_GET['next'] ?? '/');
if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
    $next = '/';
}
header('Location: ' . url($next), true, 302);
exit;
