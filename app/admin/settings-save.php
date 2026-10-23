<?php
/**
 * Admin — POST /admin/settings-save (ADMIN only)
 * Updates the single site_settings row and audits the changed field list.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_admin();

// ---------------------------------------------------------------- validate
[$businessName, $e['businessName']] = v_str('businessName', $_POST, 1, 120);
[$motto, $e['motto']]               = v_str('motto', $_POST, 1, 120);
[$legalName, $e['legalName']]       = v_str('legalName', $_POST, 1, 200);
[$address, $e['address']]           = v_str('address', $_POST, 1, 300);
[$aboutText, $e['aboutText']]       = v_str_opt('aboutText', $_POST, 6000);
[$primaryPhone, $e['primaryPhone']] = v_str('primaryPhone', $_POST, 1, 40);
[$secondaryPhone, $e['secondaryPhone']] = v_str_opt('secondaryPhone', $_POST, 40);
[$whatsappNumber, $e['whatsappNumber']] = v_str('whatsappNumber', $_POST, 1, 40);
[$displayEmail, $e['displayEmail']] = v_email('displayEmail', $_POST, true);
[$instagramUrl, $e['instagramUrl']] = v_str_opt('instagramUrl', $_POST, 300);
[$tiktokUrl, $e['tiktokUrl']]       = v_str_opt('tiktokUrl', $_POST, 300);
[$mapsUrl, $e['mapsUrl']]           = v_str_opt('mapsUrl', $_POST, 300);
[$invoiceNotes, $e['invoiceNotes']] = v_str_opt('invoiceNotes', $_POST, 6000);

// URLs must look like URLs when provided
foreach (['instagramUrl' => $instagramUrl, 'tiktokUrl' => $tiktokUrl, 'mapsUrl' => $mapsUrl] as $urlField => $urlValue) {
    if ($urlValue !== null && $urlValue !== '' && !filter_var($urlValue, FILTER_VALIDATE_URL)) {
        $e[$urlField] = "$urlField must be a valid URL";
    }
}

$errors = array_filter($e);
if ($errors !== [] || $businessName === null || $motto === null || $legalName === null || $address === null
    || $primaryPhone === null || $whatsappNumber === null || $displayEmail === null) {
    flash('error', $errors !== [] ? validate_message($errors) : 'Please fill in all required fields.');
    redirect('/admin/settings');
}

// ------------------------------------------------------------------- save
$old = settings();

$new = [
    'business_name'    => $businessName,
    'motto'            => $motto,
    'about_text'       => (string) $aboutText,
    'phone_primary'    => $primaryPhone,
    'phone_secondary'  => (string) ($secondaryPhone ?? ''),
    'whatsapp_number'  => $whatsappNumber,
    'display_email'    => $displayEmail,
    'instagram_url'    => (string) ($instagramUrl ?? ''),
    'tiktok_url'       => $tiktokUrl,
    'maps_url'         => (string) ($mapsUrl ?? ''),
    'address'          => $address,
    'invoice_notes'    => (string) ($invoiceNotes ?? ''),
    'legal_name'       => $legalName,
];

$changed = [];
foreach ($new as $column => $value) {
    if ((string) ($old[$column] ?? '') !== (string) $value) {
        $changed[] = $column;
    }
}

db_exec(
    "UPDATE site_settings SET business_name = ?, motto = ?, about_text = ?, phone_primary = ?, phone_secondary = ?,
        whatsapp_number = ?, display_email = ?, instagram_url = ?, tiktok_url = ?, maps_url = ?, address = ?,
        invoice_notes = ?, legal_name = ?, updated_at = ?
     WHERE id = 'settings'",
    [
        $new['business_name'], $new['motto'], $new['about_text'], $new['phone_primary'], $new['phone_secondary'],
        $new['whatsapp_number'], $new['display_email'], $new['instagram_url'], $new['tiktok_url'], $new['maps_url'],
        $new['address'], $new['invoice_notes'], $new['legal_name'], now_eat(),
    ]
);

audit_log(
    $staff['id'],
    $staff['name'],
    'SETTINGS_UPDATE',
    'SiteSettings',
    'settings',
    'Updated: ' . ($changed !== [] ? implode(', ', $changed) : '(no changes)')
);

flash('ok', 'Settings saved.');
redirect('/admin/settings');
