<?php
/**
 * Site settings — single row (id = 'settings'), cached per request.
 * Mirrors the SiteSettings model from the Next.js app.
 */

declare(strict_types=1);

function settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $row = db_one('SELECT * FROM site_settings WHERE id = ?', ['settings']);
    if ($row === null) {
        // First boot before seeding — return defaults so pages still render.
        $row = [
            'id'                => 'settings',
            'business_name'     => 'Integrity Autocars',
            'motto'             => 'OUR PROMISE DELIVERED',
            'about_text'        => '',
            'phone_primary'     => '+255 756 428 835',
            'phone_secondary'   => '+255 787 679 922',
            'whatsapp_number'   => '+255756428835',
            'display_email'     => 'integrityauto@gmail.com',
            'instagram_url'     => 'https://www.instagram.com/integrityauto.co/',
            'tiktok_url'        => null,
            'maps_url'          => 'https://maps.app.goo.gl/7t5BbBUmiYQmF8iA9',
            'address'           => 'Kijitonyama, Dar es Salaam, Tanzania',
            'invoice_notes'     => '',
            'legal_name'        => 'Integrity Roadworthy Company Limited',
            'show_sold_publicly' => 1,
            'updated_at'        => now_eat(),
        ];
    }
    return $settings = $row;
}

function admin_notify_email(): string
{
    return ADMIN_NOTIFY_EMAIL !== '' ? ADMIN_NOTIFY_EMAIL : (string) settings()['display_email'];
}

/**
 * Default WhatsApp message presets — localised through t() so the prefilled
 * text follows the visitor's chosen site language (wa_* keys in app/lang).
 */
function wa_preset(string $key, array $ctx = []): string
{
    $business = (string) settings()['business_name'];
    return match ($key) {
        'general'      => t('wa_general', ['biz' => $business]),
        'hero'         => t('wa_hero', ['biz' => $business]),
        'contact_card' => t('wa_contact_card', ['biz' => $business]),
        'vehicle'      => t('wa_vehicle', [
            'biz'   => $business,
            'name'  => (string) ($ctx['name'] ?? ''),
            'year'  => (string) ($ctx['year'] ?? ''),
            'price' => format_tsh((int) ($ctx['price'] ?? 0)),
        ]),
        'enquiry'      => t('wa_enquiry', ['biz' => $business]),
        'invoice'      => t('wa_invoice', ['biz' => $business, 'number' => (string) ($ctx['number'] ?? '')]),
        'invoice_bad'  => t('wa_invoice_bad', ['biz' => $business]),
        default        => t('wa_general', ['biz' => $business]),
    };
}
