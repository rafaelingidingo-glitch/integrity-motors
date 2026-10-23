<?php
/**
 * Admin — GET /admin/offer-pdf (stream the offer PDF as a download)
 * offer_pdf() sends headers + file and exits.
 */

declare(strict_types=1);

$staff = require_staff();

$offerId = trim((string) ($_GET['id'] ?? ''));
$offer   = $offerId !== '' ? db_one('SELECT * FROM offers WHERE id = ?', [$offerId]) : null;

if ($offer === null) {
    flash('error', 'Offer not found.');
    redirect('/admin/offers');
}

offer_pdf($offer, true);
