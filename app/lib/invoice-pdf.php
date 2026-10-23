<?php
/**
 * Invoice & Offer PDFs — layout port of src/lib/invoice-pdf.ts (@react-pdf).
 * A4, Helvetica; red accent #d0202e, ink #0a0a0a, grey #6b6b6b.
 */

declare(strict_types=1);

function invoice_seq(string $number): string
{
    $parts = explode('-', $number);
    return (string) end($parts);
}

/** Draw text shrink-to-fit within $maxWidth (keeps PDF columns from colliding).
 *  Uses a 0.9 safety factor: Linux PDF viewers substitute base-14 fonts with
 *  slightly wider system fonts, so widths must not be trusted at 100%. */
function pdf_text_fit(IacPdf $pdf, float $x, float $y, string $text, float $maxWidth, float $size): void
{
    $fit = $maxWidth * 0.9;
    if ($pdf->textWidth($text) > $fit) {
        while ($size > 6.5 && $pdf->textWidth($text) > $fit) {
            $size -= 0.5;
            $pdf->setFont('bold', $size);
        }
        if ($pdf->textWidth($text) > $fit) {
            while ($text !== '' && $pdf->textWidth($text . '…') > $fit) {
                $text = mb_substr($text, 0, mb_strlen($text) - 1);
            }
            $text .= '…';
        }
    }
    $pdf->text($x, $y, $text);
}

function pdf_logo_path(): ?string
{
    $path = APP_ROOT . '/public/assets/img/logo.png';
    return is_file($path) ? $path : null;
}

/** Business block used on PDFs (name / legal name / phone / email / city). */
function pdf_business(): array
{
    $s = settings();
    $city = trim(explode(',', (string) $s['address'])[0]);
    return [
        'name'      => (string) $s['business_name'],
        'legalName' => (string) $s['legal_name'],
        'phone'     => (string) $s['phone_primary'],
        'email'     => (string) $s['display_email'],
        'address'   => (string) $s['address'],
        'city'      => $city !== '' ? $city : 'Dar es Salaam',
    ];
}

