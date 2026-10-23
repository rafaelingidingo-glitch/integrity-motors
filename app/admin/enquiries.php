<?php
/**
 * Admin — Enquiries list + detail panel (?id=).
 * Actions live in enquiry-save.php / enquiry-note.php / enquiry-invoice.php.
 */

$staff = require_staff();

$admin_title  = 'Enquiries';
$admin_active = 'enquiries';

$ENQ_META = [
    'NEW'               => ['New',               'bg-amber-100 text-amber-700',   'bg-amber-600 border-amber-600 text-white'],
    'CONTACTED'         => ['Contacted',         'bg-teal-100 text-teal-700',     'bg-teal-600 border-teal-600 text-white'],
    'OFFER_SENT'        => ['Offer Sent',        'bg-violet-100 text-violet-700', 'bg-violet-600 border-violet-600 text-white'],
    'INVOICE_GENERATED' => ['Invoice Generated', 'bg-orange-100 text-orange-700', 'bg-orange-600 border-orange-600 text-white'],
    'CONFIRMED'         => ['Confirmed',         'bg-green-100 text-green-700',   'bg-green-600 border-green-600 text-white'],
    'CLOSED'            => ['Closed',            'bg-zinc-200 text-zinc-700',     'bg-zinc-600 border-zinc-600 text-white'],
    'NOT_PROCEEDING'    => ['Not Proceeding',    'bg-red-100 text-red-700',       'bg-red-600 border-red-600 text-white'],
];

// ---------------------------------------------------------------------------
// Filters + list query
// ---------------------------------------------------------------------------
[$page, $pageSize, $offset] = page_context(20);

$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, ENQUIRY_STATUSES, true)) {
    $status = '';
}

$searchWhere = [];
$searchWhereSql = '';
$searchParams = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $searchWhere[] = '(e.full_name LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)';
    array_push($searchParams, $like, $like, $like);
    $searchWhereSql = 'WHERE ' . implode(' AND ', $searchWhere);
}

