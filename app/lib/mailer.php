<?php
/**
 * Resend transactional email — key never leaves the backend.
 * When RESEND_API_KEY is empty (dev), the attempt is logged to email_logs
 * with success = 0 and the app continues gracefully (same as the Next.js app).
 */

declare(strict_types=1);

function send_email(string $type, string $to, string $subject, string $html, ?string $from = null): bool
{
    $apiKey   = RESEND_API_KEY;
    $fromAddr = $from ?: RESEND_FROM;
    $success  = false;
    $error    = null;

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid recipient email address';
    } elseif ($apiKey === '') {
        $error = 'RESEND_API_KEY not configured (dev mode — email skipped, stored in email_logs)';
        error_log("[email:{$type}] skipped -> {$to} :: {$subject}");
    } else {
        $payload = json_encode([
            'from'    => $fromAddr,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            $success = true;
        } else {
            $error = "Resend {$status}: " . mb_substr(is_string($body) ? $body : $err, 0, 300);
        }
    }

    try {
        db_exec(
            'INSERT INTO email_logs (id, type, recipient, subject, success, error, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [new_id(), $type, $to, $subject, $success ? 1 : 0, $error, now_eat()]
        );
    } catch (Throwable $e) {
        error_log('[email] log write failed: ' . $e->getMessage());
    }

    return $success;
}

// ---------------------------------------------------------------------------
// Branded wrapper — exact port of wrap()/row() in src/lib/email.ts
// ---------------------------------------------------------------------------
function email_wrap(string $title, string $bodyHtml): string
{
    return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;border:1px solid #eee;border-radius:8px;overflow:hidden">
  <div style="background:#0a0a0a;padding:16px 24px">
    <span style="color:#e11d2e;font-weight:bold;font-size:20px;letter-spacing:0.5px">INTEGRITY AUTOCARS</span>
    <span style="color:#999;font-size:11px;display:block">"OUR PROMISE DELIVERED"</span>
  </div>
  <div style="padding:24px">
    <h2 style="margin:0 0 12px;font-size:18px;color:#111">{$title}</h2>
    {$bodyHtml}
  </div>
  <div style="background:#fafafa;padding:14px 24px;font-size:12px;color:#777">
    Integrity Autocars &middot; +255 756 428 835 / +255 787 679 922 &middot; Kijitonyama, Dar es Salaam
  </div>
</div>
HTML;
}

function email_row(string $label, ?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $labelEsc = htmlspecialchars($label, ENT_QUOTES);
    $valueEsc = htmlspecialchars($value, ENT_QUOTES);
    return "<tr><td style=\"padding:6px 12px 6px 0;color:#666;white-space:nowrap;vertical-align:top\">{$labelEsc}</td>"
         . "<td style=\"padding:6px 0;font-weight:600;color:#111\">{$valueEsc}</td></tr>";
}

// ---------------------------------------------------------------------------
// Templates — exact ports from src/lib/email.ts
// ---------------------------------------------------------------------------
function email_enquiry_admin(array $d): string
{
    return email_wrap(
        'New vehicle enquiry',
        '<p>A customer just submitted an enquiry via the website.</p><table style="width:100%">'
        . email_row('Customer', $d['fullName'])
        . email_row('Phone', $d['phone'])
        . email_row('Email', $d['email'])
        . email_row('City', $d['city'])
        . email_row('Vehicle', $d['vehicleName'])
        . email_row('Recorded price', $d['vehiclePrice'])
        . email_row('Message', $d['message'] ?? null)
        . '</table><p style="color:#666;font-size:13px">Open the admin dashboard to follow up and assign this enquiry.</p>'
    );
}

function email_enquiry_ack(string $fullName, string $vehicleName): string
{
    $name = htmlspecialchars($fullName, ENT_QUOTES);
    $veh  = htmlspecialchars($vehicleName, ENT_QUOTES);
    return email_wrap(
        "Thank you, {$name}!",
        "<p>We received your enquiry about the <strong>{$veh}</strong>.</p>"
        . '<p>Our team will contact you shortly. For a faster response, reach us on WhatsApp at <strong>+255 756 428 835</strong>.</p>'
        . '<p style="color:#777;font-size:13px">Integrity Autocars &mdash; "OUR PROMISE DELIVERED"</p>'
    );
}

function email_contact_admin(array $d): string
{
    return email_wrap(
        'New contact form message',
        '<table style="width:100%">'
        . email_row('Name', $d['name'])
        . email_row('Phone', $d['phone'] ?? null)
        . email_row('Email', $d['email'] ?? null)
        . email_row('Subject', $d['subject'])
        . email_row('Message', $d['message'])
        . '</table>'
    );
}

function email_newsletter_confirm(string $confirmUrl): string
{
    $url = htmlspecialchars($confirmUrl, ENT_QUOTES);
    return email_wrap(
        'Confirm your subscription',
        '<p>Please confirm that you want to receive notifications when new vehicles arrive at Integrity Autocars.</p>'
        . "<p><a href=\"{$url}\" style=\"display:inline-block;background:#e11d2e;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold\">Confirm subscription</a></p>"
        . '<p style="color:#777;font-size:12px">If you didn\'t request this, you can ignore this email.</p>'
    );
}

function email_password_reset(string $name, string $resetUrl): string
{
    $n = htmlspecialchars($name, ENT_QUOTES);
    $u = htmlspecialchars($resetUrl, ENT_QUOTES);
    return email_wrap(
        'Password reset request',
        "<p>Hi {$n},</p>"
        . '<p>We received a request to reset the password for your Integrity Autocars staff account.</p>'
        . "<p><a href=\"{$u}\" style=\"display:inline-block;background:#e11d2e;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold\">Reset password</a></p>"
        . "<p style=\"color:#777;font-size:13px\">Or paste this link into your browser:<br /><span style=\"word-break:break-all\">{$u}</span></p>"
        . '<p style="color:#777;font-size:12px">This link expires in 1 hour and can be used only once. If you didn\'t request a reset, you can safely ignore this email &mdash; your password stays unchanged.</p>'
    );
}

function email_new_vehicle_broadcast(array $d): string
{
    $name  = htmlspecialchars($d['vehicleName'], ENT_QUOTES);
    $year  = (int) $d['year'];
    $price = htmlspecialchars($d['price'], ENT_QUOTES);
    $url   = htmlspecialchars($d['url'], ENT_QUOTES);
    $img   = isset($d['imageUrl']) && $d['imageUrl'] !== ''
        ? '<img src="' . htmlspecialchars($d['imageUrl'], ENT_QUOTES) . '" alt="' . $name . '" style="max-width:100%;border-radius:6px;margin-bottom:12px" />'
        : '';
    return email_wrap(
        'New vehicle just arrived!',
        '<p>A new listing is now available at Integrity Autocars:</p>'
        . "<p style=\"font-size:17px;font-weight:bold;margin:4px 0\">{$name} ({$year})</p>"
        . "<p style=\"font-size:16px;color:#e11d2e;font-weight:bold;margin:0 0 12px\">{$price}</p>"
        . $img
        . "<p><a href=\"{$url}\" style=\"display:inline-block;background:#0a0a0a;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold\">View details</a></p>"
    );
}
