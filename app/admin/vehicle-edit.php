<?php
/**
 * /admin/vehicle-edit — create / edit a vehicle (ADMIN only).
 * The form posts to /admin/vehicle-save (not multipart); photos are managed
 * through /admin/vehicle-image forms below (edit mode only).
 */

declare(strict_types=1);

$staff        = require_admin();
$admin_active = 'vehicles';

$id      = trim((string) ($_GET['id'] ?? ''));
$vehicle = null;
if ($id !== '') {
    $vehicle = db_one('SELECT * FROM vehicles WHERE id = ?', [$id]);
    if ($vehicle === null) {
        flash('error', 'Vehicle not found.');
        redirect('/admin/vehicles');
    }
}
$isEdit = $vehicle !== null;
$admin_title = $isEdit ? 'Edit Vehicle' : 'Add Vehicle';
$heading     = $isEdit ? vehicle_name($vehicle) . ' — Edit' : 'Add Vehicle';

/** Escaped current value (edit) or default (create). */
$val = fn(string $key, string $default = ''): string => e((string) ($vehicle[$key] ?? $default));
/** Checkbox state. */
$on = fn(string $key, bool $default = false): bool => $vehicle !== null ? ((int) $vehicle[$key] === 1) : $default;

$images   = $isEdit ? vehicle_images((string) $vehicle['id']) : [];
$imgCount = count($images);

require APP_ROOT . '/app/views/layout/admin-header.php';
?>

<!-- Sticky action row -->
<div class="sticky top-16 z-20 -mx-4 mb-6 flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-100/95 px-4 py-3 backdrop-blur lg:-mx-8 lg:px-8">
  <div class="flex min-w-0 items-center gap-3">
    <a href="<?= url('/admin/vehicles') ?>"
       class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-zinc-300 bg-white text-zinc-600 hover:bg-zinc-50"
       aria-label="Back to vehicles">
      <?= icon('arrow-left', 'h-4 w-4') ?>
    </a>
    <h2 class="truncate text-sm font-semibold text-zinc-800 sm:text-base"><?= e($heading) ?></h2>
  </div>
  <button type="submit" form="vehicle-form"
          class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
    <?= icon('check', 'h-4 w-4') ?> Save
  </button>
</div>

