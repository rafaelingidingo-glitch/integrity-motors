<?php
/**
 * i18n — lightweight English/Kiswahili localisation for the PUBLIC site.
 *
 * - Languages live in app/lang/{en,sw}.php as flat "key => text" arrays.
 * - t($key, [':name' => ...]) translates with English fallback.
 * - The choice is stored in the session (server-authoritative) and mirrored
 *   into a 1-year HttpOnly cookie (iac_lang) so it survives session expiry.
 * - The switcher itself is a dependency-free <details> dropdown with the
 *   globe icon (see lang_switcher()); app.js only ADDS outside-click/Escape
 *   closing — the menu works with JavaScript disabled.
 * - Scope: the public website only. The admin area intentionally stays in
 *   English (internal staff tool) and keeps no switcher. WhatsApp preset
 *   texts follow the visitor's chosen language (see settings.php wa_preset).
 */

declare(strict_types=1);

const IAC_LANGS       = ['en' => 'English', 'sw' => 'Kiswahili'];
const IAC_LANG_COOKIE = 'iac_lang';

/**
 * Current language: session choice → cookie → 'en'.
 * The returned value is always one of the IAC_LANGS keys (whitelist), so it
 * is safe to interpolate into file paths and URLs.
 */
function current_lang(): string
{
    $lang = (string) ($_SESSION['_lang'] ?? ($_COOKIE[IAC_LANG_COOKIE] ?? 'en'));
    return isset(IAC_LANGS[$lang]) ? $lang : 'en';
}

/** Translate $key in the current language (English fallback, then the key). */
function t(string $key, array $replace = []): string
{
    static $dicts = [];
    $lang = current_lang();
    if (!isset($dicts[$lang])) {
        $file = APP_ROOT . '/app/lang/' . $lang . '.php'; // $lang is whitelisted above
        $dicts[$lang] = is_file($file) ? (array) require $file : [];
    }
    $text = $dicts[$lang][$key] ?? null;
    if ($text === null && $lang !== 'en') {
        if (!isset($dicts['en'])) {
            $dicts['en'] = (array) require APP_ROOT . '/app/lang/en.php';
        }
        $text = $dicts['en'][$key] ?? null;
    }
    if ($text === null) {
        return $key; // visible marker for missing keys (en.php is the source of truth)
    }
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . (string) $name, (string) $value, $text);
    }
    return $text;
}

/**
 * Translate an enum-ish DB value for display (falls back to the raw value
 * for anything outside the whitelist — data stays untouched in the DB).
 */
function t_cond(?string $condition): string
{
    $c = (string) $condition;
    return in_array($c, ['New', 'Used'], true) ? t('cond_' . $c) : $c;
}

function t_pay(?string $status): string
{
    $s = (string) $status;
    return in_array($s, ['UNPAID', 'PARTIAL', 'PAID'], true) ? t('pay_' . $s) : $s;
}

/**
 * Translate a user-facing validation message produced by lib/validate.php
 * (after the page has swapped the field name for its localised label).
 * Recognises the fixed message templates; unknown text passes through.
 */
function t_err(string $message): string
{
    if ($message === t('err_channel')) {
        return $message; // already localised (contact.php builds it via t())
    }
    $map = [
        '/^(.*) is required$/u'                      => 'err_required',
        '/^(.*) must be at least (\d+) characters$/u' => 'err_min_chars',
        '/^(.*) must be at most (\d+) characters$/u'  => 'err_max_chars',
        '/^(.*) must be a valid email address$/u'    => 'err_email',
        '/^(.*) must be a number$/u'                 => 'err_number',
        '/^(.*) must be between (\d+) and (\d+)$/u'  => 'err_between',
    ];
    foreach ($map as $pattern => $key) {
        if (preg_match($pattern, $message, $m) === 1) {
            $ctx = ['field' => $m[1]];
            if (isset($m[2])) {
                $ctx['min'] = $m[2];
                $ctx['max'] = $m[3] ?? '';
            }
            return t($key, $ctx);
        }
    }
    return $message;
}

/** URL that switches the language and returns the visitor to the same page. */
function lang_switch_url(string $code): string
{
    if (!isset(IAC_LANGS[$code]) || $code === current_lang()) {
        return url(current_path());
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $next = current_path() . ($qs !== '' ? '?' . $qs : '');
    return url('/lang/' . $code . '?next=' . rawurlencode($next));
}

/**
 * Globe-icon language dropdown (pure HTML <details> — no JS required).
 * Used in the public header (desktop nav + mobile bar).
 */
function lang_switcher(): string
{
    $current = current_lang();
    $items = '';
    foreach (IAC_LANGS as $code => $label) {
        $active = $code === $current;
        $items .= '<li><a href="' . e(lang_switch_url($code)) . '" hreflang="' . e($code) . '" lang="' . e($code) . '"'
            . ($active ? ' aria-current="page"' : '')
            . ' class="flex min-h-10 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm '
            . ($active ? 'bg-zinc-100 font-semibold text-brand' : 'text-zinc-700 hover:bg-zinc-100') . '">'
            . '<span>' . e($label) . '</span>'
            . ($active ? icon('check', 'h-4 w-4 shrink-0') : '')
            . '</a></li>';
    }
    $btn = 'inline-flex h-11 w-11 shrink-0 cursor-pointer list-none items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900';
    return '<details class="lang-menu relative">'
        . '<summary class="' . $btn . '" aria-label="' . e(t('lang_aria')) . '" title="' . e(t('lang_title')) . '">'
        . icon('globe', 'h-5 w-5')
        . '</summary>'
        . '<div class="absolute right-0 top-full z-50 mt-2 w-44 rounded-xl border border-zinc-200 bg-white p-1.5 shadow-lg">'
        . '<p class="px-2.5 pb-1 pt-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-400">' . e(t('lang_title')) . '</p>'
        . '<ul class="space-y-0.5">' . $items . '</ul>'
        . '</div>'
        . '</details>';
}
