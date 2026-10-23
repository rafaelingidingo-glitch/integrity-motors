<?php
/**
 * Dev seed — applies sql/schema.sqlite.sql and inserts reference data so the
 * site can run locally with DB_DRIVER=sqlite (no MySQL needed).
 *
 * Run:  php sql/seed-dev.php
 * (Auto-applied on first request too — see storage/iac-dev.sqlite.)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (DB_DRIVER !== 'sqlite') {
    exit("seed-dev.php is for DB_DRIVER=sqlite only. For production import sql/schema.mysql.sql + sql/migrate-from-sqlite.php\n");
}

$pdo = IacDb::pdo();
$pdo->exec((string) file_get_contents(APP_ROOT . '/sql/schema.sqlite.sql'));
echo "schema applied\n";

$exists = db_scalar('SELECT COUNT(*) FROM staff_users');
if ((int) $exists > 0) {
    echo "already seeded — done\n";
    exit;
}

// ------------------------------------------------------------------ settings
db_exec(
    'INSERT INTO site_settings (id, business_name, motto, about_text, phone_primary, phone_secondary, whatsapp_number,
        display_email, instagram_url, tiktok_url, maps_url, address, invoice_notes, legal_name, show_sold_publicly, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        'settings',
        'Integrity Autocars',
        'OUR PROMISE DELIVERED',
        "Integrity Autocars is a Dar es Salaam based motor dealership offering quality used vehicles sourced from Europe and Asia. Every vehicle is listed honestly — real photos, clear specs and fair pricing. Visit our yard in Kijitonyama or browse the full inventory online.",
        '+255 756 428 835',
        '+255 787 679 922',
        '+255756428835',
        'integrityauto@gmail.com',
        'https://www.instagram.com/integrityauto.co/',
        'https://www.tiktok.com/@integrityautocars',
        'https://maps.app.goo.gl/7t5BbBUmiYQmF8iA9',
        'Kijitonyama, Dar es Salaam, Tanzania',
        "CRDB Bank\nTower Branch\n015088134900\n\nNMB Bank\nBank House\n20110118012\n\nPayment should be made to Integrity Roadworthy Company Limited. Please share the bank slip after deposit.",
        'Integrity Roadworthy Company Limited',
        1,
        now_eat(),
    ]
);
echo "settings seeded\n";

// --------------------------------------------------------------------- staff
$adminId = new_id();
$staffId = new_id();
db_exec(
    'INSERT INTO staff_users (id, email, name, password_hash, role, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
    [$adminId, 'admin@integrityautocars.co.tz', 'Rafael Ingidingo', password_hash('Admin@2025', PASSWORD_BCRYPT), 'ADMIN', 1, now_eat(), now_eat()]
);
db_exec(
    'INSERT INTO staff_users (id, email, name, password_hash, role, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
    [$staffId, 'staff@integrityautocars.co.tz', 'Front Desk', password_hash('Front@2025', PASSWORD_BCRYPT), 'STAFF', 1, now_eat(), now_eat()]
);
echo "staff seeded (admin@integrityautocars.co.tz / Admin@2025 — dev only)\n";

// ------------------------------------------------------------------ vehicles
$vehicles = [
    ['Toyota', 'Vanguard', 2013, 28500000, 87000, 'Petrol', 'Automatic', 'AWD', 'Pearl White', '7-seater in excellent condition. New tyres, full service history, first come first served.', "Reverse camera\nLeather seats\nSunroof\nAlloy wheels", 1, 1, 'toyota-vanguard'],
    ['BMW', 'X3', 2015, 33500000, 64000, 'Petrol', 'Automatic', 'AWD', 'Black Sapphire', 'Well maintained X3 with panoramic roof and full BMW service records.', "Panoramic roof\nNavigation\nLeather seats\nParking sensors", 1, 1, 'bmw-x3'],
    ['Subaru', 'Outback', 2014, 21500000, 98000, 'Petrol', 'CVT', 'AWD', 'Silver', 'Reliable AWD wagon — perfect for upcountry roads. Fresh import.', "Symmetrical AWD\nRoof rails\nCruise control\nBluetooth audio", 1, 0, 'subaru-outback'],
    ['Suzuki', 'Swift', 2018, 12450000, 41000, 'Petrol', 'Automatic', 'FWD', 'Red', 'Fuel efficient city car, one owner, accident free.', "Keyless start\nFog lights\nTouch screen audio", 1, 1, 'suzuki-swift'],
    ['Volkswagen', 'Golf', 2016, 17900000, 72000, 'Petrol', 'Automatic', 'FWD', 'Dark Blue', 'GT-line trim with DSG gearbox. Excellent condition throughout.', "DSG gearbox\nSport seats\nLED headlights", 1, 0, 'vw-golf'],
    ['Mitsubishi', 'Pajero', 2012, 26500000, 118000, 'Diesel', 'Automatic', '4WD', 'White', 'Legendary 4WD, ready for safari and business. Interim service just done.', "4WD lock\nDiff lock\nTow bar\n7 seats", 1, 0, 'mitsubishi-pajero'],
];
$now = now_eat();
foreach ($vehicles as $i => [$make, $model, $year, $price, $km, $fuel, $trans, $drive, $color, $desc, $features, $featured, $published, $seed]) {
    $vid  = new_id();
    $slug = slugify("$make-$model-$year") . '-' . bin2hex(random_bytes(3));
    db_exec(
        'INSERT INTO vehicles (id, make, model, year, slug, price, mileage_km, engine_cc, fuel_type, transmission, drive_type,
            exterior_color, `condition`, description, features, status, published, featured, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$vid, $make, $model, $year, $slug, $price, $km, 2000 - ($i % 5) * 100, $fuel, $trans, $drive, $color, 'Used', $desc, $features, 'AVAILABLE', $published, $featured, $now, $now]
    );
    db_exec(
        'INSERT INTO vehicle_images (id, vehicle_id, url, is_cover, sort_order, created_at) VALUES (?,?,?,?,?,?)',
        [new_id(), $vid, "/uploads/seed/{$seed}-1.jpg", 1, 0, $now]
    );
    db_exec(
        'INSERT INTO vehicle_images (id, vehicle_id, url, is_cover, sort_order, created_at) VALUES (?,?,?,?,?,?)',
        [new_id(), $vid, "/uploads/seed/{$seed}-2.jpg", 0, 1, $now]
    );
}
echo count($vehicles) . " vehicles seeded\n";

// ------------------------------------------------------------------ enquiries
db_exec(
    'INSERT INTO enquiries (id, full_name, email, phone, city, address, message, vehicle_id, vehicle_snapshot, status, assigned_to_id, created_at, updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [
        new_id(), 'Juma Mwakyusa', 'juma.mwakyusa@example.com', '+255 713 555 234', 'Dar es Salaam', 'Mikocheni',
        'Is the Vanguard still available? Can I come see it on Saturday?',
        null, vehicle_snapshot(null, 0), 'NEW', null, $now, $now,
    ]
);
echo "sample enquiry seeded\n";

// ------------------------------------------------------------------- invoices
$invNotes = (string) settings()['invoice_notes'];
$invId = new_id();
db_exec(
    'INSERT INTO invoices (id, number, enquiry_id, customer_name, customer_email, customer_phone, customer_city, customer_address,
        vehicle_snapshot, price_at_issue, discount, additional_charges, total, notes, payment_status, share_token, created_by_id, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [
        $invId, 'INV-' . date('Y') . '-0001', null, 'Juma Mwakyusa', 'juma.mwakyusa@example.com', '+255 713 555 234', 'Dar es Salaam', 'Mikocheni, Plot 44',
        json_encode(['vehicleId' => null, 'name' => 'Toyota Vanguard 2013 SUV'], JSON_UNESCAPED_UNICODE),
        28500000, 500000, 150000, 28150000, $invNotes, 'UNPAID', null, $adminId, $now,
    ]
);
echo "sample invoice seeded\n";

echo "done\n";