$where   = $searchWhere;
$params  = $searchParams;
if ($status !== '') {
    $where[] = 'e.status = ?';
    $params[] = $status;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_scalar("SELECT COUNT(*) FROM enquiries e $whereSql", $params);

$rows = db_all(
    "SELECT e.*, v.make, v.model, v.year, s.name AS staff_name,
            (SELECT COUNT(*) FROM enquiry_notes en WHERE en.enquiry_id = e.id) AS note_count
     FROM enquiries e
     LEFT JOIN vehicles v ON v.id = e.vehicle_id
     LEFT JOIN staff_users s ON s.id = e.assigned_to_id
     $whereSql
     ORDER BY e.created_at DESC
     LIMIT $pageSize OFFSET $offset",
    $params
);

// Per-status counts (search filter respected so chips stay meaningful)
$statusCounts = ['_all' => 0];
foreach (db_all("SELECT e.status, COUNT(*) AS c FROM enquiries e $searchWhereSql GROUP BY e.status", $searchParams) as $c) {
    $statusCounts[(string) $c['status']] = (int) $c['c'];
    $statusCounts['_all'] += (int) $c['c'];
}

$totalPages = max(1, (int) ceil($total / max(1, $pageSize)));

// ---------------------------------------------------------------------------
// Detail panel data (?id=)
// ---------------------------------------------------------------------------
$detailId  = trim((string) ($_GET['id'] ?? ''));
$detail    = null;
$snap      = [];
$notes     = [];
$invoices  = [];
$activeStaff = db_all("SELECT id, name FROM staff_users WHERE is_active = 1 ORDER BY name ASC");

if ($detailId !== '') {
    $detail = db_one(
        'SELECT e.*, s.name AS staff_name FROM enquiries e
         LEFT JOIN staff_users s ON s.id = e.assigned_to_id
         WHERE e.id = ?',
        [$detailId]
    );
    if ($detail !== null) {
        $snap = snapshot_decode($detail['vehicle_snapshot']);
        $notes = db_all(
            'SELECT en.*, su.name AS staff_name FROM enquiry_notes en
             LEFT JOIN staff_users su ON su.id = en.staff_id
             WHERE en.enquiry_id = ? ORDER BY en.created_at DESC',
            [$detailId]
        );
        $invoices = db_all(
            'SELECT id, number, total, payment_status FROM invoices WHERE enquiry_id = ? ORDER BY created_at DESC',
            [$detailId]
        );
    }
}

$detailUrl = $detail !== null ? url('/admin/enquiries?id=' . rawurlencode((string) $detail['id'])) : null;

/** Build a list URL preserving the current search (chips / pagination). */
$enq_list_url = function (string $statusVal) use ($q): string {
    $qs = [];
    if ($q !== '') {
        $qs['q'] = $q;
    }
    if ($statusVal !== '') {
        $qs['status'] = $statusVal;
    }
    return url('/admin/enquiries' . ($qs === [] ? '' : '?' . http_build_query($qs)));
};

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <!-- Toolbar -->
  <form method="get" action="<?= url('/admin/enquiries') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center">
    <div class="relative flex-1">
      <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400') ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone or email…"
             aria-label="Search enquiries"
             class="h-11 w-full rounded-lg border border-zinc-300 bg-white pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
    </div>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      Search
    </button>
  </form>

  <!-- Status chips -->
  <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by status">
    <?php
    $allCls = $status === '' ? 'bg-brand border-brand text-white' : 'bg-zinc-200 text-zinc-700 border-transparent';
    ?>
    <a href="<?= e($enq_list_url('')) ?>"
       class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold <?= $allCls ?>">
      All (<?= $statusCounts['_all'] ?>)
    </a>
    <?php foreach (ENQUIRY_STATUSES as $st): ?>
      <?php [$stLabel, $stChip, $stActive] = $ENQ_META[$st]; ?>
      <a href="<?= e($enq_list_url($st)) ?>"
         class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold <?= $status === $st ? $stActive : $stChip . ' border-transparent' ?>">
        <?= e($stLabel) ?> (<?= $statusCounts[$st] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <!-- List -->
  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Customer</th>
            <th class="px-4 py-3 font-semibold">Vehicle</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">Assigned</th>
            <th class="px-4 py-3 font-semibold">Created</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No enquiries found.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $hasVehicle = $row['make'] !== null && $row['make'] !== '';
            [$stLabel, $stChip] = $ENQ_META[$row['status']] ?? [$row['status'], 'bg-zinc-200 text-zinc-700'];
            ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3">
                <p class="font-medium text-zinc-900"><?= e((string) $row['full_name']) ?></p>
                <p class="text-xs text-zinc-500"><?= e((string) $row['phone']) ?></p>
                <p class="text-xs text-zinc-500"><?= e((string) $row['email']) ?></p>
              </td>
              <td class="px-4 py-3">
                <?php if ($hasVehicle): ?>
                  <span class="inline-flex items-center gap-1.5"><?= icon('car', 'h-4 w-4 text-zinc-400') ?> <?= e($row['make'] . ' ' . $row['model'] . ' ' . $row['year']) ?></span>
                <?php else: ?>
                  <span class="text-zinc-500">General enquiry</span>
                <?php endif; ?>
                <?php if ((int) $row['note_count'] > 0): ?>
                  <p class="mt-1 text-[11px] text-zinc-400"><?= (int) $row['note_count'] ?> note<?= (int) $row['note_count'] === 1 ? '' : 's' ?></p>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $stChip ?>"><?= e($stLabel) ?></span>
              </td>
              <td class="px-4 py-3 text-zinc-600"><?= $row['staff_name'] !== null ? e((string) $row['staff_name']) : '<span class="text-zinc-400">Unassigned</span>' ?></td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_time((string) $row['created_at'])) ?></td>
              <td class="px-4 py-3 text-right">
                <a href="<?= e(url('/admin/enquiries?id=' . rawurlencode((string) $row['id']))) ?>"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                  <?= icon('external-link', 'h-3.5 w-3.5') ?> Open
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div class="flex items-center justify-between text-sm text-zinc-600">
    <p>Page <?= $page ?> of <?= $totalPages ?> · <?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></p>
    <div class="flex items-center gap-2">
      <?php if ($page > 1): ?>
        <a href="<?= e($enq_list_url($status) . (str_contains($enq_list_url($status), '?') ? '&' : '?') . 'page=' . ($page - 1)) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</span>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e($enq_list_url($status) . (str_contains($enq_list_url($status), '?') ? '&' : '?') . 'page=' . ($page + 1)) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100">Next <?= icon('chevron-right', 'h-4 w-4') ?></a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400">Next <?= icon('chevron-right', 'h-4 w-4') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($detailId !== '' && $detail === null): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Enquiry not found.</div>
  <?php endif; ?>

  <?php if ($detail !== null):
    $dStatus   = (string) $detail['status'];
    [$dStLabel, $dStChip] = $ENQ_META[$dStatus] ?? [$dStatus, 'bg-zinc-200 text-zinc-700'];
    $snapName  = (string) ($snap['name'] ?? 'General enquiry');
    $isVehicle = ($snap['vehicleId'] ?? null) !== null;
    $priceAtEnquiry = (int) ($snap['priceAtEnquiry'] ?? 0);
    $bizName   = (string) settings()['business_name'];
    $about     = ($snapName !== '' && $snapName !== 'General enquiry') ? $snapName : 'my enquiry';
    $waMsg     = "Hello {$bizName}, I am following up on my enquiry about {$about}.";
  ?>
  <!-- ============================ Detail panel ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Enquiry detail">

    <!-- Header -->
    <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex flex-wrap items-center gap-3">
          <h2 class="text-lg font-bold text-zinc-900"><?= e((string) $detail['full_name']) ?></h2>
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $dStChip ?>"><?= e($dStLabel) ?></span>
        </div>
        <p class="mt-1 text-sm text-zinc-500">Created <?= e(format_date_time((string) $detail['created_at'])) ?></p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ((string) ($detail['phone'] ?? '') !== ''): ?>
          <a href="<?= e(tel_link((string) $detail['phone'])) ?>"
             class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
            <?= icon('phone', 'h-4 w-4') ?> Call
          </a>
        <?php endif; ?>
        <a href="<?= e(wa_link((string) settings()['whatsapp_number'], $waMsg)) ?>" target="_blank" rel="noopener"
           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-wa px-4 text-sm font-semibold text-white hover:bg-wa-dark">
          <?= icon('whatsapp', 'h-4 w-4') ?> WhatsApp
        </a>
        <a href="<?= e('mailto:' . (string) $detail['email']) ?>"
           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
          <?= icon('mail', 'h-4 w-4') ?> Email
        </a>
      </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <!-- Snapshot box -->
      <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Vehicle snapshot</h3>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <dt class="text-zinc-500">Vehicle</dt>
          <dd class="font-medium text-zinc-900"><?= e($snapName) ?></dd>
          <?php if ($isVehicle): ?>
            <dt class="text-zinc-500">Price at enquiry</dt>
            <dd class="font-medium text-zinc-900"><?= e(format_tsh($priceAtEnquiry)) ?></dd>
            <dt class="text-zinc-500">Mileage</dt>
            <dd class="text-zinc-900"><?= e(format_km(isset($snap['mileageKm']) ? (int) $snap['mileageKm'] : null)) ?></dd>
            <dt class="text-zinc-500">Fuel</dt>
            <dd class="text-zinc-900"><?= e((string) ($snap['fuelType'] ?? '—')) ?></dd>
            <dt class="text-zinc-500">Condition</dt>
            <dd class="text-zinc-900"><?= e((string) ($snap['condition'] ?? '—')) ?></dd>
            <dt class="text-zinc-500">Chassis</dt>
            <dd class="text-zinc-900"><?= $snap['chassisNumber'] ?? null ? e((string) $snap['chassisNumber']) : '—' ?></dd>
          <?php endif; ?>
          <dt class="text-zinc-500">City</dt>
          <dd class="text-zinc-900"><?= e((string) $detail['city']) ?></dd>
          <dt class="text-zinc-500">Address</dt>
          <dd class="whitespace-pre-line text-zinc-900"><?= $detail['address'] !== null && $detail['address'] !== '' ? e((string) $detail['address']) : '—' ?></dd>
        </dl>
        <?php if ($detail['message'] !== null && trim((string) $detail['message']) !== ''): ?>
          <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">Message</h3>
          <p class="whitespace-pre-line rounded-lg bg-white p-3 text-sm text-zinc-800"><?= e((string) $detail['message']) ?></p>
        <?php endif; ?>
      </div>

      <div class="flex flex-col gap-4">
        <!-- Status + assignment form -->
        <form method="post" action="<?= url('/admin/enquiry-save') ?>" class="rounded-lg border border-zinc-200 bg-white p-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="enquiry_id" value="<?= e((string) $detail['id']) ?>">
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label for="enq-status" class="block text-sm font-medium text-zinc-700 mb-1">Status</label>
              <select id="enq-status" name="status" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <?php foreach (ENQUIRY_STATUSES as $st): ?>
                  <option value="<?= e($st) ?>" <?= $dStatus === $st ? 'selected' : '' ?>><?= e($ENQ_META[$st][0]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="enq-assigned" class="block text-sm font-medium text-zinc-700 mb-1">Assigned staff</label>
              <select id="enq-assigned" name="assigned_to_id" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="">Unassigned</option>
                <?php foreach ($activeStaff as $stf): ?>
                  <option value="<?= e((string) $stf['id']) ?>" <?= $detail['assigned_to_id'] === $stf['id'] ? 'selected' : '' ?>><?= e((string) $stf['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
              <?= icon('check', 'h-4 w-4') ?> Save
            </button>
          </div>
        </form>

        <!-- Internal notes -->
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
          <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Internal notes</h3>
          <div class="max-h-48 space-y-2 overflow-y-auto scrollbar-slim">
            <?php if ($notes === []): ?>
              <p class="text-sm text-zinc-500">No notes yet.</p>
            <?php endif; ?>
            <?php foreach ($notes as $note): ?>
              <div class="rounded-lg bg-zinc-50 p-3">
                <p class="whitespace-pre-line text-sm text-zinc-800"><?= e((string) $note['content']) ?></p>
                <p class="mt-1 text-[11px] text-zinc-400"><?= e((string) ($note['staff_name'] ?? 'System')) ?> · <?= e(format_date_time((string) $note['created_at'])) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="<?= url('/admin/enquiry-note') ?>" class="mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="enquiry_id" value="<?= e((string) $detail['id']) ?>">
            <label for="enq-note" class="sr-only">New note</label>
            <textarea id="enq-note" name="content" rows="2" required maxlength="5000" placeholder="Add an internal note…"
                      class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
            <button type="submit" class="mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-brand bg-white px-4 text-sm font-semibold text-brand hover:bg-brand/10">
              <?= icon('plus', 'h-4 w-4') ?> Add note
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Existing invoices for this enquiry -->
    <?php if ($invoices !== []): ?>
      <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Invoices for this enquiry</h3>
        <ul class="divide-y divide-zinc-100">
          <?php foreach ($invoices as $inv): ?>
            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
              <span class="font-mono font-medium text-zinc-900"><?= e((string) $inv['number']) ?></span>
              <span class="font-medium"><?= e(format_tsh((int) $inv['total'])) ?></span>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= ['UNPAID' => 'bg-amber-100 text-amber-700', 'PARTIAL' => 'bg-orange-100 text-orange-700', 'PAID' => 'bg-green-100 text-green-700'][$inv['payment_status']] ?? 'bg-zinc-200 text-zinc-700' ?>">
                <?= e((string) $inv['payment_status']) ?>
              </span>
              <a href="<?= e(url('/admin/invoices?highlight=' . rawurlencode((string) $inv['number']))) ?>"
                 class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                <?= icon('external-link', 'h-3.5 w-3.5') ?> View
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Footer: close + generate invoice -->
    <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 sm:flex-row sm:items-start sm:justify-between">
      <a href="<?= e(url('/admin/enquiries' . ($q !== '' || $status !== '' ? '?' . http_build_query(array_filter(['q' => $q, 'status' => $status])) : ''))) ?>"
         class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
        <?= icon('arrow-left', 'h-4 w-4') ?> Close
      </a>

      <details class="w-full rounded-lg border border-brand/30 bg-brand/5 sm:max-w-md">
        <summary class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('file-text', 'h-4 w-4') ?> Generate Invoice
        </summary>
        <form method="post" action="<?= url('/admin/enquiry-invoice') ?>" class="p-4">
          <?= csrf_field() ?>
          <input type="hidden" name="enquiry_id" value="<?= e((string) $detail['id']) ?>">
          <input type="hidden" id="inv-base" value="<?= $priceAtEnquiry ?>">
          <p class="text-sm text-zinc-700">Price at enquiry: <span class="font-semibold text-zinc-900"><?= e(format_tsh($priceAtEnquiry)) ?></span></p>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
              <label for="inv-discount" class="block text-sm font-medium text-zinc-700 mb-1">Discount (TSh)</label>
              <input type="number" id="inv-discount" name="discount" value="0" min="0" step="1" oninput="iacInvoiceTotal()"
                     class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>
            <div>
              <label for="inv-charges" class="block text-sm font-medium text-zinc-700 mb-1">Additional charges (TSh)</label>
              <input type="number" id="inv-charges" name="additionalCharges" value="0" min="0" step="1" oninput="iacInvoiceTotal()"
                     class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>
          </div>
          <p class="mt-3 text-sm text-zinc-700">Total: <span id="inv-total" class="font-bold text-zinc-900"><?= e(format_tsh($priceAtEnquiry)) ?></span></p>
          <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">The enquiry will be marked &ldquo;Invoice Generated&rdquo; automatically.</p>
          <button type="submit" class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
            <?= icon('file-text', 'h-4 w-4') ?> Generate Invoice
          </button>
        </form>
      </details>
    </div>
  </section>
  <?php endif; ?>
</div>

<script>
function iacInvoiceTotal() {
  var base = parseInt(document.getElementById('inv-base').value, 10) || 0;
  var d = parseInt(document.getElementById('inv-discount').value, 10) || 0;
  var c = parseInt(document.getElementById('inv-charges').value, 10) || 0;
  var total = Math.max(base - d, 0) + c;
  document.getElementById('inv-total').textContent =
    'TSh' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
