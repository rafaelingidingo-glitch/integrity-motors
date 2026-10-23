<?php
/**
 * Document numbering — INV-YYYY-#### (invoices) and OFF-YYYY-#### (offers).
 * Exact port of the Next.js numbering logic (max existing suffix + 1, 5 retries).
 */

declare(strict_types=1);

/**
 * Next sequential document number for the given prefix ('INV' or 'OFF').
 * Uses the highest existing suffix for the current year (number LIKE
 * "PREFIX-YYYY-%") rather than a counter, so imports/seed rows are respected.
 *
 * @throws RuntimeException when no unique number could be produced after 5 tries
 */
function next_document_number(string $prefix): string
{
    $prefix = strtoupper(trim($prefix));
    $table  = match ($prefix) {
        'INV'   => 'invoices',
        'OFF'   => 'offers',
        default => throw new InvalidArgumentException("Unsupported document prefix: $prefix"),
    };

    $year = date('Y');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        // Fresh max on every loop — a parallel insert may have claimed a number.
        $last = db_scalar(
            "SELECT number FROM `$table` WHERE number LIKE ? ORDER BY number DESC LIMIT 1",
            [$prefix . '-' . $year . '-%']
        );

        $seq = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        $number = sprintf('%s-%s-%04d', $prefix, $year, $seq);

        $taken = db_scalar("SELECT COUNT(*) FROM `$table` WHERE number = ?", [$number]);
        if ((int) $taken === 0) {
            return $number;
        }
    }

    throw new RuntimeException("Could not generate a unique $prefix document number after 5 attempts");
}
