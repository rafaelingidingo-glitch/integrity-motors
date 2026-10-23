<?php
/**
 * Admin — Invoices list + share panel (?id=).
 * Share-link creation posts to invoice-share.php; payment status inline
 * auto-submits to invoice-save.php (action=payment).
 */

$staff = require_staff();

$admin_title  = 'Invoices';
$admin_active = 'invoices';
$PAY_META = [
    'UNPAID'  => ['Unpaid',  'bg-amber-100 text-amber-700'],
    'PARTIAL' => ['Partial', 'bg-orange-100 text-orange-700'],
    'PAID'    => ['Paid',    'bg-green-100 text-green-700'],
];

$q = trim((string) ($_GET['q'] ?? ''));

$where  = [];
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(number LIKE ? OR customer_name LIKE ?)';
    array_push($params, $like, $like);
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = db_all("SELECT * FROM invoices $whereSql ORDER BY created_at DESC LIMIT 200", $params);

$highlight = trim((string) ($_GET['highlight'] ?? ''));

// ----------------------------- Share panel (?id=) -----------------------------
$shareInvoice = null;
$shareLink = '';
$waMessage = '';
$waPhone = '';
$waHref = '';
$shareId = trim((string) ($_GET['id'] ?? ''));
if ($shareId !== '') {
    $shareInvoice = db_one('SELECT * FROM invoices WHERE id = ?', [$shareId]);
    if ($shareInvoice !== null) {
        $business  = (string) settings()['business_name'];
        $shareLink = app_url('/invoice/' . $shareInvoice['id'] . '?t=' . (string) $shareInvoice['share_token']);
        $waPhone   = (string) $shareInvoice['customer_phone'];
        $waMessage = "Hello {$shareInvoice['customer_name']},\n"
            . "\n"
            . "Here is your invoice from {$business}:\n"
            . "\n"
            . "Invoice: {$shareInvoice['number']}\n"
            . 'Total: ' . format_tsh((int) $shareInvoice['total']) . "\n"
            . "\n"
            . "View or download your invoice here:\n"
            . "{$shareLink}\n"
            . "\n"
            . "Payment instructions are included in the invoice. Thank you for choosing {$business}!";
        $waHref = wa_link($waPhone, $waMessage);
    }
}

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <?php if ($shareId !== '' && $shareInvoice === null): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Invoice not found.</div>
  <?php endif; ?>

  <?php if ($shareInvoice !== null): ?>
  <!-- ============================ Share panel ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Share invoice">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-zinc-900">Share invoice <span class="font-mono"><?= e((string) $shareInvoice['number']) ?></span></h2>
        <p class="mt-0.5 text-sm text-zinc-500"><?= e((string) $shareInvoice['customer_name']) ?> · <?= e(format_tsh((int) $shareInvoice['total'])) ?></p>
      </div>
      <a href="<?= e(url('/admin/invoices')) ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
        <?= icon('x', 'h-3.5 w-3.5') ?> Close
      </a>
    </div>

    <?php if ($shareInvoice['share_token'] === null || $shareInvoice['share_token'] === ''): ?>
      <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <p class="text-sm text-zinc-600">This invoice has no public link yet. Create one so the customer can view or download the PDF.</p>
        <form method="post" action="<?= url('/admin/invoice-share') ?>" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="invoice_id" value="<?= e((string) $shareInvoice['id']) ?>">
          <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
            <?= icon('link', 'h-4 w-4') ?> Create share link
          </button>
        </form>
      </div>
    <?php else: ?>
      <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div>
          <label class="block text-sm font-medium text-zinc-700 mb-1" for="share-link">Public link</label>
          <div class="flex gap-2">
            <input id="share-link" type="text" readonly value="<?= e($shareLink) ?>"
                   class="h-11 w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            <button type="button" data-copy="#share-link"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
              <?= icon('link', 'h-4 w-4') ?> Copy
            </button>
          </div>

          <label class="block text-sm font-medium text-zinc-700 mb-1 mt-4" for="wa-phone">Phone number</label>
          <input id="wa-phone" type="tel" value="<?= e($waPhone) ?>"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>

        <div>
          <label class="block text-sm font-medium text-zinc-700 mb-1" for="wa-message">WhatsApp message</label>
          <textarea id="wa-message" rows="10"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= e($waMessage) ?></textarea>
          <a id="wa-send-link" href="<?= e($waHref) ?>" target="_blank" rel="noopener"
             class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-wa px-4 text-sm font-semibold text-white hover:bg-wa-dark">
            <?= icon('whatsapp', 'h-4 w-4') ?> Send via WhatsApp
          </a>
        </div>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <form method="get" action="<?= url('/admin/invoices') ?>" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400') ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search number or customer…"
               aria-label="Search invoices"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
        Search
      </button>
    </form>
    <a href="<?= e(url('/admin/invoice-edit')) ?>"
       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('plus', 'h-4 w-4') ?> New Invoice
    </a>
  </div>

  <!-- List -->
  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Number</th>
            <th class="px-4 py-3 font-semibold">Customer</th>
            <th class="px-4 py-3 font-semibold">Total</th>
            <th class="px-4 py-3 font-semibold">Payment</th>
            <th class="px-4 py-3 font-semibold">Issued</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No invoices yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php $isHi = $highlight !== '' && $row['number'] === $highlight; ?>
            <tr class="<?= $isHi ? 'bg-brand/10' : 'hover:bg-zinc-50' ?>">
              <td class="px-4 py-3 font-mono font-medium text-zinc-900"><?= e((string) $row['number']) ?></td>
              <td class="px-4 py-3 text-zinc-700"><?= e((string) $row['customer_name']) ?></td>
              <td class="px-4 py-3 font-medium text-zinc-900"><?= e(format_tsh((int) $row['total'])) ?></td>
              <td class="px-4 py-3">
                <form method="post" action="<?= url('/admin/invoice-save') ?>" class="relative flex items-center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="payment">
                  <input type="hidden" name="invoice_id" value="<?= e((string) $row['id']) ?>">
                  <!-- relative ^ makes it the containing block for the sr-only label,
                       so the absolutely-positioned label is clipped by the table's
                       overflow-x-auto wrapper instead of stretching the page. -->
                  <label class="sr-only" for="pay-<?= e((string) $row['id']) ?>">Payment status</label>
                  <select id="pay-<?= e((string) $row['id']) ?>" name="paymentStatus" data-autosubmit
                          class="h-9 rounded-lg border border-zinc-300 bg-white px-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <?php foreach (PAYMENT_STATUSES as $ps): ?>
                      <option value="<?= e($ps) ?>" <?= $row['payment_status'] === $ps ? 'selected' : '' ?>><?= e($PAY_META[$ps][0]) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <noscript><button type="submit" class="ml-1 rounded-lg border border-zinc-300 px-2 py-1 text-xs">Save</button></noscript>
                </form>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_time((string) $row['created_at'])) ?></td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                  <a href="<?= e(url('/admin/invoice-pdf?id=' . rawurlencode((string) $row['id']))) ?>"
                     title="Download PDF"
                     class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    <?= icon('download', 'h-3.5 w-3.5') ?> PDF
                  </a>
                  <a href="<?= e(url('/admin/invoice-edit?id=' . rawurlencode((string) $row['id']))) ?>"
                     title="Edit invoice"
                     class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    <?= icon('pencil', 'h-3.5 w-3.5') ?> Edit
                  </a>
                  <a href="<?= e(url('/admin/invoices?id=' . rawurlencode((string) $row['id']))) ?>"
                     title="Share invoice"
                     class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                    <?= icon('share-2', 'h-3.5 w-3.5') ?> Share
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

<?php if ($shareInvoice !== null && $shareInvoice['share_token'] !== null && $shareInvoice['share_token'] !== ''): ?>
<script>
(function () {
  var link = document.getElementById('wa-send-link');
  var phone = document.getElementById('wa-phone');
  var message = document.getElementById('wa-message');
  if (!link || !phone || !message) return;
  function update() {
    var digits = (phone.value || '').replace(/\D+/g, '');
    if (digits.charAt(0) === '0') digits = '255' + digits.slice(1);
    link.href = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(message.value);
  }
  phone.addEventListener('input', update);
  message.addEventListener('input', update);
})();
</script>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
