<?php
/**
 * /admin/vehicle-image — POST handler for the vehicle photo manager:
 * upload (multipart files[]), add link, remove, set cover. ADMIN only.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_admin();

$vehicleId = trim((string) ($_POST['vehicle_id'] ?? ''));
$vehicle   = $vehicleId !== '' ? db_one('SELECT id, make, model, year FROM vehicles WHERE id = ?', [$vehicleId]) : null;
if ($vehicle === null) {
    flash('error', 'Vehicle not found.');
    redirect('/admin/vehicles');
}
$editUrl   = '/admin/vehicle-edit?id=' . urlencode($vehicleId);
$vehName   = vehicle_name($vehicle);

/** Current image stats for the vehicle. */
function vehicle_image_stats(string $vehicleId): array
{
    return [
        'count' => (int) db_scalar('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ?', [$vehicleId]),
        'max'   => (int) (db_scalar('SELECT COALESCE(MAX(sort_order), -1) FROM vehicle_images WHERE vehicle_id = ?', [$vehicleId]) ?? -1),
    ];
}

$action = (string) ($_POST['action'] ?? '');

// ---------------------------------------------------------------------------
// upload — multipart files[]
// ---------------------------------------------------------------------------
if ($action === 'upload') {
    $raw = $_FILES['files'] ?? null;
    if (!is_array($raw) || !isset($raw['name']) || !is_array($raw['name']) || count($raw['name']) === 0) {
        flash('error', 'No files were selected.');
        redirect($editUrl);
    }

    $stats = vehicle_image_stats($vehicleId);
    $room  = max(0, UPLOAD_MAX_PER_VEHICLE - $stats['count']);
    $added    = 0;
    $skipped  = 0;
    $failures = [];

    $total = count($raw['name']);
    for ($i = 0; $i < $total; $i++) {
        $file = [
            'name'     => (string) $raw['name'][$i],
            'type'     => (string) $raw['type'][$i],
            'tmp_name' => (string) $raw['tmp_name'][$i],
            'error'    => (int) $raw['error'][$i],
            'size'     => (int) $raw['size'][$i],
        ];
        if ($file['name'] === '' && $file['error'] === UPLOAD_ERR_NO_FILE) {
            continue; // empty <input> slot
        }
        if ($added >= $room) {
            $skipped++;
            continue;
        }

        $res = save_uploaded_image($file);
        if (!$res['ok'] || $res['url'] === null) {
            $failures[] = ($file['name'] !== '' ? $file['name'] : 'file') . ': ' . (string) $res['error'];
            continue;
        }

        $stats['max']++;
        db_exec(
            'INSERT INTO vehicle_images (id, vehicle_id, url, is_cover, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [new_id(), $vehicleId, $res['url'], $stats['count'] === 0 ? 1 : 0, $stats['max'], now_eat()]
        );
        $stats['count']++;
        $added++;
    }

    if ($failures !== []) {
        flash('error', implode(' · ', $failures));
    }
    if ($skipped > 0) {
        flash('ok', "Image limit reached (" . UPLOAD_MAX_PER_VEHICLE . "). Skipped {$skipped} file(s).");
    }
    if ($added > 0) {
        if ($failures === [] && $skipped === 0) {
            flash('ok', "Added {$added} photo(s) to {$vehName}.");
        }
        audit_log($staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $vehicleId, "Added {$added} photo(s) to {$vehName}");
    }
    redirect($editUrl);
}

// ---------------------------------------------------------------------------
// link — remote image URL
// ---------------------------------------------------------------------------
if ($action === 'link') {
    $res = handle_image_link((string) ($_POST['url'] ?? ''));
    if (!$res['ok'] || $res['url'] === null) {
        flash('error', (string) $res['error']);
        redirect($editUrl);
    }

    if (db_one('SELECT id FROM vehicle_images WHERE vehicle_id = ? AND url = ?', [$vehicleId, $res['url']]) !== null) {
        flash('error', 'That image is already on this vehicle');
        redirect($editUrl);
    }

    $stats = vehicle_image_stats($vehicleId);
    if ($stats['count'] >= UPLOAD_MAX_PER_VEHICLE) {
        flash('error', 'Image limit reached (' . UPLOAD_MAX_PER_VEHICLE . ').');
        redirect($editUrl);
    }

    db_exec(
        'INSERT INTO vehicle_images (id, vehicle_id, url, is_cover, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [new_id(), $vehicleId, $res['url'], $stats['count'] === 0 ? 1 : 0, $stats['max'] + 1, now_eat()]
    );
    audit_log($staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $vehicleId, "Added 1 linked photo to {$vehName}");
    flash('ok', "Added 1 photo to {$vehName}.");
    redirect($editUrl);
}

// ---------------------------------------------------------------------------
// remove — delete one photo, promote the newest if it was the cover
// ---------------------------------------------------------------------------
if ($action === 'remove') {
    $imageId = trim((string) ($_POST['image_id'] ?? ''));
    $image   = $imageId !== ''
        ? db_one('SELECT id, url, is_cover FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [$imageId, $vehicleId])
        : null;
    if ($image === null) {
        flash('error', 'Image not found on this vehicle.');
        redirect($editUrl);
    }

    db_exec('DELETE FROM vehicle_images WHERE id = ?', [$image['id']]);

    // Free disk space when the underlying uploaded file is no longer referenced.
    delete_orphan_image_file((string) $image['url']);

    if ((int) $image['is_cover'] === 1) {
        $next = db_one(
            'SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY sort_order DESC, id ASC LIMIT 1',
            [$vehicleId]
        );
        if ($next !== null) {
            db_exec('UPDATE vehicle_images SET is_cover = 1 WHERE id = ?', [$next['id']]);
        }
    }

    audit_log($staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $vehicleId, "Removed 1 photo from {$vehName}");
    flash('ok', "Photo removed from {$vehName}.");
    redirect($editUrl);
}

// ---------------------------------------------------------------------------
// cover — promote one photo, demote the rest
// ---------------------------------------------------------------------------
if ($action === 'cover') {
    $imageId = trim((string) ($_POST['image_id'] ?? ''));
    $image   = $imageId !== ''
        ? db_one('SELECT id FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [$imageId, $vehicleId])
        : null;
    if ($image === null) {
        flash('error', 'Image not found on this vehicle.');
        redirect($editUrl);
    }

    IacDb::transaction(function () use ($vehicleId, $imageId): void {
        db_exec('UPDATE vehicle_images SET is_cover = 0 WHERE vehicle_id = ?', [$vehicleId]);
        db_exec('UPDATE vehicle_images SET is_cover = 1 WHERE id = ?', [$imageId]);
    });

    audit_log($staff['id'], $staff['name'], 'VEHICLE_UPDATE', 'Vehicle', $vehicleId, "Set cover photo on {$vehName}");
    flash('ok', 'Cover photo updated.');
    redirect($editUrl);
}

flash('error', 'Unknown action.');
redirect($editUrl);