<form id="vehicle-form" method="post" action="<?= url('/admin/vehicle-save') ?>" class="space-y-6" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $vehicle['id']) ?>"><?php endif; ?>

  <!-- Basics -->
  <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-zinc-900">Basics</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <div>
        <label for="f-make" class="block text-sm font-medium text-zinc-700 mb-1">Make *</label>
        <input id="f-make" name="make" type="text" required maxlength="80" value="<?= $val('make') ?>" placeholder="Toyota"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="f-model" class="block text-sm font-medium text-zinc-700 mb-1">Model *</label>
        <input id="f-model" name="model" type="text" required maxlength="120" value="<?= $val('model') ?>" placeholder="Vanguard"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="f-year" class="block text-sm font-medium text-zinc-700 mb-1">Year *</label>
          <input id="f-year" name="year" type="number" required min="1950" max="2100" value="<?= $val('year') ?>" placeholder="2013"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
        <div>
          <label for="f-condition" class="block text-sm font-medium text-zinc-700 mb-1">Condition *</label>
          <select id="f-condition" name="condition"
                  class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            <option value="Used" <?= ($vehicle !== null ? $vehicle['condition'] : 'Used') === 'Used' ? 'selected' : '' ?>>Used</option>
            <option value="New" <?= ($vehicle !== null ? $vehicle['condition'] : 'Used') === 'New' ? 'selected' : '' ?>>New</option>
          </select>
        </div>
      </div>
      <div>
        <label for="f-chassis" class="block text-sm font-medium text-zinc-700 mb-1">Chassis number</label>
        <input id="f-chassis" name="chassisNumber" type="text" maxlength="80" value="<?= $val('chassis_number') ?>" placeholder="Optional"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
    </div>
  </div>

  <!-- Price & specs -->
  <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-zinc-900">Price &amp; specifications</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <label for="f-price" class="block text-sm font-medium text-zinc-700 mb-1">Price (TSh) *</label>
        <input id="f-price" name="price" type="number" required min="0" step="1" value="<?= $val('price') ?>" placeholder="28500000"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="f-mileage" class="block text-sm font-medium text-zinc-700 mb-1">Mileage (km) *</label>
        <input id="f-mileage" name="mileageKm" type="number" required min="0" step="1" value="<?= $val('mileage_km') ?>" placeholder="87000"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="f-engine" class="block text-sm font-medium text-zinc-700 mb-1">Engine (cc)</label>
        <input id="f-engine" name="engineCc" type="number" min="0" step="1" value="<?= $val('engine_cc') ?>" placeholder="Optional"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="f-fuel" class="block text-sm font-medium text-zinc-700 mb-1">Fuel type *</label>
        <select id="f-fuel" name="fuelType"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <?php foreach (['Petrol', 'Diesel', 'Hybrid', 'Electric'] as $fuel): ?>
            <option value="<?= $fuel ?>" <?= ($vehicle['fuel_type'] ?? 'Petrol') === $fuel ? 'selected' : '' ?>><?= $fuel ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-transmission" class="block text-sm font-medium text-zinc-700 mb-1">Transmission</label>
        <select id="f-transmission" name="transmission"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="">—</option>
          <?php foreach (['Automatic', 'Manual', 'CVT', 'DCT'] as $t): ?>
            <option value="<?= $t ?>" <?= ($vehicle['transmission'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-drive" class="block text-sm font-medium text-zinc-700 mb-1">Drive type</label>
        <select id="f-drive" name="driveType"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="">—</option>
          <?php foreach (['FWD', 'RWD', 'AWD', '4WD'] as $d): ?>
            <option value="<?= $d ?>" <?= ($vehicle['drive_type'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-color" class="block text-sm font-medium text-zinc-700 mb-1">Exterior color</label>
        <input id="f-color" name="exteriorColor" type="text" maxlength="80" value="<?= $val('exterior_color') ?>" placeholder="Pearl White"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
    </div>
  </div>

  <!-- Listing & visibility -->
  <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-zinc-900">Listing &amp; visibility</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <label for="f-status" class="block text-sm font-medium text-zinc-700 mb-1">Status *</label>
        <select id="f-status" name="status"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="AVAILABLE" <?= ($vehicle['status'] ?? 'AVAILABLE') === 'AVAILABLE' ? 'selected' : '' ?>>Available</option>
          <option value="SOLD" <?= ($vehicle['status'] ?? '') === 'SOLD' ? 'selected' : '' ?>>Sold</option>
        </select>
        <p class="mt-1.5 flex items-start gap-1.5 text-xs text-zinc-500">
          <?= icon('info', 'h-3.5 w-3.5 shrink-0 mt-px') ?>
          <span>SOLD vehicles keep their photos in the dashboard but photos are hidden from the public</span>
        </p>
      </div>
      <div>
        <label for="f-offer-price" class="block text-sm font-medium text-zinc-700 mb-1">Offer price (TSh)</label>
        <input id="f-offer-price" name="offerPrice" type="number" min="0" step="1" value="<?= $val('offer_price') ?>" placeholder="Optional"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="f-offer-end" class="block text-sm font-medium text-zinc-700 mb-1">Offer ends</label>
        <input id="f-offer-end" name="offerEnd" type="date" value="<?= e(substr((string) ($vehicle['offer_end'] ?? ''), 0, 10)) ?>"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="flex items-center gap-6 sm:col-span-2 lg:col-span-3">
        <label class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-zinc-700">
          <input type="checkbox" name="published" value="1" <?= $on('published', true) ? 'checked' : '' ?> class="h-5 w-5 accent-brand">
          Published (visible on the public site)
        </label>
        <label class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-zinc-700">
          <input type="checkbox" name="featured" value="1" <?= $on('featured') ? 'checked' : '' ?> class="h-5 w-5 accent-brand">
          Featured (home page carousel)
        </label>
      </div>
    </div>

    <details class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4" <?= $isEdit && ($vehicle['offer_terms'] ?? '') !== '' ? 'open' : '' ?>>
      <summary class="cursor-pointer text-sm font-medium text-zinc-700">Offer terms</summary>
      <div class="mt-3">
        <label for="f-offer-terms" class="sr-only">Offer terms</label>
        <textarea id="f-offer-terms" name="offerTerms" rows="4" maxlength="3000"
                  placeholder="Terms shown alongside the offer badge (max 3000 characters)"
                  class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= $val('offer_terms') ?></textarea>
      </div>
    </details>
  </div>

  <!-- Description & features -->
  <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-zinc-900">Description &amp; features</h3>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div>
        <label for="f-description" class="block text-sm font-medium text-zinc-700 mb-1">Description</label>
        <textarea id="f-description" name="description" rows="6" maxlength="6000"
                  placeholder="Condition, service history, anything a buyer should know… (max 6000 characters)"
                  class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= $val('description') ?></textarea>
        <p class="mt-1 text-xs text-zinc-400">Line breaks are preserved on the public page.</p>
      </div>
      <div>
        <label for="f-features" class="block text-sm font-medium text-zinc-700 mb-1">Features</label>
        <textarea id="f-features" name="features" rows="6" maxlength="6000"
                  placeholder="Reverse camera&#10;Leather seats&#10;Sunroof"
                  class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= $val('features') ?></textarea>
        <p class="mt-1 text-xs text-zinc-400">One feature per line.</p>
      </div>
    </div>
  </div>

  <div class="flex items-center justify-end gap-3">
    <a href="<?= url('/admin/vehicles') ?>"
       class="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Cancel</a>
    <button type="submit"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('check', 'h-4 w-4') ?> Save
    </button>
  </div>
</form>

<!-- ------------------------------------------------------------------ Photos -->
<section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-labelledby="photos-h">
  <div class="flex items-center justify-between gap-3">
    <h3 id="photos-h" class="text-sm font-semibold text-zinc-900">Photos</h3>
    <?php if ($isEdit): ?><p class="text-xs font-medium text-zinc-500"><?= $imgCount ?>/12</p><?php endif; ?>
  </div>

  <?php if (!$isEdit): ?>
    <p class="mt-3 flex items-start gap-1.5 text-sm text-zinc-500">
      <?= icon('info', 'h-4 w-4 shrink-0 mt-px') ?>
      <span>Save the vehicle first, then add photos.</span>
    </p>
  <?php else: ?>

    <?php if ($images === []): ?>
      <p class="mt-3 text-sm text-zinc-400">No photos yet — add uploads or links below. The first photo becomes the cover.</p>
    <?php else: ?>
      <div class="mt-4 grid max-h-96 grid-cols-2 gap-4 overflow-y-auto scrollbar-slim sm:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($images as $img):
            $isCover = (int) $img['is_cover'] === 1;
            $imgId   = (string) $img['id'];
        ?>
        <figure class="overflow-hidden rounded-lg border border-zinc-200">
          <div class="relative">
            <img src="<?= url((string) $img['url']) ?>" alt="Vehicle photo"
                 class="aspect-[4/3] w-full object-cover" loading="lazy" width="320" height="240">
            <?php if ($isCover): ?>
              <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-brand px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Cover</span>
            <?php endif; ?>
          </div>
          <figcaption class="flex items-center justify-between gap-2 p-2">
            <?php if (!$isCover): ?>
              <form method="post" action="<?= url('/admin/vehicle-image') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cover">
                <input type="hidden" name="vehicle_id" value="<?= e((string) $vehicle['id']) ?>">
                <input type="hidden" name="image_id" value="<?= e($imgId) ?>">
                <button type="submit"
                        class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-200 px-2.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50"
                        title="Make this the cover photo">
                  <span class="h-3 w-3 rounded-full border border-zinc-400"></span> Cover
                </button>
              </form>
            <?php else: ?>
              <span class="inline-flex min-h-9 items-center gap-1.5 px-2.5 text-xs font-semibold text-brand">
                <span class="h-3 w-3 rounded-full bg-brand"></span> Cover
              </span>
            <?php endif; ?>
            <form method="post" action="<?= url('/admin/vehicle-image') ?>" data-confirm="Remove this photo?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="vehicle_id" value="<?= e((string) $vehicle['id']) ?>">
              <input type="hidden" name="image_id" value="<?= e($imgId) ?>">
              <button type="submit" aria-label="Remove photo"
                      class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-zinc-500 hover:border-red-200 hover:bg-red-50 hover:text-brand">
                <?= icon('trash-2', 'h-4 w-4') ?>
              </button>
            </form>
          </figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mt-5 grid gap-4 border-t border-zinc-100 pt-5 lg:grid-cols-2">
      <form method="post" action="<?= url('/admin/vehicle-image') ?>" enctype="multipart/form-data" class="rounded-lg border border-dashed border-zinc-300 p-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="vehicle_id" value="<?= e((string) $vehicle['id']) ?>">
        <label class="block text-sm font-medium text-zinc-700 mb-1">Add photos</label>
        <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"
               class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2.5 file:text-sm file:font-semibold file:text-zinc-700 hover:file:bg-zinc-200">
        <p class="mt-1.5 text-xs text-zinc-400">JPEG, PNG, WebP or GIF — up to 5 MB each, max 12 per vehicle, auto-resized to 1600px WebP</p>
        <button type="submit"
                class="mt-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('plus', 'h-4 w-4') ?> Upload
        </button>
      </form>

      <form method="post" action="<?= url('/admin/vehicle-image') ?>" class="rounded-lg border border-dashed border-zinc-300 p-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="link">
        <input type="hidden" name="vehicle_id" value="<?= e((string) $vehicle['id']) ?>">
        <label for="f-img-link" class="block text-sm font-medium text-zinc-700 mb-1">Add from link</label>
        <input id="f-img-link" name="url" type="url" maxlength="600" placeholder="https://…"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        <p class="mt-1.5 text-xs text-zinc-400">Paste a direct image URL hosted elsewhere.</p>
        <button type="submit"
                class="mt-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
          <?= icon('link', 'h-4 w-4') ?> Add Link
        </button>
      </form>
    </div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