// ---------------------------------------------------------------------------
// INVOICE PDF
// ---------------------------------------------------------------------------
function invoice_pdf(array $invoice, bool $attachment): never
{
    $pdf = new IacPdf();
    $biz = pdf_business();
    $snap = snapshot_decode($invoice['vehicle_snapshot']);
    $seq  = invoice_seq((string) $invoice['number']);

    $ink   = '#0a0a0a';
    $red   = '#d0202e';
    $grey  = '#6b6b6b';
    $light = '#9a9a9a';
    $mLeft = 70.0;
    $mRight = 70.0;
    $w = IacPdf::PAGE_W - $mLeft - $mRight;

    // 1) header — logo left, title block right
    $logo = pdf_logo_path();
    $topY = 46.0;
    if ($logo !== null) {
        $pdf->image($logo, $mLeft, $topY, 56, 56);
    }
    $rightX = IacPdf::PAGE_W - $mRight - 170.0;
    $pdf->setFont('bold', 22, 2.5);
    $pdf->setColorHex($ink);
    $pdf->text($rightX, $topY + 8, 'INVOICE');
    $pdf->setFont('bold', 12);
    $pdf->setColorHex($red);
    $pdf->text($rightX, $topY + 30, '#' . $seq);
    $pdf->setFont('normal', 9);
    $pdf->setColorHex($grey);
    $pdf->text($rightX, $topY + 46, 'Issued ' . format_date_dmy($invoice['created_at']));

    // 2) red accent bar
    $pdf->rect($mLeft, $topY + 72, $w, 2.5, [0.816, 0.125, 0.180]);

    // 3) FROM / BILL TO
    $colW = ($w - 24) * 0.46;
    $labelY = $topY + 108;
    $pdf->setFont('bold', 8, 1.2);
    $pdf->setColorHex($light);
    $pdf->text($mLeft, $labelY, 'FROM');
    $pdf->text($mLeft + $colW + 24, $labelY, 'BILL TO');

    $y = $labelY + 16;
    $pdf->setFont('bold', 11);
    $pdf->setColorHex($ink);
    pdf_text_fit($pdf, $mLeft, $y, $biz['legalName'], $colW, 11);
    pdf_text_fit($pdf, $mLeft + $colW + 24, $y, (string) $invoice['customer_name'], $colW, 11);

    $fromLines = [$biz['phone'], $biz['email'], $biz['city']];
    $toLines = array_filter([
        $invoice['customer_phone'],
        $invoice['customer_email'],
        $invoice['customer_city'],
        $invoice['customer_address'],
    ], fn ($v) => $v !== null && $v !== '');

    $pdf->setFont('normal', 9.5);
    $pdf->setColorHex($grey);
    $ly = $y + 18;
    foreach ($fromLines as $line) {
        $pdf->text($mLeft, $ly, (string) $line);
        $ly += 14;
    }
    $ly = $y + 18;
    foreach ($toLines as $line) {
        $pdf->text($mLeft + $colW + 24, $ly, (string) $line);
        $ly += 14;
    }

    // 4) items table
    $tableY = max($ly, $y + 70) + 26;
    $descW = $w * 0.48;
    $qtyW  = $w * 0.10;
    $priceW = $w * 0.21;
    $amountW = $w * 0.21;

    $pdf->rect($mLeft, $tableY, $w, 22, [0.949, 0.949, 0.949]);
    $pdf->setFont('bold', 9);
    $pdf->setColorHex($ink);
    $tx = $mLeft + 10;
    foreach (['Description' => $descW, 'QTY' => $qtyW, 'Price, TZS' => $priceW, 'Amount, TZS' => $amountW] as $head => $col) {
        $pdf->text($tx, $tableY + 14, $head);
        $tx += $col;
    }

    $rowY = $tableY + 22;
    $pdf->rect($mLeft, $rowY, $w, 34, [1, 1, 1]);
    $pdf->line($mLeft, $rowY + 34, $mLeft + $w, $rowY + 34, 0.4, [0.88, 0.88, 0.88]);

    $itemName = $snap['name'] ?? 'vehicle';
    if (isset($snap['make'])) {
        $description = sprintf('1 unit %s %s %s model', $snap['make'], $snap['model'], $snap['year']);
    } else {
        $description = '1 unit ' . $itemName;
    }
    $pdf->setFont('normal', 10);
    $pdf->setColorHex($ink);
    $pdf->text($mLeft + 10, $rowY + 15, $description);
    if (!empty($snap['chassisNumber'])) {
        $pdf->setFont('normal', 9);
        $pdf->setColorHex($grey);
        $pdf->text($mLeft + 10, $rowY + 28, 'Chasis ' . $snap['chassisNumber']);
    }

    $priceAtIssue = (int) $invoice['price_at_issue'];
    $x = $mLeft + $descW;
    $pdf->setFont('normal', 10);
    $pdf->setColorHex($ink);
    $pdf->text($x + 4, $rowY + 15, '1');
    $x += $qtyW;
    $pdf->setFont('normal', 8.5);
    $pdf->text($x + $priceW - 8 - $pdf->textWidth(format_tsh($priceAtIssue)), $rowY + 15, format_tsh($priceAtIssue));
    $pdf->text($x + $priceW + $amountW - 8 - $pdf->textWidth(format_tsh($priceAtIssue)), $rowY + 15, format_tsh($priceAtIssue));

    // 5/6) discount, charges, total
    $ly = $rowY + 34;
    $rightLabelX = $mLeft + $descW + $qtyW;
    $valueX = $mLeft + $w - 10;

    $discount = (int) $invoice['discount'];
    $charges  = (int) $invoice['additional_charges'];

    $pdf->setFont('normal', 10);
    if ($discount > 0) {
        $pdf->setColorHex($grey);
        $pdf->text($rightLabelX, $ly + 14, 'Discount');
        $pdf->text($valueX - $pdf->textWidth('-' . format_tsh($discount)), $ly + 14, '-' . format_tsh($discount));
        $ly += 20;
    }
    if ($charges > 0) {
        $pdf->setColorHex($grey);
        $pdf->text($rightLabelX, $ly + 14, 'Additional charges');
        $pdf->text($valueX - $pdf->textWidth(format_tsh($charges)), $ly + 14, format_tsh($charges));
        $ly += 20;
    }

    $ly += 8;
    $pdf->line($mLeft, $ly, $mLeft + $w, $ly, 0.6, [0.85, 0.85, 0.85]);
    $ly += 10;
    $pdf->setFont('bold', 11);
    $pdf->setColorHex($ink);
    $pdf->text($rightLabelX, $ly + 14, 'Total');
    $pdf->text($valueX - $pdf->textWidth(format_tsh((int) $invoice['total'])), $ly + 14, format_tsh((int) $invoice['total']));
    $ly += 30;

    // 7) notes & payment instructions
    if (trim((string) $invoice['notes']) !== '') {
        $ly += 10;
        $pdf->setFont('bold', 9, 1.0);
        $pdf->setColorHex($grey);
        $pdf->text($mLeft, $ly, 'NOTES & PAYMENTS INSTRUCTIONS');
        $ly += 18;
        $pdf->setFont('normal', 9.5);
        $pdf->setColorHex($ink);
        foreach ($pdf->wrapText((string) $invoice["notes"], $w * 0.95) as $noteLine) {
            $pdf->text($mLeft, $ly, (string) $noteLine);
            $ly += 14;
        }
    }

    // 8) footer — bottom right
    $pdf->setFont('normal', 8.5);
    $pdf->setColorHex($light);
    $footer = 'Inv. #' . $seq . '    1 of 1';
    $pdf->text(IacPdf::PAGE_W - $mRight - $pdf->textWidth($footer), IacPdf::PAGE_H - 36, $footer);

    $pdf->send($invoice['number'] . '.pdf', $attachment);
}

