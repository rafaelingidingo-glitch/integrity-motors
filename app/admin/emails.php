<?php
/**
 * /admin/emails — delivery log for transactional email (ADMIN only).
 * Rows are written by send_email() on every attempt: success=1 means Resend
 * accepted the message; success=0 shows the exact reason (e.g. missing API key).
 * This is how you verify your RESEND_API_KEY works after configuring it.
 */

declare(strict_types=1);

$staff = require_admin();

$admin_title  = 'Email Log';
$admin_active = 'emails';

[$page, $pageSize, $offset] = page_context(30);

$onlyFailed = ($_GET['failed'] ?? '') === '1';

$whereSql  = $onlyFailed ? 'WHERE success = 0' : '';
$countArgs = [];

$total      = (int) db_scalar("SELECT COUNT(*) FROM email_logs $whereSql", $countArgs);
$failed     = (int) db_scalar('SELECT COUNT(*) FROM email_logs WHERE success = 0');
$succeeded  = $total - $failed;
$totalPages = max(1, (int) ceil($total / max(1, $pageSize)));

$rows = db_all(
    "SELECT * FROM email_logs $whereSql ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset",
    $countArgs
);

$TYPE_LABELS = [
    'ENQUIRY_ADMIN'       => 'Enquiry → admin',
    'ENQUIRY_ACK'         => 'Enquiry acknowledgement',
    'CONTACT_ADMIN'       => 'Contact → admin',
    'NEWSLETTER_CONFIRM'  => 'Newsletter confirm',
    'NEWSLETTER_BROADCAST' => 'Newsletter broadcast',
    'PASSWORD_RESET'      => 'Password reset',
];

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <!-- Summary -->
  <div class="grid gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-zinc-200 bg-white p-4">
      <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Total attempts</p>
      <p class="mt-1 text-2xl font-bold tabular-nums"><?= number_format($total) ?></p>
    </div>
    <div class="rounded-xl border border-green-200 bg-green-50 p-4">
      <p class="text-xs font-medium uppercase tracking-wide text-green-700">Delivered (Resend accepted)</p>
      <p class="mt-1 text-2xl font-bold tabular-nums text-green-800"><?= number_format($succeeded) ?></p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
      <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Failed / skipped</p>
      <p class="mt-1 text-2xl font-bold tabular-nums text-amber-800"><?= number_format($failed) ?></p>
    </div>
  </div>

  <?php if ($failed > 0 && !$onlyFailed): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="note">
      <?= icon('alert-triangle', 'mr-1 inline h-4 w-4') ?>
      Some sends failed. The most common reason is a missing or unverified <code class="font-mono">RESEND_API_KEY</code> /
      <code class="font-mono">RESEND_FROM</code> — see <span class="font-mono text-xs">DEPLOY.md §8</span>.
      <a href="<?= e(url('/admin/emails?failed=1')) ?>" class="ml-1 font-semibold underline">Show only failures</a>
    </div>
  <?php endif; ?>

  <?php if ($onlyFailed): ?>
    <a href="<?= e(url('/admin/emails')) ?>" class="inline-flex min-h-9 items-center gap-1.5 self-start text-sm font-medium text-brand hover:underline">
      <?= icon('arrow-left', 'h-4 w-4') ?> Show all attempts
    </a>
  <?php endif; ?>

  <!-- List -->
  <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white scrollbar-slim">
    <table class="w-full min-w-[44rem] text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Time</th>
            <th class="px-4 py-3 font-semibold">Type</th>
            <th class="px-4 py-3 font-semibold">Recipient</th>
            <th class="px-4 py-3 font-semibold">Subject</th>
            <th class="px-4 py-3 font-semibold">Result</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">
              No email attempts recorded<?= $onlyFailed ? ' — nothing has failed' : ' yet' ?>.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php $ok = (int) $row['success'] === 1; ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_time((string) $row['created_at'])) ?></td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-700"><?= e($TYPE_LABELS[(string) $row['type']] ?? (string) $row['type']) ?></td>
              <td class="px-4 py-3 break-words text-zinc-700"><?= e((string) $row['recipient']) ?></td>
              <td class="px-4 py-3 max-w-[14rem] break-words text-zinc-600" title="<?= e((string) $row['subject']) ?>"><?= e((string) $row['subject']) ?></td>
              <td class="px-4 py-3">
                <?php if ($ok): ?>
                  <span class="inline-flex items-center whitespace-nowrap rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Sent</span>
                <?php else: ?>
                  <span class="inline-flex items-center whitespace-nowrap rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Failed</span>
                  <?php if ((string) ($row['error'] ?? '') !== ''): ?>
                    <p class="mt-1 max-w-[14rem] break-words text-[11px] leading-snug text-zinc-500"><?= e((string) $row['error']) ?></p>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
  </div>

  <!-- Pagination -->
  <div class="flex items-center justify-between text-sm text-zinc-600">
    <p>Page <?= $page ?> of <?= $totalPages ?> · <?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></p>
    <div class="flex items-center gap-2">
      <?php $base = $onlyFailed ? '/admin/emails?failed=1&page=' : '/admin/emails?page='; ?>
      <?php if ($page > 1): ?>
        <a href="<?= e(url($base . ($page - 1))) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</span>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e(url($base . ($page + 1))) ?>"
           class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-medium hover:bg-zinc-100">Next <?= icon('chevron-right', 'h-4 w-4') ?></a>
      <?php else: ?>
        <span class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 font-medium text-zinc-400">Next <?= icon('chevron-right', 'h-4 w-4') ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
