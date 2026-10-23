<?php
/**
 * POST /newsletter — footer newsletter subscribe (double opt-in).
 * Anti-enumeration: the flash message is identical whether the address is
 * new, unconfirmed or already confirmed. 3/60s rate limit.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

method_must_be('POST');
csrf_verify();

if (!rate_limit('newsletter', 3, 60_000)) {
    flash('error', t('nl_flash_rate'));
    back('/');
}

[$email, $err] = v_email('email', $_POST); // required, ≤254 chars, lowercased
if ($err !== null) {
    flash('error', t('nl_flash_invalid'));
    back('/');
}

$now        = now_eat();
$subscriber = db_one('SELECT id, email, is_confirmed, confirm_token FROM subscribers WHERE email = ?', [$email]);

try {
    if ($subscriber === null) {
        // New subscriber — create + send the confirmation link.
        $token = random_hex(32); // 64 hex chars
        db_exec(
            'INSERT INTO subscribers (id, email, is_confirmed, confirm_token, created_at) VALUES (?, ?, 0, ?, ?)',
            [new_id(), $email, $token, $now]
        );
        try {
            send_email(
                'NEWSLETTER_CONFIRM',
                $email,
                'Confirm your subscription — Integrity Autocars',
                email_newsletter_confirm(app_url('/newsletter-confirmed?token=' . $token))
            );
        } catch (Throwable $e) {
            error_log('[newsletter] confirm email failed: ' . $e->getMessage());
        }
    } elseif ((int) $subscriber['is_confirmed'] !== 1) {
        // Exists but never confirmed — regenerate the token and resend.
        $token = random_hex(32);
        db_exec('UPDATE subscribers SET confirm_token = ? WHERE id = ?', [$token, $subscriber['id']]);
        try {
            send_email(
                'NEWSLETTER_CONFIRM',
                $email,
                'Confirm your subscription — Integrity Autocars',
                email_newsletter_confirm(app_url('/newsletter-confirmed?token=' . $token))
            );
        } catch (Throwable $e) {
            error_log('[newsletter] confirm email failed: ' . $e->getMessage());
        }
    }
    // Already confirmed → nothing to send.
} catch (Throwable $e) {
    error_log('[newsletter] subscribe failed: ' . $e->getMessage());
}

flash('newsletter', t('nl_flash_ok'));
back('/');
