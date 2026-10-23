<?php
/**
 * POST /api/analytics — visitor counting (parity with /api/analytics).
 * Body: application/x-www-form-urlencoded  visitorId=<uuid 8..128 chars>
 * A visit = every POST; a unique = first (hash, date) row for the EAT day.
 * The raw visitor id is never stored — only a 16-hex SHA-256 hash + date.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

if (request_method() !== 'POST') {
    json_bad('Method not allowed', 405);
}

// SECURITY: this is an unauthenticated write endpoint. Without a limit an
// attacker could flood visit_daily/visitor_seen with junk rows (DB bloat)
// and inflate the visit counters. 60 hits/min per IP is far above real
// browsing traffic (1 POST per page view) but stops scripted flooding.
if (!rate_limit('analytics', 60, 60_000)) {
    json_response(['ok' => true]); // silently drop — JS treats it as fire-and-forget
}

$visitorId = trim((string) ($_POST['visitorId'] ?? $_SERVER['HTTP_X_VISITOR_ID'] ?? ''));
if (strlen($visitorId) < 8 || strlen($visitorId) > 128) {
    json_bad('Invalid visitor id', 422);
}

$date        = today_eat();
$visitorHash = substr(hash('sha256', $visitorId . $date), 0, 16);

try {
    IacDb::transaction(function () use ($visitorHash, $date) {
        // Upsert with arithmetic — visits +1 in a single statement (no
        // pre-SELECT round trip). visit_daily.date is UNIQUE on both drivers.
        if (DB_DRIVER === 'sqlite') {
            db_exec(
                'INSERT INTO visit_daily (id, date, visits, uniques) VALUES (?, ?, 1, 0)
                 ON CONFLICT(date) DO UPDATE SET visits = visits + 1',
                [new_id(), $date]
            );
        } else {
            db_exec(
                'INSERT INTO visit_daily (id, date, visits, uniques) VALUES (?, ?, 1, 0)
                 ON DUPLICATE KEY UPDATE visits = visits + 1',
                [new_id(), $date]
            );
        }

        // First sighting today only (row insert tells us — no SELECT needed).
        $isNew = DB_DRIVER === 'sqlite'
            ? db_exec(
                'INSERT INTO visitor_seen (id, visitor_hash, date) VALUES (?, ?, ?)
                 ON CONFLICT(visitor_hash, date) DO NOTHING',
                [new_id(), $visitorHash, $date]
            ) === 1
            : db_exec(
                'INSERT IGNORE INTO visitor_seen (id, visitor_hash, date) VALUES (?, ?, ?)',
                [new_id(), $visitorHash, $date]
            ) === 1;

        if ($isNew) {
            db_exec('UPDATE visit_daily SET uniques = uniques + 1 WHERE date = ?', [$date]);
        }
    });
} catch (Throwable $e) {
    error_log('[analytics] ' . $e->getMessage()); // never break the page
}

json_response(['ok' => true]);
