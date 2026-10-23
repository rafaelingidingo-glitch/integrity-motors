<?php
/**
 * Admin — POST /admin/invoice-share (create the public share token)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$invoiceId = trim((string) ($_POST['invoice_id'] ?? ''));
if ($invoiceId === '') {
    flash('error', 'Invoice is required.');
    redirect('/admin/invoices');
}

$invoice = db_one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
if ($invoice === null) {
    flash('error', 'Invoice not found.');
    redirect('/admin/invoices');
}

if ($invoice['share_token'] === null || $invoice['share_token'] === '') {
    // 48-hex token (24 random bytes), collision-safe thanks to the UNIQUE index
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = random_hex(24);
        $taken = db_scalar('SELECT COUNT(*) FROM invoices WHERE share_token = ?', [$token]);
        if ((int) $taken === 0) {
            db_exec('UPDATE invoices SET share_token = ? WHERE id = ?', [$token, $invoiceId]);
            break;
        }
        if ($attempt === 4) {
            flash('error', 'Could not generate a unique share link. Please try again.');
            redirect('/admin/invoices?id=' . rawurlencode($invoiceId));
        }
    }

    audit_log(
        $staff['id'],
        $staff['name'],
        'INVOICE_SHARED',
        'Invoice',
        $invoiceId,
        'Share link opened for ' . $invoice['number'] . ' (' . $invoice['customer_name'] . ')'
    );
}

flash('ok', 'Share link ready.');
redirect('/admin/invoices?id=' . rawurlencode($invoiceId));
