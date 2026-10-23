<?php
/**
 * Admin — Contact messages list + detail card (?id=).
 * Status updates live in message-save.php.
 */

$staff = require_staff();

$admin_title  = 'Messages';
$admin_active = 'messages';

$MSG_META = [
    'NEW'         => ['New',         'bg-amber-100 text-amber-700'],
    'IN_PROGRESS' => ['In Progress', 'bg-teal-100 text-teal-700'],
    'RESOLVED'    => ['Resolved',    'bg-green-100 text-green-700'],
];

$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, MESSAGE_STATUSES, true)) {
    $status = '';
}

$where  = [];
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR subject LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = db_all("SELECT * FROM contact_messages $whereSql ORDER BY created_at DESC LIMIT 200", $params);

$detailId = trim((string) ($_GET['id'] ?? ''));
$detail   = $detailId !== '' ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$detailId]) : null;

$msg_list_url = function (string $statusVal) use ($q): string {
    $qs = [];
    if ($q !== '') {
        $qs['q'] = $q;
    }
    if ($statusVal !== '') {
        $qs['status'] = $statusVal;
    }
    return url('/admin/messages' . ($qs === [] ? '' : '?' . http_build_query($qs)));
};

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <!-- Toolbar -->
  <form method="get" action="<?= url('/admin/messages') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center">
    <div class="relative flex-1">
      <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400') ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email or subject…"
             aria-label="Search messages"
             class="h-11 w-full rounded-lg border border-zinc-300 bg-white pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
    </div>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      Search
    </button>
  </form>

  <!-- Status chips -->
  <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by status">
    <a href="<?= e($msg_list_url('')) ?>"
       class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold <?= $status === '' ? 'bg-brand border-brand text-white' : 'border-transparent bg-zinc-200 text-zinc-700' ?>">
      All
    </a>
    <?php foreach (MESSAGE_STATUSES as $st): ?>
      <a href="<?= e($msg_list_url($st)) ?>"
         class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold <?= $status === $st ? 'bg-brand border-brand text-white' : 'border-transparent ' . $MSG_META[$st][1] ?>">
        <?= e($MSG_META[$st][0]) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- List -->
  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Name</th>
            <th class="px-4 py-3 font-semibold">Subject</th>
            <th class="px-4 py-3 font-semibold">Contact</th>
            <th class="px-4 py-3 font-semibold">Date</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php if ($rows === []): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No messages found.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php [$mLabel, $mChip] = $MSG_META[$row['status']] ?? [$row['status'], 'bg-zinc-200 text-zinc-700']; ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3 font-medium text-zinc-900"><?= e((string) $row['name']) ?></td>
              <td class="px-4 py-3 text-zinc-700"><?= e((string) $row['subject']) ?></td>
              <td class="px-4 py-3 text-zinc-600">
                <?php if ($row['phone'] !== null && $row['phone'] !== ''): ?>
                  <?= e((string) $row['phone']) ?>
                <?php elseif ($row['email'] !== null && $row['email'] !== ''): ?>
                  <?= e((string) $row['email']) ?>
                <?php else: ?>
                  <span class="text-zinc-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_time((string) $row['created_at'])) ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $mChip ?>"><?= e($mLabel) ?></span>
              </td>
              <td class="px-4 py-3 text-right">
                <a href="<?= e(url('/admin/messages?id=' . rawurlencode((string) $row['id']))) ?>"
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

  <?php if ($detailId !== '' && $detail === null): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Message not found.</div>
  <?php endif; ?>

  <?php if ($detail !== null):
    [$dLabel, $dChip] = $MSG_META[$detail['status']] ?? [$detail['status'], 'bg-zinc-200 text-zinc-700'];
  ?>
  <!-- ============================ Detail card ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Message detail">
    <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex flex-wrap items-center gap-3">
          <h2 class="text-lg font-bold text-zinc-900"><?= e((string) $detail['name']) ?></h2>
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $dChip ?>"><?= e($dLabel) ?></span>
        </div>
        <p class="mt-1 text-sm font-medium text-zinc-700"><?= e((string) $detail['subject']) ?></p>
        <p class="mt-0.5 text-sm text-zinc-500"><?= e(format_date_time((string) $detail['created_at'])) ?></p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ($detail['phone'] !== null && $detail['phone'] !== ''): ?>
          <a href="<?= e(tel_link((string) $detail['phone'])) ?>"
             class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
            <?= icon('phone', 'h-4 w-4') ?> Call
          </a>
        <?php endif; ?>
        <?php if ($detail['email'] !== null && $detail['email'] !== ''): ?>
          <a href="<?= e('mailto:' . (string) $detail['email']) ?>"
             class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
            <?= icon('mail', 'h-4 w-4') ?> Email
          </a>
        <?php endif; ?>
      </div>
    </div>

    <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">Message</h3>
    <p class="whitespace-pre-line rounded-lg bg-zinc-50 p-4 text-sm text-zinc-800"><?= e((string) $detail['message']) ?></p>

    <form method="post" action="<?= url('/admin/message-save') ?>" class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 sm:flex-row sm:items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) $detail['id']) ?>">
      <div class="sm:w-56">
        <label for="msg-status" class="block text-sm font-medium text-zinc-700 mb-1">Status</label>
        <select id="msg-status" name="status" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <?php foreach (MESSAGE_STATUSES as $st): ?>
            <option value="<?= e($st) ?>" <?= $detail['status'] === $st ? 'selected' : '' ?>><?= e($MSG_META[$st][0]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
        <?= icon('check', 'h-4 w-4') ?> Save
      </button>
    </form>
  </section>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
