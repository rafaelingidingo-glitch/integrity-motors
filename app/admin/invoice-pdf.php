<?php
/**
 * Admin — GET /admin/invoice-pdf (stream the invoice PDF as a download)
 * invoice_pdf() sends headers + file and exits.
 */

declare(strict_types=1);

$staff = require_staff();

$invoiceId = trim((string) ($_GET['id'] ?? ''));
$invoice   = $invoiceId !== '' ? db_one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) : null;

if ($invoice === null) {
    flash('error', 'Invoice not found.');
    redirect('/admin/invoices');
}

invoice_pdf($invoice, true);
