<?php
/**
 * Admin — POST /admin/invoice-save
 * action=payment : inline payment status change (invoices list)
 * action=create  : manual invoice (invoice-edit create mode)
 * action=update  : adjust discount / additional charges (invoice-edit edit mode)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$action = (string) ($_POST['action'] ?? '');

// ----------------------------------------------------------------- payment
if ($action === 'payment') {
    $invoiceId = trim((string) ($_POST['invoice_id'] ?? ''));
    $invoice   = $invoiceId !== '' ? db_one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) : null;
    if ($invoice === null) {
        flash('error', 'Invoice not found.');
        redirect('/admin/invoices');
    }

    [$paymentStatus, $err] = v_enum('paymentStatus', $_POST, PAYMENT_STATUSES);
    if ($err !== null || $paymentStatus === null) {
        flash('error', 'Invalid payment status.');
        redirect('/admin/invoices');
    }

    db_exec('UPDATE invoices SET payment_status = ? WHERE id = ?', [$paymentStatus, $invoiceId]);

    audit_log(
        $staff['id'],
        $staff['name'],
        'INVOICE_UPDATED',
        'Invoice',
        $invoiceId,
        'paymentStatus ' . $invoice['payment_status'] . ' → ' . $paymentStatus
    );

    flash('ok', 'Payment status updated.');
    back('/admin/invoices');
}

// ------------------------------------------------------------------ create
if ($action === 'create') {
    [$customerName, $e1] = v_str('customerName', $_POST, 1, 200);
    [$customerPhone, $e2] = v_str('customerPhone', $_POST, 1, 40);
    [$customerEmail, $e3] = v_email('customerEmail', $_POST, false);
    [$itemName, $e4] = v_str('itemName', $_POST, 1, 300);
    [$price, $e5] = v_int('price', $_POST, 0, PHP_INT_MAX);
    [$discount, $e6] = v_int('discount', $_POST, 0, PHP_INT_MAX);
    [$charges, $e7] = v_int('additionalCharges', $_POST, 0, PHP_INT_MAX);

    $customerCity = trim((string) ($_POST['customerCity'] ?? ''));
    if ($customerCity === '') {
        $customerCity = 'Dar es Salaam';
    }
    $e8 = mb_strlen($customerCity) > 120 ? 'customerCity must be at most 120 characters' : null;
    [$customerAddress, $e9] = v_str_opt('customerAddress', $_POST, 300);

    $errors = [];
    foreach (['customerName' => $e1, 'customerPhone' => $e2, 'customerEmail' => $e3, 'itemName' => $e4,
              'price' => $e5, 'discount' => $e6, 'additionalCharges' => $e7, 'customerCity' => $e8,
              'customerAddress' => $e9] as $field => $fieldErr) {
        if ($fieldErr !== null) {
            $errors[$field] = $fieldErr;
        }
    }
    if ($errors !== [] || $customerName === null || $customerPhone === null || $itemName === null || $price === null) {
        flash('error', $errors !== [] ? validate_message($errors) : 'Please fill in all required fields.');
        redirect('/admin/invoice-edit');
    }

    $discount = (int) $discount;
    $charges  = (int) $charges;
    $total    = max($price - $discount, 0) + $charges;

    $invoiceId = new_id();
    $snapshot  = json_encode(['vehicleId' => null, 'name' => $itemName], JSON_UNESCAPED_UNICODE);
    // Number generation + INSERT must be atomic — two concurrent saves could
    // otherwise both pass the "number taken?" check and collide on the
    // UNIQUE(number) index (same pattern as enquiry-invoice.php).
    $number = IacDb::transaction(static function () use ($invoiceId, $customerName, $customerEmail, $customerPhone, $customerCity, $customerAddress, $snapshot, $price, $discount, $charges, $total, $staff): string {
        $number = next_document_number('INV');

        db_exec(
            'INSERT INTO invoices (id, number, enquiry_id, customer_name, customer_email, customer_phone, customer_city, customer_address,
                vehicle_snapshot, price_at_issue, discount, additional_charges, total, notes, payment_status, share_token, created_by_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $invoiceId,
                $number,
                null,
                $customerName,
                (string) ($customerEmail ?? ''),
                $customerPhone,
                $customerCity,
                $customerAddress,
                $snapshot,
                $price,
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

        return $number;
    });

    audit_log(
        $staff['id'],
        $staff['name'],
        'INVOICE_CREATED',
        'Invoice',
        $invoiceId,
        "Manual invoice {$number} for {$customerName} — total {$total} TSh"
    );

    flash('ok', "Invoice {$number} created.");
    redirect('/admin/invoices?id=' . rawurlencode($invoiceId));
}

// ------------------------------------------------------------------ update
if ($action === 'update') {
    $invoiceId = trim((string) ($_POST['invoice_id'] ?? ''));
    $invoice   = $invoiceId !== '' ? db_one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) : null;
    if ($invoice === null) {
        flash('error', 'Invoice not found.');
        redirect('/admin/invoices');
    }

    [$discount, $e1] = v_int('discount', $_POST, 0, PHP_INT_MAX);
    [$charges, $e2]  = v_int('additionalCharges', $_POST, 0, PHP_INT_MAX);
    if ($e1 !== null || $e2 !== null || $discount === null || $charges === null) {
        flash('error', 'Discount and additional charges must be zero or a positive amount.');
        redirect('/admin/invoice-edit?id=' . rawurlencode($invoiceId));
    }

    $total = max((int) $invoice['price_at_issue'] - $discount, 0) + $charges;

    db_exec(
        'UPDATE invoices SET discount = ?, additional_charges = ?, total = ? WHERE id = ?',
        [$discount, $charges, $total, $invoiceId]
    );

    audit_log(
        $staff['id'],
        $staff['name'],
        'INVOICE_UPDATED',
        'Invoice',
        $invoiceId,
        'discount ' . $invoice['discount'] . ' → ' . $discount . '; additionalCharges ' . $invoice['additional_charges'] . ' → ' . $charges
    );

    flash('ok', 'Invoice updated.');
    redirect('/admin/invoice-edit?id=' . rawurlencode($invoiceId));
}

flash('error', 'Unknown action.');
redirect('/admin/invoices');
