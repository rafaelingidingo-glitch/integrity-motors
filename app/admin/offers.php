<?php
/**
 * Admin — Offers list + "New Offer" form (?new=1).
 * Creation posts to offer-save.php; activation toggles to offer-toggle.php.
 */

$staff = require_staff();

$admin_title  = 'Offers';
$admin_active = 'offers';

$OFF_META = [
    'ACTIVE'   => ['Active',   'bg-green-100 text-green-700'],
    'INACTIVE' => ['Inactive', 'bg-zinc-200 text-zinc-700'],
];

$rows = db_all('SELECT * FROM offers ORDER BY created_at DESC LIMIT 200');

$showNew = ($_GET['new'] ?? '') === '1';

$vehicles = db_all(
    "SELECT id, make, model, year, price FROM vehicles
     WHERE status = 'AVAILABLE' AND published = 1
     ORDER BY created_at DESC LIMIT 48"
);

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <?php if ($showNew): ?>
  <!-- ============================ New offer form ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="New offer">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-zinc-900">New Offer</h2>
      <a href="<?= e(url('/admin/offers')) ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
        <?= icon('x', 'h-3.5 w-3.5') ?> Close
      </a>
    </div>

    <form method="post" action="<?= url('/admin/offer-save') ?>" class="mt-4 grid gap-4 lg:grid-cols-2" id="offer-form">
      <?= csrf_field() ?>

      <div class="lg:col-span-2">
        <label for="o-vehicle" class="block text-sm font-medium text-zinc-700 mb-1">Vehicle <span class="text-brand">*</span></label>
        <select id="o-vehicle" name="vehicleId" required onchange="iacOfferHint()"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="">Select a vehicle…</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= e((string) $v['id']) ?>" data-price="<?= (int) $v['price'] ?>">
              <?= e($v['make'] . ' ' . $v['model'] . ' ' . $v['year'] . ' — ' . format_tsh((int) $v['price'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p id="o-hint" class="mt-1 text-xs text-zinc-500">Select a vehicle first.</p>
      </div>

      <div>
        <label for="o-name" class="block text-sm font-medium text-zinc-700 mb-1">Customer name</label>
        <input id="o-name" name="customerName" type="text" maxlength="200" placeholder="Optional — leave blank for a walk-in customer"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="o-phone" class="block text-sm font-medium text-zinc-700 mb-1">Phone</label>
        <input id="o-phone" name="customerPhone" type="tel" maxlength="40" placeholder="Optional"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="o-email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
        <input id="o-email" name="customerEmail" type="email" maxlength="200" placeholder="Optional"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="o-price" class="block text-sm font-medium text-zinc-700 mb-1">Offer price (TSh) <span class="text-brand">*</span></label>
        <input id="o-price" name="offerPrice" type="number" required min="1" step="1" oninput="iacOfferHint()"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        <p class="mt-1 text-xs text-zinc-500">Must be below the regular price.</p>
      </div>
      <div>
        <label for="o-from" class="block text-sm font-medium text-zinc-700 mb-1">Valid from</label>
        <input id="o-from" name="validFrom" type="date" required value="<?= e(today_eat()) ?>"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="o-to" class="block text-sm font-medium text-zinc-700 mb-1">Valid until</label>
        <input id="o-to" name="validEnd" type="date"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        <p class="mt-1 text-xs text-zinc-500">Leave blank to run until cancelled.</p>
      </div>
      <div class="lg:col-span-2">
        <label for="o-terms" class="block text-sm font-medium text-zinc-700 mb-1">Terms</label>
        <textarea id="o-terms" name="terms" rows="3" maxlength="5000" placeholder="Optional — shown on the offer PDF"
                  class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
      </div>

      <div class="lg:col-span-2 flex justify-end">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('ticket', 'h-4 w-4') ?> Create Offer
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="flex items-center justify-between gap-3">
    <p class="text-sm text-zinc-600">Latest <?= count($rows) ?> offers. Active offers are mirrored onto the public vehicle price.</p>
    <a href="<?= e(url('/admin/offers?new=1')) ?>"
       class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('plus', 'h-4 w-4') ?> New Offer
    </a>
  </div>

  <!-- List -->
  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Number</th>
            <th class="px-4 py-3 font-semibold">Vehicle</th>
            <th class="px-4 py-3 font-semibold">Customer</th>
            <th class="px-4 py-3 font-semibold">Pricing</th>
            <th class="px-4 py-3 font-semibold">Valid</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-zinc-500">No offers yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row):
            $oSnap = snapshot_decode($row['vehicle_snapshot']);
            [$oLabel, $oChip] = $OFF_META[$row['status']] ?? [$row['status'], 'bg-zinc-200 text-zinc-700'];
          ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3 font-mono font-medium text-zinc-900"><?= e((string) $row['number']) ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5"><?= icon('car', 'h-4 w-4 text-zinc-400') ?> <?= e((string) ($oSnap['name'] ?? '—')) ?></span>
              </td>
              <td class="px-4 py-3 text-zinc-700"><?= $row['customer_name'] !== null && $row['customer_name'] !== '' ? e((string) $row['customer_name']) : '<span class="text-zinc-400">Walk-in Customer</span>' ?></td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="text-zinc-400 line-through"><?= e(format_tsh((int) $row['regular_price'])) ?></span>
                <span class="ml-1 font-semibold text-zinc-900"><?= e(format_tsh((int) $row['offer_price'])) ?></span>
                <span class="ml-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-brand/10 text-brand">−<?= (int) $row['discount_percent'] ?>%</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600">
                <?= e(format_date_dmy((string) $row['valid_from'])) ?>
                →
                <?= $row['valid_to'] !== null && $row['valid_to'] !== '' ? e(format_date_dmy((string) $row['valid_to'])) : 'Until cancelled' ?>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $oChip ?>"><?= e($oLabel) ?></span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                  <form method="post" action="<?= url('/admin/offer-toggle') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="offer_id" value="<?= e((string) $row['id']) ?>">
                    <input type="hidden" name="target" value="<?= e($row['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE') ?>">
                    <button type="submit"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                      <?= icon('refresh-cw', 'h-3.5 w-3.5') ?> <?= $row['status'] === 'ACTIVE' ? 'Set Inactive' : 'Set Active' ?>
                    </button>
                  </form>
                  <a href="<?= e(url('/admin/offer-pdf?id=' . rawurlencode((string) $row['id']))) ?>"
                     title="Download PDF"
                     class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    <?= icon('download', 'h-3.5 w-3.5') ?> PDF
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function iacOfferHint() {
  var select = document.getElementById('o-vehicle');
  var priceInput = document.getElementById('o-price');
  var hint = document.getElementById('o-hint');
  if (!select || !hint) return;
  var opt = select.options[select.selectedIndex];
  var regular = opt ? parseInt(opt.getAttribute('data-price') || '', 10) : NaN;
  var offer = priceInput ? parseInt(priceInput.value, 10) : NaN;
  if (!opt || isNaN(regular)) {
    hint.textContent = 'Select a vehicle first.';
    return;
  }
  var text = 'Regular price: TSh' + regular.toLocaleString('en-US');
  if (!isNaN(offer) && offer > 0) {
    var pct = Math.round(((regular - offer) / regular) * 100);
    text += offer < regular
      ? ' — you save ' + pct + '% (TSh' + (regular - offer).toLocaleString('en-US') + ')'
      : ' — the offer must be below the regular price.';
  }
  hint.textContent = text;
}
</script>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
