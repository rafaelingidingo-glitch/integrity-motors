<?php
/**
 * Admin — Activity log (ADMIN only), newest first, 20 per page.
 */

$staff = require_admin();

$admin_title  = 'Activity Log';
$admin_active = 'audit';

[$page, $pageSize, $offset] = page_context(20);

$total      = (int) db_scalar('SELECT COUNT(*) FROM audit_logs');
$totalPages = max(1, (int) ceil($total / max(1, $pageSize)));

$rows = db_all("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset");

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Time</th>
            <th class="px-4 py-3 font-semibold">Staff</th>
            <th class="px-4 py-3 font-semibold">Action</th>
            <th class="px-4 py-3 font-semibold">Entity</th>
            <th class="px-4 py-3 font-semibold">Detail</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">No activity recorded yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_time((string) $row['created_at'])) ?></td>
              <td class="px-4 py-3 text-zinc-700"><?= $row['staff_name'] !== null && $row['staff_name'] !== '' ? e((string) $row['staff_name']) : '<span class="text-zinc-400">System</span>' ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded px-2 py-0.5 font-mono text-xs font-semibold bg-zinc-100 text-zinc-700"><?= e((string) $row['action']) ?></span>
              </td>
              <td class="px-4 py-3 text-zinc-600"><?= e((string) $row['entity']) ?></td>
              <td class="px-4 py-3 text-zinc-600"><?= $row['detail'] !== null && $row['detail'] !== '' ? e((string) $row['detail']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div class="flex items-center justify-between text-sm text-zinc-600">
    <p>Page <?= $page ?> of <?= $totalPages ?> · <?= $total ?> entries</p>
    <div class="flex items-center gap-2">
      <?php if ($page > 1): ?>
        <a href="<?= e(url('/admin/audit?page=' . ($page - 1))) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</span>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e(url('/admin/audit?page=' . ($page + 1))) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100">Next <?= icon('chevron-right', 'h-4 w-4') ?></a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400">Next <?= icon('chevron-right', 'h-4 w-4') ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
