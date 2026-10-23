<?php
/**
 * /admin/vehicles — staff vehicle list (ADMIN + STAFF).
 * STAFF role is read-only: every mutating control is hidden for them.
 */

declare(strict_types=1);

$staff        = require_staff();
$admin_title  = 'Vehicles';
$admin_active = 'vehicles';
$canEdit      = is_admin();

$q        = trim((string) ($_GET['q'] ?? ''));
$status   = in_array($_GET['status'] ?? 'ALL', ['ALL', 'AVAILABLE', 'SOLD'], true) ? (string) ($_GET['status'] ?? 'ALL') : 'ALL';
[$page, $pageSize] = page_context(20);

$result    = vehicle_list(['search' => $q, 'status' => $status, 'sort' => 'newest'], true, $page, $pageSize);
$vehicles  = $result['items'];
$total     = $result['total'];
$totalPages = $result['totalPages'];

// One grouped query for per-row image counts (keyed by vehicle id)
$imageCounts = [];
foreach (db_all('SELECT vehicle_id, COUNT(*) AS n FROM vehicle_images GROUP BY vehicle_id') as $row) {
    $imageCounts[(string) $row['vehicle_id']] = (int) $row['n'];
}

/** Build a list URL preserving the current filters. */
$vehListUrl = function (int $p) use ($q, $status): string {
    $params = array_filter(['q' => $q, 'status' => $status !== 'ALL' ? $status : null, 'page' => $p > 1 ? $p : null]);
    return url('/admin/vehicles' . ($params !== [] ? '?' . http_build_query($params) : ''));
};

require APP_ROOT . '/app/views/layout/admin-header.php';
?>

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
  <form method="get" action="<?= url('/admin/vehicles') ?>" class="flex w-full max-w-xl items-center gap-2" role="search">
    <div class="relative w-full">
      <span class="pointer-events-none absolute inset-y-0 left-0 inline-flex w-10 items-center justify-center text-zinc-400"><?= icon('search', 'h-4 w-4') ?></span>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search make, model or year…"
             aria-label="Search vehicles"
             class="h-11 w-full rounded-lg border border-zinc-300 bg-white pl-10 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
    </div>
    <select name="status" data-autosubmit aria-label="Filter by status"
            class="h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>All statuses</option>
      <option value="AVAILABLE" <?= $status === 'AVAILABLE' ? 'selected' : '' ?>>Available</option>
      <option value="SOLD" <?= $status === 'SOLD' ? 'selected' : '' ?>>Sold</option>
    </select>
    <noscript><button type="submit" class="h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700">Go</button></noscript>
  </form>

  <?php if ($canEdit): ?>
    <a href="<?= url('/admin/vehicle-edit') ?>"
       class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('plus', 'h-4 w-4') ?> Add Vehicle
    </a>
  <?php endif; ?>
</div>

<p class="mb-3 text-xs text-zinc-500"><?= number_format($total) ?> vehicle<?= $total === 1 ? '' : 's' ?> found</p>

<?php if ($vehicles === []): ?>
  <div class="rounded-xl border border-zinc-200 bg-white p-10 text-center">
    <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= icon('package', 'h-6 w-6') ?></span>
    <p class="mt-3 text-sm font-medium text-zinc-700">No vehicles match this view.</p>
    <p class="mt-1 text-sm text-zinc-400">Try a different search or status filter.</p>
  </div>
