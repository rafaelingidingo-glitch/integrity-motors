<?php
/**
 * /admin/vehicle-save — POST handler: quick toggles (published / featured)
 * and full create-or-update. ADMIN only. PRG: flash + redirect everywhere.
 * Note: `condition` is a MySQL reserved word → always backtick-quoted here.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_admin();

$action = (string) ($_POST['action'] ?? '');

// ---------------------------------------------------------------------------
// Quick toggles from the vehicles list
// ---------------------------------------------------------------------------
if ($action === 'toggle-published' || $action === 'toggle-featured') {
    $id     = trim((string) ($_POST['id'] ?? ''));
    $column = $action === 'toggle-published' ? 'published' : 'featured';
    $value  = v_bool('value', $_POST) ? 1 : 0;

    $vehicle = $id !== '' ? db_one('SELECT id, make, model, year FROM vehicles WHERE id = ?', [$id]) : null;
    if ($vehicle === null) {
        flash('error', 'Vehicle not found.');
        redirect('/admin/vehicles');
    }

    db_exec("UPDATE vehicles SET {$column} = ?, updated_at = ? WHERE id = ?", [$value, now_eat(), $vehicle['id']]);
    audit_log(
        $staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $vehicle['id'],
        vehicle_name($vehicle) . " {$column} -> {$value}"
    );
    back('/admin/vehicles');
}

// ---------------------------------------------------------------------------
// Full save (create or update)
// ---------------------------------------------------------------------------
if ($action !== 'save') {
    flash('error', 'Unknown action.');
    redirect('/admin/vehicles');
}

$id     = trim((string) ($_POST['id'] ?? ''));
$isEdit = $id !== '';

$old = null;
if ($isEdit) {
    $old = db_one('SELECT * FROM vehicles WHERE id = ?', [$id]);
    if ($old === null) {
        flash('error', 'Vehicle not found.');
        redirect('/admin/vehicles');
    }
}

// ---- validate (mirrors the zod schema of the original API) -----------------
$pairs = [
    'make'            => v_str('make', $_POST, 1, 80),
    'model'           => v_str('model', $_POST, 1, 120),
    'year'            => v_int('year', $_POST, 1950, 2100),
    'price'           => v_int('price', $_POST, 0, PHP_INT_MAX),
    'mileage_km'      => v_int('mileageKm', $_POST, 0, PHP_INT_MAX),
    'engine_cc'       => v_int_opt('engineCc', $_POST, 0, PHP_INT_MAX),
    'fuel_type'       => v_enum('fuelType', $_POST, ['Petrol', 'Diesel', 'Hybrid', 'Electric']),
    'transmission'    => v_str_opt('transmission', $_POST, 40),
    'drive_type'      => v_str_opt('driveType', $_POST, 40),
    'exterior_color'  => v_str_opt('exteriorColor', $_POST, 80),
    'condition'       => v_enum('condition', $_POST, ['New', 'Used']),
    'chassis_number'  => v_str_opt('chassisNumber', $_POST, 80),
    'description'     => v_str_opt('description', $_POST, 6000),
    'features'        => v_str_opt('features', $_POST, 6000),
    'status'          => v_enum('status', $_POST, ['AVAILABLE', 'SOLD']),
    'offer_price'     => v_int_opt('offerPrice', $_POST, 0, PHP_INT_MAX),
    'offer_end'       => v_date_opt('offerEnd', $_POST),
    'offer_terms'     => v_str_opt('offerTerms', $_POST, 3000),
];
$errors = [];
$values = [];
foreach ($pairs as $field => [$v, $err]) {
    if ($err !== null) {
        $errors[$field] = $err;
    } else {
        $values[$field] = $v;
    }
}
$published = v_bool('published', $_POST) ? 1 : 0;
$featured  = v_bool('featured', $_POST) ? 1 : 0;

$formUrl = $isEdit ? '/admin/vehicle-edit?id=' . urlencode($id) : '/admin/vehicle-edit';
if ($errors !== []) {
    flash('error', validate_message($errors));
    redirect($formUrl);
}

$now = now_eat();

// ---------------------------------------------------------------------------
// CREATE
// ---------------------------------------------------------------------------
if (!$isEdit) {
    $slug = '';
    for ($i = 0; $i < 10; $i++) {
        $candidate = slugify("{$values['make']}-{$values['model']}-{$values['year']}") . '-' . bin2hex(random_bytes(3));
        if ((int) db_scalar('SELECT COUNT(*) FROM vehicles WHERE slug = ?', [$candidate]) === 0) {
            $slug = $candidate;
            break;
        }
    }
    if ($slug === '') {
        flash('error', 'Could not generate a unique vehicle link — please try again.');
        redirect('/admin/vehicle-edit');
    }

    $vid = new_id();
    db_exec(
        'INSERT INTO vehicles (id, make, model, year, slug, price, offer_price, offer_start, offer_end, offer_terms,
            mileage_km, engine_cc, fuel_type, transmission, drive_type, exterior_color, `condition`, chassis_number,
            description, features, status, published, featured, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $vid, $values['make'], $values['model'], $values['year'], $slug, $values['price'],
            $values['offer_price'], null, $values['offer_end'], $values['offer_terms'],
            $values['mileage_km'], $values['engine_cc'], $values['fuel_type'], $values['transmission'],
            $values['drive_type'], $values['exterior_color'], $values['condition'], $values['chassis_number'],
            $values['description'], $values['features'], $values['status'], $published, $featured, $now, $now,
        ]
    );

    audit_log(
        $staff['id'], $staff['name'], 'VEHICLE_CREATE', 'Vehicle', $vid,
        "{$values['make']} {$values['model']} {$values['year']} ({$values['status']}, published={$published})"
    );

    // Newsletter broadcast — confirmed subscribers only, create-with-published only
    if ($published === 1) {
        $coverUrl = db_scalar(
            'SELECT url FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_cover DESC, sort_order DESC, id ASC LIMIT 1',
            [$vid]
        );
        foreach (db_all('SELECT email FROM subscribers WHERE is_confirmed = 1') as $sub) {
            send_email(
                'NEWSLETTER_BROADCAST',
                (string) $sub['email'],
                "New arrival: {$values['make']} {$values['model']} ({$values['year']})",
                email_new_vehicle_broadcast([
                    'vehicleName' => $values['make'] . ' ' . $values['model'],
                    'year'        => $values['year'],
                    'price'       => format_tsh((int) $values['price']),
                    'url'         => app_url('/vehicle/' . $slug),
                    'imageUrl'    => is_string($coverUrl) && $coverUrl !== '' ? app_url($coverUrl) : null,
                ])
            ); // send failures are ignored (logged to email_logs inside send_email)
        }
    }

    flash('ok', 'Vehicle added.');
    redirect('/admin/vehicles');
}

// ---------------------------------------------------------------------------
// UPDATE — build "Updated: field, field" audit detail from the diff
// ---------------------------------------------------------------------------
$intFields = ['year', 'price', 'mileage_km', 'engine_cc', 'offer_price'];
$norm      = static fn (mixed $v): string => $v === null ? '' : (string) $v;

$changed = [];
foreach ($values as $field => $newValue) {
    $oldValue = in_array($field, $intFields, true)
        ? ($old[$field] !== null ? (string) (int) $old[$field] : null)
        : $old[$field];
    if ($norm($oldValue) !== $norm($newValue)) {
        $changed[] = $field;
    }
}
if ((int) $old['published'] !== $published) {
    $changed[] = 'published';
}
if ((int) $old['featured'] !== $featured) {
    $changed[] = 'featured';
}

db_exec(
    'UPDATE vehicles SET make = ?, model = ?, year = ?, price = ?, offer_price = ?, offer_end = ?, offer_terms = ?,
        mileage_km = ?, engine_cc = ?, fuel_type = ?, transmission = ?, drive_type = ?, exterior_color = ?,
        `condition` = ?, chassis_number = ?, description = ?, features = ?, status = ?, published = ?, featured = ?,
        updated_at = ?
     WHERE id = ?',
    [
        $values['make'], $values['model'], $values['year'], $values['price'], $values['offer_price'],
        $values['offer_end'], $values['offer_terms'], $values['mileage_km'], $values['engine_cc'],
        $values['fuel_type'], $values['transmission'], $values['drive_type'], $values['exterior_color'],
        $values['condition'], $values['chassis_number'], $values['description'], $values['features'],
        $values['status'], $published, $featured, $now, $id,
    ]
);

audit_log(
    $staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $id,
    'Updated: ' . ($changed !== [] ? implode(', ', $changed) : 'no changes')
);

flash('ok', 'Vehicle updated.');
redirect($formUrl);
