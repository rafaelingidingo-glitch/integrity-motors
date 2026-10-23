<?php
/**
 * Shared helpers — formatting, URLs, WhatsApp links, CSRF, flash messages.
 * Ported 1:1 from the Next.js app (src/lib/format.ts, whatsapp.ts, api.ts).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Shared enum values (single source of truth for admin list filters AND the
// POST validators — previously duplicated as separate consts per file).
// ---------------------------------------------------------------------------
const ENQUIRY_STATUSES  = ['NEW', 'CONTACTED', 'OFFER_SENT', 'INVOICE_GENERATED', 'CONFIRMED', 'CLOSED', 'NOT_PROCEEDING'];
const PAYMENT_STATUSES  = ['UNPAID', 'PARTIAL', 'PAID'];
const MESSAGE_STATUSES  = ['NEW', 'IN_PROGRESS', 'RESOLVED'];
const STAFF_ROLES       = ['ADMIN', 'STAFF'];

// ---------------------------------------------------------------------------
// HTTP / request context
// ---------------------------------------------------------------------------
function http_is_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Behind the cPanel/LiteSpeed or Cloudflare proxy
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || ($_SERVER['HTTP_CF_VISITOR'] ?? '') === '{"scheme":"https"}';
}

/**
 * Best-effort client IP for rate limiting.
 *
 * SECURITY: X-Forwarded-For is client-controlled. It is only honoured when the
 * request actually arrives from a private/loopback address, i.e. behind a
 * trusted local reverse proxy (cPanel LiteSpeed, the dev proxy, Cloudflare in
 * front of Apache). The RIGHTMOST XFF entry is used because it is the value
 * appended by the nearest trusted proxy; everything to its left can be forged
 * by the client. A direct client cannot get a fresh rate-limit window by
 * sending an arbitrary XFF header.
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'local';
    $isPrivate = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isPrivate) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if ($parts[$i] !== '' && filter_var($parts[$i], FILTER_VALIDATE_IP) !== false) {
                    return $parts[$i];
                }
            }
        }
        $real = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        if (is_string($real) && $real !== '' && filter_var($real, FILTER_VALIDATE_IP) !== false) {
            return $real;
        }
    }
    return $remote;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return '/' . trim($path, '/');
}

/** Full current URL (scheme+host from APP_URL, path+query from request). */
function current_url(): string
{
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    return app_url(current_path()) . ($qs !== '' ? '?' . $qs : '');
}

/** Build an in-app URL preserving pretty routing (works on dev + prod). */
function url(string $path): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, $status);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && str_starts_with($ref, app_url('/'))) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/** Render ok/error flash boxes (call once per page, after the header). */
function render_flashes(): void
{
    $boxes = [
        [flash('ok'),    'border-green-200 bg-green-50 text-green-800', 'status'],
        [flash('error'), 'border-red-200 bg-red-50 text-red-800',       'alert'],
    ];
    foreach ($boxes as [$msg, $cls, $role]) {
        if ($msg !== null && $msg !== '') {
            echo '<div class="mb-4 rounded-lg border px-4 py-3 text-sm ' . $cls . '" role="' . $role . '">' . e($msg) . '</div>';
        }
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_bad(string $message, int $status = 400, array $extra = []): never
{
    json_response(['error' => $message] + $extra, $status);
}

// ---------------------------------------------------------------------------
// Flash messages (one-shot, stored in session)
// ---------------------------------------------------------------------------
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Money / numbers / dates  (exact parity with src/lib/format.ts)
// ---------------------------------------------------------------------------
/** "TSh43,500,000.00" — en-US grouping, 2 decimals; null → "—" */
function format_tsh(?int $amount): string
{
    if ($amount === null) {
        return '—';
    }
    return 'TSh' . number_format($amount, 2);
}

/** "TSh 43.5M" / "TSh 450K" — compact display used on cards/badges */
function format_tsh_compact(?int $amount): string
{
    if ($amount === null) {
        return '—';
    }
    $abs = abs($amount);
    $sign = $amount < 0 ? '-' : '';
    if ($abs >= 1_000_000) {
        $v = $abs / 1_000_000;
        return 'TSh ' . $sign . (round($v * 10) / 10 == floor($v) ? number_format($v, 0) : number_format($v, 1)) . 'M';
    }
    if ($abs >= 1_000) {
        $v = $abs / 1_000;
        return 'TSh ' . $sign . (round($v * 10) / 10 == floor($v) ? number_format($v, 0) : number_format($v, 1)) . 'K';
    }
    return 'TSh ' . $sign . number_format($abs);
}

/** "12,345 km" */
function format_km(?int $km): string
{
    return $km === null ? '—' : number_format($km) . ' km';
}

/** DD/MM/YYYY */
function format_date_dmy(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** "17 Apr 2026, 10:41" (en-GB style) */
function format_date_time(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d M Y, H:i', $ts) : '—';
}

/** "Listed 12 Mar 2026" */
function format_date_medium(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('j M Y', $ts) : '—';
}

/** YYYY-MM-DD in Africa/Dar_es_Salaam (app timezone is fixed to EAT). */
function today_eat(): string
{
    return date('Y-m-d');
}

function now_eat(): string
{
    return date('Y-m-d H:i:s');
}

// ---------------------------------------------------------------------------
// Strings
// ---------------------------------------------------------------------------
/** slugify — lowercase, non-alnum → "-", trim, max 60 chars (parity with api.ts) */
function slugify(string $text, int $max = 60): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return mb_substr($slug, 0, $max);
}

function random_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

/** cuid-ish 24-char id compatible with existing rows */
function new_id(): string
{
    return substr(str_replace('.', '', base_convert((string) time(), 10, 36)) . bin2hex(random_bytes(10)), 0, 24);
}

// ---------------------------------------------------------------------------
// WhatsApp (exact parity with src/lib/whatsapp.ts)
// ---------------------------------------------------------------------------
/** Strip non-digits; upgrade a leading 0 to the Tanzania country code 255. */
function normalize_wa_digits(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '255' . substr($digits, 1);
    }
    return $digits;
}

function wa_link(string $number, string $text): string
{
    return 'https://wa.me/' . normalize_wa_digits($number) . '?text=' . rawurlencode($text);
}

function tel_link(string $number): string
{
    return 'tel:' . preg_replace('/\s+/', '', $number);
}

// ---------------------------------------------------------------------------
// Vehicle helpers (offer math mirrors the API serializer)
// ---------------------------------------------------------------------------
function vehicle_name(array $v): string
{
    return $v['make'] . ' ' . $v['model'] . ' ' . $v['year'];
}

function offer_active(array $v): bool
{
    if ($v['offer_price'] === null || $v['offer_price'] === '') {
        return false;
    }
    $now = now_eat();
    if (!empty($v['offer_start']) && $v['offer_start'] > $now) {
        return false;
    }
    if (!empty($v['offer_end']) && $v['offer_end'] < $now) {
        return false;
    }
    return (int) $v['offer_price'] > 0;
}

function effective_price(array $v): int
{
    return offer_active($v) ? (int) $v['offer_price'] : (int) $v['price'];
}

function discount_percent(array $v): int
{
    $regular = (int) $v['price'];
    if ($regular <= 0 || !offer_active($v)) {
        return 0;
    }
    $offer = (int) $v['offer_price'];
    if ($offer >= $regular) {
        return 0;
    }
    return (int) round((($regular - $offer) / $regular) * 100);
}

// ---------------------------------------------------------------------------
// CSRF — required on every state-changing POST form
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
        http_response_code(419);
        exit('Page expired — please go back, refresh the page and try again.');
    }
}

