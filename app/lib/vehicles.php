<?php
/**
 * Vehicle queries — exact port of /api/vehicles semantics.
 * Public lists ALWAYS filter published=1 AND status='AVAILABLE' (SOLD vehicles
 * are never listed, and direct links to sold/unpublished return 404).
 */

declare(strict_types=1);

const VEHICLE_SORTS = ['newest', 'price_asc', 'price_desc', 'year_desc'];

/**
 * Build the WHERE clause + params for vehicle list filters.
 * $staffView: staff can see everything; public always gets AVAILABLE+published.
 */
function vehicle_filters(array $q, bool $staffView): array
{
    $where  = [];
    $params = [];

    if (!$staffView) {
        $where[] = "v.published = 1 AND v.status = 'AVAILABLE'";
    } elseif (!empty($q['status']) && in_array($q['status'], ['AVAILABLE', 'SOLD'], true)) {
        $where[] = 'v.status = ?';
        $params[] = $q['status'];
    }

    if (!empty($q['make'])) {
        $where[] = 'v.make = ?';
        $params[] = (string) $q['make'];
    }
    if (!empty($q['condition']) && in_array($q['condition'], ['New', 'Used'], true)) {
        $where[] = 'v.`condition` = ?';
        $params[] = $q['condition'];
    }
    if (isset($q['minPrice']) && $q['minPrice'] !== '' && is_numeric($q['minPrice'])) {
        $where[] = 'v.price >= ?';
        $params[] = (int) $q['minPrice'];
    }
    if (isset($q['maxPrice']) && $q['maxPrice'] !== '' && is_numeric($q['maxPrice'])) {
        $where[] = 'v.price <= ?';
        $params[] = (int) $q['maxPrice'];
    }
    if (!empty($q['yearFrom']) && is_numeric($q['yearFrom'])) {
        $where[] = 'v.year >= ?';
        $params[] = (int) $q['yearFrom'];
    }
    if (!empty($q['yearTo']) && is_numeric($q['yearTo'])) {
        $where[] = 'v.year <= ?';
        $params[] = (int) $q['yearTo'];
    }
    if (!empty($q['featured'])) {
        $where[] = 'v.featured = 1';
    }

    if (!empty($q['search'])) {
        $search = trim((string) $q['search']);
        if ($search !== '') {
            if (preg_match('/^\d{4}$/', $search)) {
                $where[] = '(v.make LIKE ? OR v.model LIKE ? OR v.fuel_type LIKE ? OR v.`condition` LIKE ? OR v.description LIKE ? OR v.year = ?)';
                $like    = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like, $like, (int) $search);
            } else {
                $where[] = '(v.make LIKE ? OR v.model LIKE ? OR v.fuel_type LIKE ? OR v.`condition` LIKE ? OR v.description LIKE ?)';
                $like    = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }
    }

    return [implode(' AND ', $where), $params];
}

function vehicle_order_sql(string $sort): string
{
    return match ($sort) {
        'price_asc'  => 'v.price ASC, v.created_at DESC',
        'price_desc' => 'v.price DESC, v.created_at DESC',
        'year_desc'  => 'v.year DESC, v.created_at DESC',
        default      => 'v.created_at DESC',
    };
}

/** Paginated vehicle list with cover image attached (keyed by vehicle id). */
function vehicle_list(array $q, bool $staffView, int $page, int $pageSize): array
{
    [$where, $params] = vehicle_filters($q, $staffView);
    $whereSql = $where !== '' ? 'WHERE ' . $where : '';
    $offset   = max(0, ($page - 1) * $pageSize);

    $total = (int) db_scalar("SELECT COUNT(*) FROM vehicles v $whereSql", $params);

    $items = db_all(
        "SELECT v.* FROM vehicles v $whereSql ORDER BY " . vehicle_order_sql((string) ($q['sort'] ?? 'newest')) . " LIMIT $pageSize OFFSET $offset",
        $params
    );

    vehicle_attach_covers($items);

    return [
        'items'      => $items,
        'total'      => $total,
        'page'       => $page,
        'pageSize'   => $pageSize,
        'totalPages' => max(1, (int) ceil($total / max(1, $pageSize))),
    ];
}

/** Attach 'cover' (url) to each vehicle row, cover-first ordering respected. */
function vehicle_attach_covers(array &$items): void
{
    if ($items === []) {
        return;
    }
    $ids = array_column($items, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT vehicle_id, url FROM vehicle_images WHERE vehicle_id IN ($marks) ORDER BY is_cover DESC, sort_order DESC, id ASC",
        $ids
    );
    $map = [];
    foreach ($rows as $r) {
        if (!isset($map[$r['vehicle_id']])) {
            $map[$r['vehicle_id']] = $r['url'];
        }
    }
    foreach ($items as &$item) {
        $item['cover'] = $map[$item['id']] ?? null;
    }
    unset($item);
}

