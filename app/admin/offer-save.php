<?php
/**
 * Admin — POST /admin/offer-save (create an offer + mirror onto the vehicle)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$redirectToForm = fn (): never => redirect('/admin/offers?new=1');

$vehicleId = trim((string) ($_POST['vehicleId'] ?? ''));
if ($vehicleId === '') {
    flash('error', 'Please select a vehicle.');
    $redirectToForm();
}

$vehicle = db_one('SELECT * FROM vehicles WHERE id = ?', [$vehicleId]);
if ($vehicle === null || $vehicle['status'] !== 'AVAILABLE' || (int) $vehicle['published'] !== 1) {
    flash('error', 'That vehicle is no longer available for an offer.');
    redirect('/admin/offers');
}

[$customerName, $e1] = v_str_opt('customerName', $_POST, 200);
[$customerPhone, $e2] = v_str_opt('customerPhone', $_POST, 40);
[$customerEmail, $e3] = v_email('customerEmail', $_POST, false);
[$terms, $e4] = v_str_opt('terms', $_POST, 5000);
[$offerPrice, $e5] = v_int('offerPrice', $_POST, 1, PHP_INT_MAX);

[$validFrom, $e6] = v_date_opt('validFrom', $_POST);
if ($validFrom === null && $e6 === null) {
    $validFrom = now_eat(); // form defaults to today; server-side fallback
}
[$validTo, $e7] = v_date_opt('validEnd', $_POST);

$errors = [];
foreach (['customerName' => $e1, 'customerPhone' => $e2, 'customerEmail' => $e3, 'terms' => $e4,
          'offerPrice' => $e5, 'validFrom' => $e6, 'validEnd' => $e7] as $field => $fieldErr) {
    if ($fieldErr !== null) {
        $errors[$field] = $fieldErr;
    }
}

$regularPrice = (int) $vehicle['price'];
if ($offerPrice === null || $offerPrice <= 0 || $offerPrice >= $regularPrice) {
    $errors['offerPrice'] = "offer price must be above 0 and below the regular price (" . format_tsh($regularPrice) . ")";
}

if ($errors !== [] || $offerPrice === null) {
    flash('error', validate_message($errors));
    $redirectToForm();
}

$discountPercent = (int) round((($regularPrice - $offerPrice) / $regularPrice) * 100);
$savedAmount     = $regularPrice - $offerPrice;
$snapshot        = vehicle_snapshot($vehicle, $regularPrice);
$vehicleName     = (string) (snapshot_decode($snapshot)['name'] ?? vehicle_name($vehicle));

$offerId = new_id();
$number = IacDb::transaction(function () use ($offerId, $vehicleId, $snapshot, $customerName, $customerPhone, $customerEmail, $regularPrice, $offerPrice, $discountPercent, $savedAmount, $validFrom, $validTo, $terms, $staff): string {
    $number = next_document_number('OFF');

    db_exec(
        'INSERT INTO offers (id, number, vehicle_id, vehicle_snapshot, customer_name, customer_phone, customer_email,
            regular_price, offer_price, discount_percent, saved_amount, valid_from, valid_to, terms, status, created_by_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $offerId,
            $number,
            $vehicleId,
            $snapshot,
            $customerName,
            $customerPhone,
            $customerEmail,
            $regularPrice,
            $offerPrice,
            $discountPercent,
            $savedAmount,
            $validFrom,
            $validTo,
            $terms,
            'ACTIVE',
            $staff['id'],
            now_eat(),
        ]
    );

    // Mirror the offer onto the vehicle (public listing shows the discounted price)
    db_exec(
        'UPDATE vehicles SET offer_price = ?, offer_start = ?, offer_end = ?, offer_terms = ?, updated_at = ? WHERE id = ?',
        [$offerPrice, $validFrom, $validTo, $terms, now_eat(), $vehicleId]
    );

    return $number;
});

audit_log(
    $staff['id'],
    $staff['name'],
    'OFFER_CREATED',
    'Offer',
    $offerId,
    "{$number} for {$vehicleName} ({$offerPrice} TSh)"
);

flash('ok', "Offer {$number} created.");
redirect('/admin/offers');