// ---------------------------------------------------------------------------
// OFFER PDF
// ---------------------------------------------------------------------------
function offer_pdf(array $offer, bool $attachment): never
{
    $pdf = new IacPdf();
    $biz = pdf_business();
    $snap = snapshot_decode($offer['vehicle_snapshot']);
    $seq  = invoice_seq((string) $offer['number']);

    $ink = '#0a0a0a';
    $grey = '#6b6b6b';
    $light = '#9a9a9a';
    $mLeft = 70.0;
    $w = IacPdf::PAGE_W - 140.0;

    $topY = 46.0;
    $logo = pdf_logo_path();
    if ($logo !== null) {
        $pdf->image($logo, $mLeft, $topY, 56, 56);
    }
    $rightX = IacPdf::PAGE_W - 70.0 - 170.0;
    $pdf->setFont('bold', 22, 2.5);
    $pdf->setColorHex($ink);
    $pdf->text($rightX, $topY + 8, 'OFFER');
    $pdf->setFont('bold', 12);
    $pdf->setColorHex('#d0202e');
    $pdf->text($rightX, $topY + 30, '#' . $seq);
    $pdf->setFont('normal', 9);
    $pdf->setColorHex($grey);
    $pdf->text($rightX, $topY + 46, 'Issued ' . format_date_dmy($offer['created_at']));

    $pdf->rect($mLeft, $topY + 72, $w, 2.5, [0.816, 0.125, 0.180]);

    // FROM / BILL TO
    $colW = ($w - 24) * 0.46;
    $labelY = $topY + 108;
    $pdf->setFont('bold', 8, 1.2);
    $pdf->setColorHex($light);
    $pdf->text($mLeft, $labelY, 'FROM');
    $pdf->text($mLeft + $colW + 24, $labelY, 'PREPARED FOR');

    $y = $labelY + 16;
    $pdf->setFont('bold', 11);
    $pdf->setColorHex($ink);
    pdf_text_fit($pdf, $mLeft, $y, $biz['legalName'], $colW, 11);
    $customerLabel = $offer['customer_name'] !== null && $offer['customer_name'] !== '' ? (string) $offer['customer_name'] : 'Walk-in Customer';
    pdf_text_fit($pdf, $mLeft + $colW + 24, $y, $customerLabel, $colW, 11);

    $ly = $y + 18;
    $pdf->setFont('normal', 9.5);
    $pdf->setColorHex($grey);
    foreach ([$biz['phone'], $biz['email'], $biz['city']] as $line) {
        $pdf->text($mLeft, $ly, (string) $line);
        $ly += 14;
    }
    $ly = $y + 18;
    foreach (array_filter([$offer['customer_phone'], $offer['customer_email']], fn ($v) => $v !== null && $v !== '') as $line) {
        $pdf->text($mLeft + $colW + 24, $ly, (string) $line);
        $ly += 14;
    }

    // VEHICLE DETAILS table
    $tableY = max($ly, $y + 70) + 26;
    $pdf->setFont('bold', 8, 1.0);
    $pdf->setColorHex($grey);
    $pdf->text($mLeft, $tableY, 'VEHICLE DETAILS');

    $headY = $tableY + 14;
    $pdf->rect($mLeft, $headY, $w, 22, [0.949, 0.949, 0.949]);
    $pdf->setFont('bold', 8.5);
    $pdf->setColorHex($ink);
    $cols = ['Vehicle' => 0.30, 'Year' => 0.12, 'Mileage' => 0.16, 'Engine' => 0.12, 'Fuel' => 0.14, 'Condition' => 0.16];
    $tx = $mLeft + 10;
    foreach ($cols as $head => $frac) {
        $pdf->text($tx, $headY + 14, $head);
        $tx += $w * $frac;
    }
    $rowY = $headY + 22;
    $pdf->rect($mLeft, $rowY, $w, 24, [1, 1, 1]);
    $pdf->line($mLeft, $rowY + 24, $mLeft + $w, $rowY + 24, 0.4, [0.88, 0.88, 0.88]);
    $pdf->setFont('normal', 9.5);
    $tx = $mLeft + 10;
    $values = [
        ($snap['make'] ?? '') . ' ' . ($snap['model'] ?? ''),
        (string) ($snap['year'] ?? ''),
        format_km(isset($snap['mileageKm']) ? (int) $snap['mileageKm'] : null),
        isset($snap['engineCc']) && $snap['engineCc'] ? $snap['engineCc'] . 'cc' : '—',
        (string) ($snap['fuelType'] ?? '—'),
        (string) ($snap['condition'] ?? '—'),
    ];
    $i = 0;
    foreach ($cols as $_head => $frac) {
        $pdf->text($tx, $rowY + 15, $values[$i] !== '' ? $values[$i] : '—');
        $tx += $w * $frac;
        $i++;
    }

    // PRICING table
    $pY = $rowY + 24 + 26;
    $pdf->setFont('bold', 8, 1.0);
    $pdf->setColorHex($grey);
    $pdf->text($mLeft, $pY, 'PRICING');

    $rY = $pY + 14;
    $regular = (int) $offer['regular_price'];
    $offerPrice = (int) $offer['offer_price'];
    $rows = [
        ['Regular Price', format_tsh($regular), $ink, false],
        ['Offer Price — 1 unit ' . trim((($snap['make'] ?? '') . ' ' . ($snap['model'] ?? '') . ' ' . ($snap['year'] ?? ''))) . ' model', format_tsh($offerPrice), $ink, false],
        ['You Save (' . (int) $offer['discount_percent'] . '%)', format_tsh((int) $offer['saved_amount']), '#d0202e', true],
        ['Valid From', format_date_dmy($offer['valid_from']), $grey, false],
        ['Valid Until', $offer['valid_to'] !== null && $offer['valid_to'] !== '' ? format_date_dmy($offer['valid_to']) : 'Until cancelled', $grey, false],
    ];
    foreach ($rows as [$label, $value, $color, $bold]) {
        $pdf->rect($mLeft, $rY, $w, 24, [1, 1, 1]);
        $pdf->line($mLeft, $rY + 24, $mLeft + $w, $rY + 24, 0.4, [0.88, 0.88, 0.88]);
        $pdf->setFont($bold ? 'bold' : 'normal', 10);
        $pdf->setColorHex($color === '#0a0a0a' ? $ink : $color);
        $pdf->text($mLeft + 10, $rY + 15, $label);
        $pdf->text($mLeft + $w - 10 - $pdf->textWidth($value), $rY + 15, $value);
        $rY += 24;
    }

    // TERMS & NOTES
    $tY = $rY + 26;
    $pdf->setFont('bold', 8, 1.0);
    $pdf->setColorHex($grey);
    $pdf->text($mLeft, $tY, 'TERMS & NOTES');
    $ly = $tY + 18;
    $terms = (string) ($offer['terms'] ?? '');
    if (trim($terms) === '') {
        $terms = 'This quotation is valid until the validity date shown above. All prices are in Tanzanian Shillings (TSh). For orders and questions contact us on +255 756 428 835.';
    }
    $pdf->setFont('normal', 9.5);
    $pdf->setColorHex($ink);
    foreach ($pdf->wrapText($terms, $w * 0.95) as $line) {
        $pdf->text($mLeft, $ly, (string) $line);
        $ly += 14;
    }

    $pdf->setFont('normal', 8.5);
    $pdf->setColorHex($light);
    $footer = 'Off. #' . $seq . '    1 of 1';
    $pdf->text(IacPdf::PAGE_W - 70.0 - $pdf->textWidth($footer), IacPdf::PAGE_H - 36, $footer);

    $pdf->send($offer['number'] . '.pdf', $attachment);
}
