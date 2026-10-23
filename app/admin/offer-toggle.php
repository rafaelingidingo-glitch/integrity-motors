<?php
/**
 * Admin — POST /admin/offer-toggle (activate / deactivate an offer)
 * Mirror rule: an ACTIVE offer within its validity window keeps the vehicle's
 * offer_price; anything else clears it (offer_start/end/terms are untouched).
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$offerId = trim((string) ($_POST['offer_id'] ?? ''));
if ($offerId === '') {
    flash('error', 'Offer is required.');
    redirect('/admin/offers');
}

$offer = db_one('SELECT * FROM offers WHERE id = ?', [$offerId]);
if ($offer === null) {
    flash('error', 'Offer not found.');
    redirect('/admin/offers');
}

[$target, $err] = v_enum('target', $_POST, ['ACTIVE', 'INACTIVE']);
if ($err !== null || $target === null || $target === $offer['status']) {
    flash($err !== null || $target === null ? 'error' : 'ok', $err !== null || $target === null ? 'Invalid status.' : 'Offer already ' . strtolower((string) $target) . '.');
    back('/admin/offers');
}

IacDb::transaction(function () use ($offer, $offerId, $target): void {
    db_exec('UPDATE offers SET status = ? WHERE id = ?', [$target, $offerId]);

    $withinWindow = $target === 'ACTIVE'
        && ($offer['valid_to'] === null || $offer['valid_to'] === '' || $offer['valid_to'] >= now_eat());

    db_exec(
        'UPDATE vehicles SET offer_price = ? WHERE id = ?',
        [$withinWindow ? $offer['offer_price'] : null, $offer['vehicle_id']]
    );
});

audit_log(
    $staff['id'],
    $staff['name'],
    'OFFER_UPDATED',
    'Offer',
    $offerId,
    'status ' . $offer['status'] . ' → ' . $target
);

flash('ok', 'Offer ' . strtolower($target === 'ACTIVE' ? 'activated' : 'deactivated') . '.');
back('/admin/offers');
