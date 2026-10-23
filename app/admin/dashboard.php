<?php
/**
 * /admin — dashboard: greeting, quick actions, 11 KPI cards, recent
 * enquiries and recent audit activity. Mirrors the original SPA admin home.
 */

declare(strict_types=1);

$staff        = require_staff();
$admin_title  = 'Dashboard';
$admin_active = 'dashboard';

// ---------------------------------------------------------------- helpers
if (!function_exists('enquiry_status_label')) {
    function enquiry_status_label(string $status): string
    {
        return match ($status) {
            'NEW'                => 'New',
            'CONTACTED'          => 'Contacted',
            'OFFER_SENT'         => 'Offer Sent',
            'INVOICE_GENERATED'  => 'Invoice Generated',
            'CONFIRMED'          => 'Confirmed',
            'CLOSED'             => 'Closed',
            'NOT_PROCEEDING'     => 'Not Proceeding',
            default              => $status,
        };
    }
}

if (!function_exists('enquiry_status_badge')) {
    function enquiry_status_badge(string $status): string
    {
        $cls = match ($status) {
            'NEW'               => 'bg-amber-100 text-amber-700',
            'CONTACTED'         => 'bg-zinc-100 text-zinc-600',
            'OFFER_SENT'        => 'bg-orange-100 text-orange-700',
            'INVOICE_GENERATED' => 'bg-red-100 text-red-700',
            'CONFIRMED'         => 'bg-green-100 text-green-700',
            'CLOSED'            => 'bg-zinc-200 text-zinc-700',
            'NOT_PROCEEDING'    => 'bg-zinc-100 text-zinc-500',
            default             => 'bg-zinc-100 text-zinc-600',
        };
        return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">'
            . e(enquiry_status_label($status)) . '</span>';
    }
}

// ---------------------------------------------------------------- KPI data
// One pass over the vehicles table instead of four separate COUNT queries.
$vehicleCounts = db_one(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN published = 0 THEN 1 ELSE 0 END), 0) AS unpublished,
            COALESCE(SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END), 0) AS available,
            COALESCE(SUM(CASE WHEN status = 'SOLD' THEN 1 ELSE 0 END), 0) AS sold
     FROM vehicles"
) ?? ['total' => 0, 'unpublished' => 0, 'available' => 0, 'sold' => 0];
$totalVehicles = (int) $vehicleCounts['total'];
$unpublished   = (int) $vehicleCounts['unpublished'];
$available     = (int) $vehicleCounts['available'];
$sold          = (int) $vehicleCounts['sold'];

// All remaining counters in ONE round trip (scalar subqueries are independent;
// each is the same query that used to run separately). $today is bound.
$kpi = db_one(
    "SELECT
        (SELECT COUNT(*) FROM enquiries WHERE status = 'NEW') AS new_enquiries,
        (SELECT COUNT(*) FROM enquiries WHERE status IN ('CONTACTED', 'OFFER_SENT', 'INVOICE_GENERATED')) AS pending_follows,
        (SELECT COUNT(*) FROM contact_messages WHERE status = 'NEW') AS new_messages,
        (SELECT COALESCE(visits, 0)  FROM visit_daily WHERE date = ?) AS visits_today,
        (SELECT COALESCE(uniques, 0) FROM visit_daily WHERE date = ?) AS uniques_today,
        (SELECT COALESCE(SUM(visits), 0) FROM visit_daily) AS total_visits,
        (SELECT COUNT(*) FROM invoices) AS total_invoices,
        (SELECT COUNT(*) FROM invoices WHERE payment_status IN ('UNPAID', 'PARTIAL')) AS unsettled,
        (SELECT COUNT(*) FROM offers WHERE status = 'ACTIVE') AS active_offers,
        (SELECT COUNT(*) FROM subscribers WHERE is_confirmed = 1) AS subscribers",
    [today_eat(), today_eat()]
) ?? [
    'new_enquiries' => 0, 'pending_follows' => 0, 'new_messages' => 0,
    'visits_today' => 0, 'uniques_today' => 0, 'total_visits' => 0,
    'total_invoices' => 0, 'unsettled' => 0, 'active_offers' => 0, 'subscribers' => 0,
];
$newEnquiries   = (int) $kpi['new_enquiries'];
$pendingFollows = (int) $kpi['pending_follows'];
$newMessages    = (int) $kpi['new_messages'];
$visitorsToday  = (int) $kpi['visits_today'];
$uniquesToday   = (int) $kpi['uniques_today'];
$totalVisits    = (int) $kpi['total_visits'];
$totalInvoices  = (int) $kpi['total_invoices'];
$unsettled      = (int) $kpi['unsettled'];
$activeOffers   = (int) $kpi['active_offers'];
$subscribers    = (int) $kpi['subscribers'];

$recentEnquiries = db_all('SELECT id, full_name, status, created_at FROM enquiries ORDER BY created_at DESC, id ASC LIMIT 5');
$recentActivity  = db_all('SELECT id, action, staff_name, entity, detail, created_at FROM audit_logs ORDER BY created_at DESC, id ASC LIMIT 8');