/** Only allow this method to continue, else 405. */
function method_must_be(string $method): void
{
    if (request_method() !== strtoupper($method)) {
        http_response_code(405);
        exit('Method not allowed');
    }
}

// ---------------------------------------------------------------------------
// Inline SVG icons (lucide-style paths, ISC licensed) — no JS/icon deps
// ---------------------------------------------------------------------------
function icon(string $name, string $class = 'h-4 w-4'): string
{
    static $paths = [
        'menu'           => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x'              => '<path d="M18 6 6 18M6 6l12 12"/>',
        'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'map-pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'instagram'      => '<rect width="20" height="20" x="2" y="2" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
        'music-2'        => '<circle cx="8" cy="18" r="4"/><path d="M12 18V2l7 4"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'whatsapp'       => '<path d="M17.6 6.32A7.85 7.85 0 0 0 12.05 4a7.94 7.94 0 0 0-7.87 7.9c0 1.4.37 2.76 1.06 3.96L4 20l4.27-1.12a7.9 7.9 0 0 0 3.78.96h.01a7.94 7.94 0 0 0 7.9-7.87 7.85 7.85 0 0 0-2.36-5.65Z"/>',
        'shield-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'globe'          => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'sparkles'       => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>',
        'car'            => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
        'star'           => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/>',
        'check'          => '<path d="M20 6 9 17l-5-5"/>',
        'chevron-left'   => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
        'send'           => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
        'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'share-2'        => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
        'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'plus'           => '<path d="M5 12h14M12 5v14"/>',
        'pencil'         => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'trash-2'        => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
        'eye'            => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'        => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'file-text'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8M16 13H8M16 17H8"/>',
        'settings'       => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'scroll-text'    => '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'package'        => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'inbox'          => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'ticket'         => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
        'arrow-left'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'image'          => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'link'           => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'lock'           => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'moon'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'external-link'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'refresh-cw'     => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'info'           => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'filter'         => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    ];
    $p = $paths[$name] ?? null;
    if ($p === null) {
        return '';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

// ---------------------------------------------------------------------------
// Dark mode — boot script (no-FOUC) + toggle button
// ---------------------------------------------------------------------------
/**
 * Inline <head> script: applies the saved (or system-preferred) theme to
 * <html> BEFORE first paint so dark mode never flashes light. Rendered by
 * every HTML view right after the stylesheet link.
 */
function theme_boot_script(): string
{
    return '<script>(function(){try{var t=localStorage.getItem("iac_theme");'
        . 'if(t==="dark"||(!t&&window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches)){'
        . 'document.documentElement.classList.add("dark")}}catch(e){}})();</script>';
}

/**
 * Light/dark toggle button. Shows a moon in light mode and a sun in dark
 * mode (pure CSS swap — see .theme-moon/.theme-sun rules in app.css);
 * app.js persists the choice to localStorage under "iac_theme".
 */
function theme_toggle_button(string $class = ''): string
{
    $base = 'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900';
    return '<button type="button" data-theme-toggle'
        . ' class="' . e($base . ($class !== '' ? ' ' . $class : '')) . '"'
        . ' aria-label="' . e(t('theme_aria')) . '" title="' . e(t('theme_title')) . '">'
        . icon('moon', 'theme-moon h-5 w-5')
        . icon('sun', 'theme-sun h-5 w-5')
        . '</button>';
}

// ---------------------------------------------------------------------------
// Pagination helper shared by admin lists
// ---------------------------------------------------------------------------
function page_context(int $defaultPageSize = 20, int $maxPageSize = 100): array
{
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = (int) ($_GET['pageSize'] ?? $defaultPageSize);
    $pageSize = min(max(1, $pageSize), $maxPageSize);
    return [$page, $pageSize, max(0, ($page - 1) * $pageSize)];
}
