<?php
/**
 * /admin/vehicle-delete — POST handler: delete a vehicle (images cascade via
 * FK) and log it. ADMIN only.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_admin();

$id      = trim((string) ($_POST['id'] ?? ''));
$vehicle = $id !== '' ? db_one('SELECT id, make, model, year FROM vehicles WHERE id = ?', [$id]) : null;
if ($vehicle === null) {
    flash('error', 'Vehicle not found.');
    redirect('/admin/vehicles');
}

$name     = vehicle_name($vehicle);
$imgCount = (int) db_scalar('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ?', [$id]);
// Uploaded files on disk are removed after the row cascade (outside the tx).
$imageUrls = db_all('SELECT url FROM vehicle_images WHERE vehicle_id = ?', [$id]);

try {
    IacDb::transaction(function () use ($id, $name, $imgCount, $staff): void {
        db_exec('DELETE FROM vehicles WHERE id = ?', [$id]); // vehicle_images cascade via FK
        audit_log(
            $staff['id'], $staff['name'], 'VEHICLE_DELETE', 'Vehicle', $id,
            "Deleted {$name} ({$imgCount} images removed)"
        );
    });
    foreach ($imageUrls as $img) {
        delete_orphan_image_file((string) $img['url']);
    }
    flash('ok', "Vehicle deleted.");
} catch (Throwable $e) {
    // e.g. offers reference this vehicle (fk_off_vehicle ON DELETE RESTRICT)
    error_log('[vehicle-delete] ' . $e->getMessage());
    flash('error', "Could not delete {$name} — it is still referenced by other records (e.g. offers).");
}

redirect('/admin/vehicles');