$firstName = trim(explode(' ', (string) $staff['name'])[0] ?? '') ?: 'staff';
$isAdmin   = is_admin();

require APP_ROOT . '/app/views/layout/admin-header.php';
?>

<p class="text-xl font-bold sm:text-2xl">Karibu, <?= e($firstName) ?>!</p>
<p class="mt-0.5 text-sm text-zinc-500">Here is what is happening at Integrity Autocars today.</p>

<div class="mt-4 flex flex-wrap gap-2">
  <?php if ($isAdmin): ?>
    <a href="<?= url('/admin/vehicle-edit') ?>"
       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('plus', 'h-4 w-4') ?> Add Vehicle
    </a>
  <?php endif; ?>
  <a href="<?= url('/admin/enquiries') ?>"
     class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
    <?= icon('inbox', 'h-4 w-4') ?> View Enquiries
  </a>
  <a href="<?= url('/admin/invoices') ?>"
     class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
    <?= icon('file-text', 'h-4 w-4') ?> Generate Invoice
  </a>
</div>

<!-- KPI cards -->
<section class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4" aria-label="Key metrics">
  <?php
  $cards = [
      ['Total Vehicles',    number_format($totalVehicles), "{$unpublished} unpublished",              'package'],
      ['Available',         number_format($available),     null,                                      'car'],
      ['Sold',              number_format($sold),          null,                                      'check'],
      ['New Enquiries',     number_format($newEnquiries),  null,                                      'inbox'],
      ['Pending Follow-ups', number_format($pendingFollows), null,                                    'clock'],
      ['New Messages',      number_format($newMessages),   null,                                      'mail'],
      ['Visitors Today',    number_format($visitorsToday), "{$uniquesToday} unique",                  'eye'],
      ['Total Visits',      number_format($totalVisits),   null,                                      'globe'],
      ['Invoices',          number_format($totalInvoices), $unsettled > 0 ? "{$unsettled} unpaid/partial" : 'all settled', 'file-text'],
      ['Active Offers',     number_format($activeOffers),  null,                                      'ticket'],
      ['Subscribers',       number_format($subscribers),   'confirmed',                               'users'],
  ];
  foreach ($cards as [$label, $value, $sub, $icn]): ?>
    <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6">
      <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500"><?= e($label) ?></p>
        <?= icon($icn, 'h-4 w-4 shrink-0 text-zinc-300') ?>
      </div>
      <p class="mt-2 text-2xl font-bold tabular-nums sm:text-3xl"><?= e($value) ?></p>
      <p class="mt-1 text-xs text-zinc-400"><?= e($sub ?? '') ?></p>
    </div>
  <?php endforeach; ?>
</section>

<div class="mt-6 grid gap-4 lg:grid-cols-2 lg:gap-6">

  <!-- Recent enquiries -->
  <section class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-labelledby="recent-enquiries-h">
    <div class="flex items-center justify-between gap-3">
      <h2 id="recent-enquiries-h" class="text-sm font-semibold text-zinc-900">Recent Enquiries</h2>
      <a href="<?= url('/admin/enquiries') ?>" class="text-xs font-semibold text-brand hover:underline">View all</a>
    </div>
    <?php if ($recentEnquiries === []): ?>
      <p class="mt-4 text-sm text-zinc-400">No enquiries yet.</p>
    <?php else: ?>
      <ul class="mt-4 divide-y divide-zinc-100">
        <?php foreach ($recentEnquiries as $enq): ?>
          <li class="flex items-center justify-between gap-3 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-zinc-800"><?= e((string) $enq['full_name']) ?></p>
              <p class="text-xs text-zinc-400"><?= e(format_date_time((string) $enq['created_at'])) ?></p>
            </div>
            <?= enquiry_status_badge((string) $enq['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- Recent activity -->
  <section class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-labelledby="recent-activity-h">
    <div class="flex items-center justify-between gap-3">
      <h2 id="recent-activity-h" class="text-sm font-semibold text-zinc-900">Recent Activity</h2>
      <a href="<?= url('/admin/audit') ?>" class="text-xs font-semibold text-brand hover:underline">Full log</a>
    </div>
    <?php if ($recentActivity === []): ?>
      <p class="mt-4 text-sm text-zinc-400">Nothing logged yet.</p>
    <?php else: ?>
      <ul class="mt-4 divide-y divide-zinc-100">
        <?php foreach ($recentActivity as $log): ?>
          <li class="flex items-start justify-between gap-3 py-2.5">
            <div class="min-w-0">
              <p class="flex flex-wrap items-center gap-2">
                <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-zinc-700"><?= e((string) $log['action']) ?></span>
                <span class="text-sm font-medium text-zinc-800"><?= e((string) ($log['staff_name'] ?? 'System')) ?></span>
              </p>
              <p class="mt-0.5 truncate text-xs text-zinc-400">
                <?= e((string) $log['entity']) ?><?= isset($log['detail']) && $log['detail'] !== null && $log['detail'] !== '' ? ' — ' . e((string) $log['detail']) : '' ?>
              </p>
            </div>
            <span class="shrink-0 text-xs text-zinc-400"><?= e(format_date_time((string) $log['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
