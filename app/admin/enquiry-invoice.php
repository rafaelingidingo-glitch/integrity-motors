<?php
/**
 * Admin — POST /admin/enquiry-invoice (generate an invoice from an enquiry)
 * Marks the enquiry INVOICE_GENERATED and snapshots the invoice notes.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$enquiryId = trim((string) ($_POST['enquiry_id'] ?? ''));
if ($enquiryId === '') {
    flash('error', 'Enquiry is required.');
    redirect('/admin/enquiries');
}

$enquiry = db_one('SELECT * FROM enquiries WHERE id = ?', [$enquiryId]);
if ($enquiry === null) {
    flash('error', 'Enquiry not found.');
    redirect('/admin/enquiries');
}

[, $discountErr] = v_int('discount', $_POST, 0, PHP_INT_MAX);
[, $chargesErr]  = v_int('additionalCharges', $_POST, 0, PHP_INT_MAX);
if ($discountErr !== null || $chargesErr !== null) {
    flash('error', 'Discount and additional charges must be zero or a positive amount.');
    redirect('/admin/enquiries?id=' . rawurlencode($enquiryId));
}
$discount = (int) ($_POST['discount'] ?? 0);
$charges  = (int) ($_POST['additionalCharges'] ?? 0);

$snap = snapshot_decode($enquiry['vehicle_snapshot']);
$priceAtEnquiry = (int) ($snap['priceAtEnquiry'] ?? 0);
$total = max($priceAtEnquiry - $discount, 0) + $charges;
$customerName = (string) $enquiry['full_name'];

$invoiceId = new_id();
$number = IacDb::transaction(function () use ($enquiry, $enquiryId, $invoiceId, $customerName, $snap, $priceAtEnquiry, $discount, $charges, $total, $staff): string {
    $number = next_document_number('INV');

    db_exec(
        'INSERT INTO invoices (id, number, enquiry_id, customer_name, customer_email, customer_phone, customer_city, customer_address,
            vehicle_snapshot, price_at_issue, discount, additional_charges, total, notes, payment_status, share_token, created_by_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $invoiceId,
            $number,
            $enquiryId,
            $customerName,
            (string) $enquiry['email'],
            (string) $enquiry['phone'],
            (string) $enquiry['city'],
            $enquiry['address'],
            (string) $enquiry['vehicle_snapshot'],
            $priceAtEnquiry,
            $discount,
            $charges,
            $total,
            (string) settings()['invoice_notes'],
            'UNPAID',
            null,
            $staff['id'],
            now_eat(),
        ]
    );

    db_exec(
        "UPDATE enquiries SET status = 'INVOICE_GENERATED', updated_at = ? WHERE id = ?",
        [now_eat(), $enquiryId]
    );

    return $number;
});

audit_log(
    $staff['id'],
    $staff['name'],
    'INVOICE_CREATED',
    'Invoice',
    $invoiceId,
    "Generated invoice {$number} for {$customerName} — total {$total} TSh"
);

flash('ok', "Invoice {$number} created.");
redirect('/admin/enquiries?id=' . rawurlencode($enquiryId));