<?php else: ?>

  <!-- Table (md+) -->
  <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 bg-white md:block">
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-500">
          <th scope="col" class="px-4 py-3 font-semibold">Vehicle</th>
          <th scope="col" class="px-4 py-3 font-semibold">Price</th>
          <th scope="col" class="px-4 py-3 font-semibold">Status</th>
          <th scope="col" class="px-4 py-3 font-semibold">Published</th>
          <th scope="col" class="px-4 py-3 font-semibold">Featured</th>
          <th scope="col" class="px-4 py-3 font-semibold">Images</th>
          <?php if ($canEdit): ?><th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-100">
        <?php foreach ($vehicles as $v):
            $vid      = (string) $v['id'];
            $name     = vehicle_name($v);
            $imgCount = $imageCounts[$vid] ?? 0;
            $published = (int) $v['published'] === 1;
            $featured  = (int) $v['featured'] === 1;
        ?>
        <tr class="align-middle">
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <?php if (!empty($v['cover'])): ?>
                <img src="<?= url((string) $v['cover']) ?>" alt="" class="h-16 w-16 shrink-0 rounded-lg border border-zinc-200 object-cover" width="64" height="64" loading="lazy">
              <?php else: ?>
                <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 text-zinc-300"><?= icon('image', 'h-5 w-5') ?></span>
              <?php endif; ?>
              <div class="min-w-0">
                <p class="truncate font-medium text-zinc-800"><?= e($name) ?></p>
                <p class="truncate text-xs text-zinc-400">/vehicle/<?= e((string) $v['slug']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 whitespace-nowrap text-zinc-700"><?= e(format_tsh((int) $v['price'])) ?></td>
          <td class="px-4 py-3">
            <?php if ($v['status'] === 'SOLD'): ?>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-zinc-200 text-zinc-600">Sold</span>
            <?php else: ?>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-700">Available</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($canEdit): ?>
            <form method="post" action="<?= url('/admin/vehicle-save') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle-published">
              <input type="hidden" name="id" value="<?= e($vid) ?>">
              <input type="hidden" name="value" value="<?= $published ? '0' : '1' ?>">
              <button type="submit" role="switch" aria-checked="<?= $published ? 'true' : 'false' ?>"
                      aria-label="<?= $published ? 'Unpublish' : 'Publish' ?> <?= e($name) ?>"
                      class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors <?= $published ? 'bg-brand' : 'bg-zinc-300' ?> hover:opacity-90">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition <?= $published ? 'translate-x-6' : 'translate-x-1' ?>"></span>
              </button>
            </form>
            <?php else: ?>
              <span class="text-xs <?= $published ? 'text-green-700' : 'text-zinc-400' ?>"><?= $published ? 'Published' : 'Unpublished' ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($canEdit): ?>
            <form method="post" action="<?= url('/admin/vehicle-save') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle-featured">
              <input type="hidden" name="id" value="<?= e($vid) ?>">
              <input type="hidden" name="value" value="<?= $featured ? '0' : '1' ?>">
              <button type="submit" aria-pressed="<?= $featured ? 'true' : 'false' ?>"
                      aria-label="<?= $featured ? 'Remove from featured' : 'Mark as featured' ?> <?= e($name) ?>"
                      class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 transition hover:bg-zinc-50"
                      title="<?= $featured ? 'Featured — click to unmark' : 'Mark as featured' ?>">
                <?= icon('star', 'h-4 w-4 ' . ($featured ? 'text-amber-500' : 'text-zinc-400')) ?>
              </button>
            </form>
            <?php else: ?>
              <?= $featured ? icon('star', 'h-4 w-4 text-amber-500') : '<span class="text-xs text-zinc-400">—</span>' ?>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 whitespace-nowrap tabular-nums text-zinc-600"><?= $imgCount ?>/12</td>
          <?php if ($canEdit): ?>
          <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-2">
              <a href="<?= url('/admin/vehicle-edit?id=' . urlencode($vid)) ?>"
                 class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-zinc-200 px-3 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">
                <?= icon('pencil', 'h-3.5 w-3.5') ?> Edit
              </a>
              <form method="post" action="<?= url('/admin/vehicle-delete') ?>" data-confirm="Delete <?= e($name) ?>? This also removes its photos from the gallery.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($vid) ?>">
                <button type="submit" aria-label="Delete <?= e($name) ?>"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-zinc-500 transition hover:border-red-200 hover:bg-red-50 hover:text-brand"
                        title="Delete vehicle">
                  <?= icon('trash-2', 'h-4 w-4') ?>
                </button>
              </form>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile stacked cards -->
  <div class="space-y-3 md:hidden">
    <?php foreach ($vehicles as $v):
        $vid      = (string) $v['id'];
        $name     = vehicle_name($v);
        $imgCount = $imageCounts[$vid] ?? 0;
        $published = (int) $v['published'] === 1;
        $featured  = (int) $v['featured'] === 1;
    ?>
    <article class="rounded-xl border border-zinc-200 bg-white p-4">
      <div class="flex gap-3">
        <?php if (!empty($v['cover'])): ?>
          <img src="<?= url((string) $v['cover']) ?>" alt="" class="h-16 w-16 shrink-0 rounded-lg border border-zinc-200 object-cover" width="64" height="64" loading="lazy">
        <?php else: ?>
          <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 text-zinc-300"><?= icon('image', 'h-5 w-5') ?></span>
        <?php endif; ?>
        <div class="min-w-0 flex-1">
          <p class="truncate font-medium text-zinc-800"><?= e($name) ?></p>
          <p class="text-xs text-zinc-400">/vehicle/<?= e((string) $v['slug']) ?></p>
          <p class="mt-1 text-sm font-semibold text-zinc-700"><?= e(format_tsh((int) $v['price'])) ?></p>
          <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            <?php if ($v['status'] === 'SOLD'): ?>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-zinc-200 text-zinc-600">Sold</span>
            <?php else: ?>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-700">Available</span>
            <?php endif; ?>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $published ? 'bg-green-50 text-green-700' : 'bg-zinc-100 text-zinc-500' ?>"><?= $published ? 'Published' : 'Unpublished' ?></span>
            <span class="text-xs text-zinc-400"><?= $imgCount ?>/12 photos</span>
          </div>
        </div>
      </div>

      <?php if ($canEdit): ?>
      <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3">
        <form method="post" action="<?= url('/admin/vehicle-save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle-published">
          <input type="hidden" name="id" value="<?= e($vid) ?>">
          <input type="hidden" name="value" value="<?= $published ? '0' : '1' ?>">
          <button type="submit" role="switch" aria-checked="<?= $published ? 'true' : 'false' ?>"
                  aria-label="<?= $published ? 'Unpublish' : 'Publish' ?> <?= e($name) ?>"
                  class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors <?= $published ? 'bg-brand' : 'bg-zinc-300' ?>">
            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition <?= $published ? 'translate-x-6' : 'translate-x-1' ?>"></span>
          </button>
        </form>
        <form method="post" action="<?= url('/admin/vehicle-save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle-featured">
          <input type="hidden" name="id" value="<?= e($vid) ?>">
          <input type="hidden" name="value" value="<?= $featured ? '0' : '1' ?>">
          <button type="submit" aria-pressed="<?= $featured ? 'true' : 'false' ?>" aria-label="Toggle featured for <?= e($name) ?>"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200">
            <?= icon('star', 'h-4 w-4 ' . ($featured ? 'text-amber-500' : 'text-zinc-400')) ?>
          </button>
        </form>
        <span class="flex-1"></span>
        <a href="<?= url('/admin/vehicle-edit?id=' . urlencode($vid)) ?>"
           class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-zinc-200 px-3 text-xs font-semibold text-zinc-700">
          <?= icon('pencil', 'h-3.5 w-3.5') ?> Edit
        </a>
        <form method="post" action="<?= url('/admin/vehicle-delete') ?>" data-confirm="Delete <?= e($name) ?>? This also removes its photos from the gallery.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($vid) ?>">
          <button type="submit" aria-label="Delete <?= e($name) ?>"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-zinc-500 hover:bg-red-50 hover:text-brand">
            <?= icon('trash-2', 'h-4 w-4') ?>
          </button>
        </form>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4 flex items-center justify-between" aria-label="Vehicle pages">
    <p class="text-xs text-zinc-500">Page <?= $page ?> of <?= $totalPages ?></p>
    <div class="flex gap-2">
      <?php if ($page > 1): ?>
        <a href="<?= e($vehListUrl($page - 1)) ?>" class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">
          <?= icon('chevron-left', 'h-3.5 w-3.5') ?> Previous
        </a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e($vehListUrl($page + 1)) ?>" class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">
          Next <?= icon('chevron-right', 'h-3.5 w-3.5') ?>
        </a>
      <?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>

<?php endif; ?>

<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
