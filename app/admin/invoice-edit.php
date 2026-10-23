<?php
/**
 * Admin — Invoice editor.
 * Create mode (no ?id=)  → manual invoice, POST /admin/invoice-save action=create
 * Edit mode   (?id=)     → adjust discount/charges, POST action=update
 */

$staff = require_staff();

$admin_title  = 'Invoices';
$admin_active = 'invoices';

$editId  = trim((string) ($_GET['id'] ?? ''));
$invoice = $editId !== '' ? db_one('SELECT * FROM invoices WHERE id = ?', [$editId]) : null;
$snap    = $invoice !== null ? snapshot_decode($invoice['vehicle_snapshot']) : [];

if ($editId !== '' && $invoice === null) {
    flash('error', 'Invoice not found.');
    redirect('/admin/invoices');
}

$admin_title = $invoice !== null ? 'Edit ' . $invoice['number'] : 'Invoices';

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <a href="<?= e(url('/admin/invoices')) ?>" class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-brand">
    <?= icon('arrow-left', 'h-4 w-4') ?> Back to invoices
  </a>

  <?php if ($invoice === null): ?>
  <!-- ============================ Create mode ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="New invoice">
    <h2 class="text-lg font-bold text-zinc-900">New Invoice</h2>
    <p class="mt-0.5 text-sm text-zinc-500">Create a manual invoice for a walk-in or phone sale. The number is assigned automatically.</p>

    <form method="post" action="<?= url('/admin/invoice-save') ?>" class="mt-4 grid gap-4 lg:grid-cols-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div>
        <label for="c-name" class="block text-sm font-medium text-zinc-700 mb-1">Customer name <span class="text-brand">*</span></label>
        <input id="c-name" name="customerName" type="text" required maxlength="200"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="c-phone" class="block text-sm font-medium text-zinc-700 mb-1">Phone <span class="text-brand">*</span></label>
        <input id="c-phone" name="customerPhone" type="tel" required maxlength="40"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="c-email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
        <input id="c-email" name="customerEmail" type="email" maxlength="200"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="c-city" class="block text-sm font-medium text-zinc-700 mb-1">City</label>
        <input id="c-city" name="customerCity" type="text" maxlength="120" value="Dar es Salaam"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="lg:col-span-2">
        <label for="c-address" class="block text-sm font-medium text-zinc-700 mb-1">Address</label>
        <input id="c-address" name="customerAddress" type="text" maxlength="300" placeholder="Street / area"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="lg:col-span-2">
        <label for="c-item" class="block text-sm font-medium text-zinc-700 mb-1">Item <span class="text-brand">*</span></label>
        <input id="c-item" name="itemName" type="text" required maxlength="300" placeholder="e.g. Toyota Harrier 2018 SUV"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="c-price" class="block text-sm font-medium text-zinc-700 mb-1">Price (TSh) <span class="text-brand">*</span></label>
        <input id="c-price" name="price" type="number" required min="0" step="1"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="c-discount" class="block text-sm font-medium text-zinc-700 mb-1">Discount (TSh)</label>
          <input id="c-discount" name="discount" type="number" min="0" step="1" value="0"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
        <div>
          <label for="c-charges" class="block text-sm font-medium text-zinc-700 mb-1">Additional charges (TSh)</label>
          <input id="c-charges" name="additionalCharges" type="number" min="0" step="1" value="0"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>

      <div class="lg:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Totals are computed automatically (Price − Discount + Additional charges). The payment notes printed on the PDF are snapshotted from Settings at creation.
      </div>

      <div class="lg:col-span-2 flex justify-end">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('file-text', 'h-4 w-4') ?> Create Invoice
        </button>
      </div>
    </form>
  </section>

  <?php else: ?>
  <!-- ============================= Edit mode ============================= -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Edit invoice">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-lg font-bold text-zinc-900">Edit <span class="font-mono"><?= e((string) $invoice['number']) ?></span></h2>
      <div class="flex items-center gap-2">
        <a href="<?= e(url('/admin/invoice-pdf?id=' . rawurlencode((string) $invoice['id']))) ?>"
           class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
          <?= icon('download', 'h-3.5 w-3.5') ?> PDF
        </a>
        <a href="<?= e(url('/admin/invoices?id=' . rawurlencode((string) $invoice['id']))) ?>"
           class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
          <?= icon('share-2', 'h-3.5 w-3.5') ?> Share
        </a>
      </div>
    </div>

    <!-- Read-only customer + item -->
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Customer</h3>
        <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2">
          <dt class="text-zinc-500">Name</dt><dd class="font-medium text-zinc-900"><?= e((string) $invoice['customer_name']) ?></dd>
          <dt class="text-zinc-500">Phone</dt><dd class="text-zinc-900"><?= e((string) $invoice['customer_phone']) ?></dd>
          <dt class="text-zinc-500">Email</dt><dd class="text-zinc-900"><?= $invoice['customer_email'] !== '' ? e((string) $invoice['customer_email']) : '—' ?></dd>
          <dt class="text-zinc-500">City</dt><dd class="text-zinc-900"><?= e((string) $invoice['customer_city']) ?></dd>
          <dt class="text-zinc-500">Address</dt><dd class="text-zinc-900"><?= $invoice['customer_address'] !== null && $invoice['customer_address'] !== '' ? e((string) $invoice['customer_address']) : '—' ?></dd>
        </dl>
      </div>
      <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Item</h3>
        <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2">
          <dt class="text-zinc-500">Description</dt><dd class="font-medium text-zinc-900"><?= e((string) ($snap['name'] ?? '—')) ?></dd>
          <dt class="text-zinc-500">Price at issue</dt><dd class="text-zinc-900"><?= e(format_tsh((int) $invoice['price_at_issue'])) ?></dd>
          <dt class="text-zinc-500">Issued</dt><dd class="text-zinc-900"><?= e(format_date_time((string) $invoice['created_at'])) ?></dd>
        </dl>
      </div>
    </div>

    <!-- Editable totals -->
    <form method="post" action="<?= url('/admin/invoice-save') ?>" class="mt-4 border-t border-zinc-200 pt-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="invoice_id" value="<?= e((string) $invoice['id']) ?>">
      <input type="hidden" id="ed-price" value="<?= (int) $invoice['price_at_issue'] ?>">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="ed-discount" class="block text-sm font-medium text-zinc-700 mb-1">Discount (TSh)</label>
          <input type="number" id="ed-discount" name="discount" min="0" step="1" value="<?= (int) $invoice['discount'] ?>" oninput="iacEditTotal()"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
        <div>
          <label for="ed-charges" class="block text-sm font-medium text-zinc-700 mb-1">Additional charges (TSh)</label>
          <input type="number" id="ed-charges" name="additionalCharges" min="0" step="1" value="<?= (int) $invoice['additional_charges'] ?>" oninput="iacEditTotal()"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>
      <p class="mt-3 text-sm text-zinc-700">
        Price <span class="text-zinc-400">(<?= e(format_tsh((int) $invoice['price_at_issue'])) ?>)</span>
        − Discount + Charges =
        <span class="font-semibold text-zinc-900">Total: <span id="ed-total"><?= e(format_tsh((int) $invoice['total'])) ?></span></span>
      </p>

      <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-4">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">Notes &amp; payments instructions (snapshot)</h3>
        <p class="whitespace-pre-line text-sm text-zinc-700"><?= $invoice['notes'] !== '' ? e((string) $invoice['notes']) : '—' ?></p>
      </div>

      <div class="mt-4 flex justify-end">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('check', 'h-4 w-4') ?> Save changes
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>
</div>

<?php if ($invoice !== null): ?>
<script>
function iacEditTotal() {
  var price = parseInt(document.getElementById('ed-price').value, 10) || 0;
  var d = parseInt(document.getElementById('ed-discount').value, 10) || 0;
  var c = parseInt(document.getElementById('ed-charges').value, 10) || 0;
  var total = Math.max(price - d, 0) + c;
  document.getElementById('ed-total').textContent =
    'TSh' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
