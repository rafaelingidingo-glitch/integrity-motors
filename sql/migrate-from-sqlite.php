<?php
/**
 * One-time migration: Next.js SQLite (Prisma) → PHP app database (MySQL).
 *
 * Usage on the cPanel server (SSH):
 *   php sql/migrate-from-sqlite.php /path/to/custom.db
 * Optionally force a target (defaults come from .env / config.php):
 *   DB_TARGET=mysql php sql/migrate-from-sqlite.php custom.db
 *
 * What it does
 *  - Applies sql/schema.mysql.sql automatically when the target is MySQL and
 *    the `vehicles` table does not exist yet.
 *  - Copies ALL business data (vehicles, images, enquiries, notes, messages,
 *    invoices, offers, subscribers, settings, email logs, audit logs, visits).
 *  - Converts Prisma epoch-millisecond timestamps to 'Y-m-d H:i:s' strings in
 *    Africa/Dar_es_Salaam time.
 *  - RE-SEEDS staff passwords with bcrypt (the old Next.js hashes use scrypt,
 *    which PHP cannot verify): every migrated staff member gets the password
 *    from $argv[2] (default "Admin@2025") — CHANGE THEM AFTER FIRST LOGIN or
 *    use the "Forgot password" flow. See DEPLOY.md.
 *  - Skips ephemeral data (rate_limits) and dead tokens (PasswordResetToken).
 *  - Idempotent: re-running upserts by primary key.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$sourcePath = $argv[1] ?? null;
$seedPassword = $argv[2] ?? 'Admin@2025';

if ($sourcePath === null || !is_file($sourcePath)) {
    exit("Usage: php sql/migrate-from-sqlite.php <path-to-custom.db> [new-staff-password]\n"
        . "Example: php sql/migrate-from-sqlite.php /home/user/custom.db\n");
}

$targetDriver = getenv('DB_TARGET') ?: DB_DRIVER;
if ($targetDriver === 'sqlite') {
    echo "WARNING: DB_TARGET=sqlite — data will go into the DEV database (" . SQLITE_PATH . ")\n";
}

$src = new PDO('sqlite:' . $sourcePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$dst = IacDb::pdo();

// ------------------------------------------------------- FK checks OFF (re-run)
// The script is idempotent (DELETE + INSERT by id). On a re-run, deleting rows
// that other rows still reference (e.g. offers → vehicles with ON DELETE
// RESTRICT) would abort the copy — so FK enforcement is suspended for the
// migration window and restored right after. The copied dataset itself is
// referentially consistent (it comes from the same schema with FKs).
if ($targetDriver === 'sqlite') {
    $dst->exec('PRAGMA foreign_keys = OFF');
} else {
    $dst->exec('SET FOREIGN_KEY_CHECKS = 0');
}

// ---------------------------------------------------------------- schema init
if ($targetDriver === 'mysql' && db_scalar('SHOW TABLES LIKE "vehicles"') === null) {
    echo "Applying sql/schema.mysql.sql ...\n";
    $dst->exec((string) file_get_contents(APP_ROOT . '/sql/schema.mysql.sql'));
} elseif ($targetDriver === 'sqlite' && db_scalar('SELECT COUNT(*) FROM sqlite_master WHERE name = "vehicles"') == 0) {
    $dst->exec((string) file_get_contents(APP_ROOT . '/sql/schema.sqlite.sql'));
}

/** epoch-ms (Prisma) or date string → 'Y-m-d H:i:s' in app timezone. */
function mig_dt(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return date('Y-m-d H:i:s', intdiv((int) $value, 1000));
    }
    $ts = strtotime((string) $value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/**
 * Copy a table. $map: sqliteColumn => mysqlColumn; auto-renames *Hash, *Id etc.
 * $deleteKey: column used for the idempotent pre-DELETE — usually 'id', but a
 * table with an additional UNIQUE key (visit_daily.date) must delete by that
 * key instead, otherwise a row that changed id (same unique value) breaks the
 * re-run with a UNIQUE constraint violation.
 */
function mig_table(PDO $src, PDO $dst, string $srcTable, string $dstTable, array $map, array $dates, ?callable $rowTransform = null, string $deleteKey = 'id'): int
{
    $count = 0;
    $rows = $src->query("SELECT * FROM \"$srcTable\"")->fetchAll();
    foreach ($rows as $row) {
        if ($rowTransform !== null) {
            $row = $rowTransform($row) ?: $row;
        }
        $data = [];
        foreach ($map as $sCol => $dCol) {
            $value = $row[$sCol] ?? null;
            if (in_array($sCol, $dates, true)) {
                $value = mig_dt($value);
            }
            // SQLite booleans come back as 0/1 ints; pass through (MySQL TINYINT(1)).
            $data[$dCol] = $value;
        }
        $cols = array_keys($data);
        $marks = implode(',', array_fill(0, count($cols), '?'));
        $colSql = implode(', ', array_map(fn ($c) => "`$c`", $cols));
        $dst->prepare("DELETE FROM `$dstTable` WHERE `$deleteKey` = ?")->execute([$row[$deleteKey]]);
        $dst->prepare("INSERT INTO `$dstTable` ($colSql) VALUES ($marks)")->execute(array_values($data));
        $count++;
    }
    printf("  %-18s %d rows\n", $dstTable, $count);
    return $count;
}

echo "Migrating $sourcePath -> " . ($targetDriver === 'mysql' ? DB_NAME : 'sqlite(dev)') . "\n";

IacDb::transaction(function () use ($src, $dst, $seedPassword) {
    // 1) staff — re-hash passwords with bcrypt
    mig_table($src, $dst, 'StaffUser', 'staff_users', [
        'id' => 'id', 'email' => 'email', 'name' => 'name', 'passwordHash' => 'password_hash',
        'role' => 'role', 'isActive' => 'is_active', 'createdAt' => 'created_at', 'updatedAt' => 'updated_at',
    ], ['createdAt', 'updatedAt'], function ($row) use ($seedPassword) {
        $row['passwordHash'] = password_hash($seedPassword, PASSWORD_BCRYPT);
        return $row;
    });

    // 2) settings (single row, id forced to 'settings')
    mig_table($src, $dst, 'SiteSettings', 'site_settings', [
        'id' => 'id', 'businessName' => 'business_name', 'motto' => 'motto', 'aboutText' => 'about_text',
        'phonePrimary' => 'phone_primary', 'phoneSecondary' => 'phone_secondary', 'whatsappNumber' => 'whatsapp_number',
        'displayEmail' => 'display_email', 'instagramUrl' => 'instagram_url', 'tiktokUrl' => 'tiktok_url',
        'mapsUrl' => 'maps_url', 'address' => 'address', 'invoiceNotes' => 'invoice_notes',
        'legalName' => 'legal_name', 'showSoldPublicly' => 'show_sold_publicly', 'updatedAt' => 'updated_at',
    ], ['updatedAt'], function ($row) {
        $row['id'] = 'settings';
        return $row;
    });

    // 3) vehicles + images
    mig_table($src, $dst, 'Vehicle', 'vehicles', [
        'id' => 'id', 'make' => 'make', 'model' => 'model', 'year' => 'year', 'slug' => 'slug',
        'price' => 'price', 'offerPrice' => 'offer_price', 'offerStart' => 'offer_start', 'offerEnd' => 'offer_end',
        'offerTerms' => 'offer_terms', 'mileageKm' => 'mileage_km', 'engineCc' => 'engine_cc',
        'fuelType' => 'fuel_type', 'transmission' => 'transmission', 'driveType' => 'drive_type',
        'exteriorColor' => 'exterior_color', 'condition' => 'condition', 'chassisNumber' => 'chassis_number',
        'description' => 'description', 'features' => 'features', 'status' => 'status',
        'published' => 'published', 'featured' => 'featured', 'createdAt' => 'created_at', 'updatedAt' => 'updated_at',
    ], ['offerStart', 'offerEnd', 'createdAt', 'updatedAt']);

    mig_table($src, $dst, 'VehicleImage', 'vehicle_images', [
        'id' => 'id', 'vehicleId' => 'vehicle_id', 'url' => 'url',
        'isCover' => 'is_cover', 'sortOrder' => 'sort_order', 'createdAt' => 'created_at',
    ], ['createdAt']);

    // 4) enquiries + notes
    mig_table($src, $dst, 'Enquiry', 'enquiries', [
        'id' => 'id', 'fullName' => 'full_name', 'email' => 'email', 'phone' => 'phone', 'city' => 'city',
        'address' => 'address', 'message' => 'message', 'vehicleId' => 'vehicle_id',
        'vehicleSnapshot' => 'vehicle_snapshot', 'status' => 'status', 'assignedToId' => 'assigned_to_id',
        'createdAt' => 'created_at', 'updatedAt' => 'updated_at',
    ], ['createdAt', 'updatedAt']);

    mig_table($src, $dst, 'EnquiryNote', 'enquiry_notes', [
        'id' => 'id', 'enquiryId' => 'enquiry_id', 'staffId' => 'staff_id', 'content' => 'content', 'createdAt' => 'created_at',
    ], ['createdAt']);

    // 5) contact messages
    mig_table($src, $dst, 'ContactMessage', 'contact_messages', [
        'id' => 'id', 'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'subject' => 'subject',
        'message' => 'message', 'status' => 'status', 'createdAt' => 'created_at',
    ], ['createdAt']);

    // 6) invoices + offers
    mig_table($src, $dst, 'Invoice', 'invoices', [
        'id' => 'id', 'number' => 'number', 'enquiryId' => 'enquiry_id', 'customerName' => 'customer_name',
        'customerEmail' => 'customer_email', 'customerPhone' => 'customer_phone', 'customerCity' => 'customer_city',
        'customerAddress' => 'customer_address', 'vehicleSnapshot' => 'vehicle_snapshot',
        'priceAtIssue' => 'price_at_issue', 'discount' => 'discount', 'additionalCharges' => 'additional_charges',
        'total' => 'total', 'notes' => 'notes', 'paymentStatus' => 'payment_status',
        'shareToken' => 'share_token', 'createdById' => 'created_by_id', 'createdAt' => 'created_at',
    ], ['createdAt']);

    mig_table($src, $dst, 'Offer', 'offers', [
        'id' => 'id', 'number' => 'number', 'vehicleId' => 'vehicle_id', 'vehicleSnapshot' => 'vehicle_snapshot',
        'customerName' => 'customer_name', 'customerPhone' => 'customer_phone', 'customerEmail' => 'customer_email',
        'regularPrice' => 'regular_price', 'offerPrice' => 'offer_price', 'discountPercent' => 'discount_percent',
        'savedAmount' => 'saved_amount', 'validFrom' => 'valid_from', 'validTo' => 'valid_to',
        'terms' => 'terms', 'status' => 'status', 'createdById' => 'created_by_id', 'createdAt' => 'created_at',
    ], ['validFrom', 'validTo', 'createdAt']);

    // 7) newsletter + logs + analytics
    mig_table($src, $dst, 'Subscriber', 'subscribers', [
        'id' => 'id', 'email' => 'email', 'isConfirmed' => 'is_confirmed', 'confirmToken' => 'confirm_token', 'createdAt' => 'created_at',
    ], ['createdAt']);

    mig_table($src, $dst, 'EmailLog', 'email_logs', [
        'id' => 'id', 'type' => 'type', 'recipient' => 'recipient', 'subject' => 'subject',
        'success' => 'success', 'error' => 'error', 'createdAt' => 'created_at',
    ], ['createdAt']);

    mig_table($src, $dst, 'AuditLog', 'audit_logs', [
        'id' => 'id', 'staffId' => 'staff_id', 'staffName' => 'staff_name', 'action' => 'action',
        'entity' => 'entity', 'entityId' => 'entity_id', 'detail' => 'detail', 'createdAt' => 'created_at',
    ], ['createdAt']);

    mig_table($src, $dst, 'VisitDaily', 'visit_daily', [
        'id' => 'id', 'date' => 'date', 'visits' => 'visits', 'uniques' => 'uniques',
    ], [], null, 'date');

    mig_table($src, $dst, 'VisitorSeen', 'visitor_seen', [
        'id' => 'id', 'visitorHash' => 'visitor_hash', 'date' => 'date',
    ], []);
});

// Restore FK enforcement (see the note at the top of the script).
if ($targetDriver === 'sqlite') {
    $dst->exec('PRAGMA foreign_keys = ON');
} else {
    $dst->exec('SET FOREIGN_KEY_CHECKS = 1');
}

echo "\nDone. Staff passwords were re-seeded to: $seedPassword\n";
echo "IMPORTANT: change every staff password after first login (Staff → Edit → new password)\n";
echo "or use the Forgot-password flow.\n";