/**
 * Vehicle list WITHOUT the COUNT(*) query — for contexts that never show a
 * page total (e.g. the home page's featured/latest strips). Semantically the
 * same rows vehicle_list() would return on page 1; saves one query per call.
 */
function vehicle_items(array $q, bool $staffView, int $limit): array
{
    [$where, $params] = vehicle_filters($q, $staffView);
    $whereSql = $where !== '' ? 'WHERE ' . $where : '';
    $limit    = max(1, $limit);

    $items = db_all(
        "SELECT v.* FROM vehicles v $whereSql ORDER BY " . vehicle_order_sql((string) ($q['sort'] ?? 'newest')) . " LIMIT $limit",
        $params
    );

    vehicle_attach_covers($items);
    return $items;
}

/** All images for one vehicle, cover first. */
function vehicle_images(string $vehicleId): array
{
    return db_all(
        'SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_cover DESC, sort_order DESC, id ASC',
        [$vehicleId]
    );
}

/**
 * Single vehicle by slug or id. Public: SOLD/unpublished → null (renders 404).
 */
function vehicle_find(?string $slug, ?string $id, bool $staffView): ?array
{
    $vehicle = null;
    if ($slug !== null && $slug !== '') {
        $vehicle = db_one('SELECT * FROM vehicles WHERE slug = ?', [$slug]);
    } elseif ($id !== null && $id !== '') {
        $vehicle = db_one('SELECT * FROM vehicles WHERE id = ?', [$id]);
    }
    if ($vehicle === null) {
        return null;
    }
    if (!$staffView && ((int) $vehicle['published'] !== 1 || $vehicle['status'] !== 'AVAILABLE')) {
        return null;
    }
    $vehicle['images'] = vehicle_images($vehicle['id']);
    $vehicle['cover']  = $vehicle['images'][0]['url'] ?? null;
    return $vehicle;
}

/** Similar vehicles: same make first (featured desc, created desc) then newest, 4 total. */
function vehicle_similar(array $vehicle, int $take = 4): array
{
    $sameMake = db_all(
        "SELECT * FROM vehicles WHERE published = 1 AND status = 'AVAILABLE' AND make = ? AND id != ?
         ORDER BY featured DESC, created_at DESC LIMIT $take",
        [$vehicle['make'], $vehicle['id']]
    );
    $needed = $take - count($sameMake);
    $filler = [];
    if ($needed > 0) {
        $filler = db_all(
            "SELECT * FROM vehicles WHERE published = 1 AND status = 'AVAILABLE' AND id != ?
             ORDER BY created_at DESC LIMIT $needed",
            [$vehicle['id']]
        );
    }
    $items = array_merge($sameMake, $filler);
    vehicle_attach_covers($items);
    return $items;
}

/** Brand chips with counts (published + AVAILABLE only). */
function vehicle_brands(): array
{
    return db_all(
        "SELECT make, COUNT(*) AS count FROM vehicles WHERE published = 1 AND status = 'AVAILABLE'
         GROUP BY make ORDER BY make ASC"
    );
}

/** Build the JSON snapshot stored on enquiries/invoices (parity with buildVehicleSnapshot). */
function vehicle_snapshot(?array $vehicle, int $priceAtEnquiry): string
{
    if ($vehicle === null) {
        return json_encode([
            'vehicleId' => null,
            'name'      => 'General enquiry',
        ], JSON_UNESCAPED_UNICODE);
    }
    return json_encode([
        'vehicleId'      => $vehicle['id'],
        'name'           => vehicle_name($vehicle),
        'make'           => $vehicle['make'],
        'model'          => $vehicle['model'],
        'year'           => (int) $vehicle['year'],
        'priceAtEnquiry' => $priceAtEnquiry,
        'mileageKm'      => (int) $vehicle['mileage_km'],
        'engineCc'       => $vehicle['engine_cc'] !== null ? (int) $vehicle['engine_cc'] : null,
        'fuelType'       => $vehicle['fuel_type'],
        'condition'      => $vehicle['condition'],
        'chassisNumber'  => $vehicle['chassis_number'],
        'transmission'   => $vehicle['transmission'],
        'slug'           => $vehicle['slug'],
    ], JSON_UNESCAPED_UNICODE);
}

/** Decode a snapshot column into an array (with name fallback). */
function snapshot_decode(?string $json): array
{
    $data = json_decode((string) $json, true);
    return is_array($data) ? $data : ['name' => 'General enquiry'];
}
