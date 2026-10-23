<?php
/**
 * DB-backed rate limiter — shared across PHP-FPM workers (no APCu needed on
 * shared hosting). Same semantics as src/lib/api.ts rateLimit(): fixed window
 * keyed by bucket + client IP.
 *
 * Table: rate_limits(bucket, ip, window_start, count)  PK(bucket, ip)
 */

declare(strict_types=1);

/**
 * @return bool true = allowed (counts the hit), false = limit exceeded.
 */
function rate_limit(string $bucket, int $limit, int $windowMs): bool
{
    $ip         = client_ip();
    $windowSecs = max(1, (int) ceil($windowMs / 1000));
    $now        = time();
    $windowKey  = intdiv($now, $windowSecs); // fixed windows aligned to the epoch

    try {
        IacDb::transaction(function () use ($bucket, $ip, $windowKey) {
            // opportunistically drop expired windows for this bucket
            db_exec(
                'DELETE FROM rate_limits WHERE bucket = ? AND window_start < ?',
                [$bucket, $windowKey - 2]
            );

            // One UPSERT with arithmetic — no pre-SELECT round trip needed:
            // same-window hits increment; a stale row restarts at 1.
            if (DB_DRIVER === 'sqlite') {
                db_exec(
                    'INSERT INTO rate_limits (bucket, ip, window_start, count) VALUES (?, ?, ?, 1)
                     ON CONFLICT(bucket, ip) DO UPDATE SET
                        count = CASE WHEN window_start = excluded.window_start THEN count + 1 ELSE 1 END,
                        window_start = excluded.window_start',
                    [$bucket, $ip, $windowKey]
                );
            } else {
                db_exec(
                    'INSERT INTO rate_limits (bucket, ip, window_start, count) VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE
                        count = IF(window_start = VALUES(window_start), count + 1, 1),
                        window_start = VALUES(window_start)',
                    [$bucket, $ip, $windowKey]
                );
            }

            // 1-in-100 sweep: drop expired rows from buckets that are no longer
            // requested (the per-bucket DELETE above only cleans the active one).
            if (random_int(1, 100) === 1) {
                db_exec('DELETE FROM rate_limits WHERE window_start < ?', [$windowKey - 2]);
            }
        });

        $row = db_one('SELECT count FROM rate_limits WHERE bucket = ? AND ip = ?', [$bucket, $ip]);
        return $row !== null && (int) $row['count'] <= $limit;
    } catch (Throwable $e) {
        error_log('[ratelimit] ' . $e->getMessage());
        return true; // fail open — never block customers because the limiter broke
    }
}
