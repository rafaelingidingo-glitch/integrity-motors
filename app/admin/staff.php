<?php
/**
 * Admin — Staff accounts (ADMIN only).
 * Create/update/toggle actions live in staff-save.php.
 */

$staff = require_admin();

$admin_title  = 'Staff';
$admin_active = 'staff';

$accounts = db_all(
    'SELECT su.*, (SELECT COUNT(*) FROM enquiries e WHERE e.assigned_to_id = su.id) AS enquiry_count
     FROM staff_users su
     ORDER BY su.created_at ASC'
);

$editId = trim((string) ($_GET['edit'] ?? ''));
$editUser = $editId !== '' ? db_one('SELECT * FROM staff_users WHERE id = ?', [$editId]) : null;
if ($editId !== '' && $editUser === null) {
    flash('error', 'Staff account not found.');
    redirect('/admin/staff');
}

$showNew = ($_GET['new'] ?? '') === '1';

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<div class="flex flex-col gap-6">

  <!-- Toolbar -->
  <div class="flex items-center justify-between gap-3">
    <p class="text-sm text-zinc-600">Team accounts. Disabled accounts cannot sign in.</p>
    <a href="<?= e(url('/admin/staff?new=1')) ?>"
       class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('plus', 'h-4 w-4') ?> New staff
    </a>
  </div>

  <?php if ($showNew): ?>
  <!-- ============================ New staff form ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="New staff">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-zinc-900">New staff account</h2>
      <a href="<?= e(url('/admin/staff')) ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
        <?= icon('x', 'h-3.5 w-3.5') ?> Close
      </a>
    </div>
    <form method="post" action="<?= url('/admin/staff-save') ?>" class="mt-4 grid gap-4 lg:grid-cols-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div>
        <label for="s-name" class="block text-sm font-medium text-zinc-700 mb-1">Name <span class="text-brand">*</span></label>
        <input id="s-name" name="name" type="text" required maxlength="120"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="s-email" class="block text-sm font-medium text-zinc-700 mb-1">Email <span class="text-brand">*</span></label>
        <input id="s-email" name="email" type="email" required maxlength="254"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="s-password" class="block text-sm font-medium text-zinc-700 mb-1">Password <span class="text-brand">*</span></label>
        <input id="s-password" name="password" type="password" required minlength="8" autocomplete="new-password"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        <p class="mt-1 text-xs text-zinc-500">Minimum 8 characters.</p>
      </div>
      <div>
        <label for="s-role" class="block text-sm font-medium text-zinc-700 mb-1">Role</label>
        <select id="s-role" name="role" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="STAFF" selected>Staff</option>
          <option value="ADMIN">Admin</option>
        </select>
      </div>
      <div class="lg:col-span-2 flex justify-end">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('users', 'h-4 w-4') ?> Create account
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($editUser !== null): ?>
  <!-- ============================ Edit staff form ============================ -->
  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Edit staff">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold text-zinc-900">Edit <?= e((string) $editUser['name']) ?></h2>
      <a href="<?= e(url('/admin/staff')) ?>" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
        <?= icon('x', 'h-3.5 w-3.5') ?> Close
      </a>
    </div>
    <form method="post" action="<?= url('/admin/staff-save') ?>" class="mt-4 grid gap-4 lg:grid-cols-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="staff_id" value="<?= e((string) $editUser['id']) ?>">
      <div>
        <label for="se-name" class="block text-sm font-medium text-zinc-700 mb-1">Name <span class="text-brand">*</span></label>
        <input id="se-name" name="name" type="text" required maxlength="120" value="<?= e((string) $editUser['name']) ?>"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div>
        <label for="se-email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
        <input id="se-email" type="email" value="<?= e((string) $editUser['email']) ?>" disabled
               class="h-11 w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-500">
      </div>
      <div>
        <label for="se-role" class="block text-sm font-medium text-zinc-700 mb-1">Role</label>
        <select id="se-role" name="role" class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value="STAFF" <?= $editUser['role'] === 'STAFF' ? 'selected' : '' ?>>Staff</option>
          <option value="ADMIN" <?= $editUser['role'] === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div>
        <label for="se-password" class="block text-sm font-medium text-zinc-700 mb-1">New password</label>
        <input id="se-password" name="newPassword" type="password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep the current password"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>
      <div class="lg:col-span-2 flex justify-end">
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
          <?= icon('check', 'h-4 w-4') ?> Save changes
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <!-- List -->
  <div class="rounded-xl border border-zinc-200 bg-white">
    <div class="overflow-x-auto scrollbar-slim">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Name</th>
            <th class="px-4 py-3 font-semibold">Email</th>
            <th class="px-4 py-3 font-semibold">Role</th>
            <th class="px-4 py-3 font-semibold">Active</th>
            <th class="px-4 py-3 font-semibold">Assigned enquiries</th>
            <th class="px-4 py-3 font-semibold">Since</th>
            <th class="px-4 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
          <?php foreach ($accounts as $acc):
            $isSelf = $acc['id'] === $staff['id'];
          ?>
            <tr class="hover:bg-zinc-50">
              <td class="px-4 py-3 font-medium text-zinc-900">
                <?= e((string) $acc['name']) ?>
                <?php if ($isSelf): ?><span class="ml-1 text-xs text-zinc-400">(you)</span><?php endif; ?>
              </td>
              <td class="px-4 py-3 text-zinc-600"><?= e((string) $acc['email']) ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $acc['role'] === 'ADMIN' ? 'bg-red-100 text-red-700' : 'bg-zinc-200 text-zinc-700' ?>">
                  <?= e((string) $acc['role']) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <form method="post" action="<?= url('/admin/staff-save') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="staff_id" value="<?= e((string) $acc['id']) ?>">
                  <button type="submit" <?= $isSelf ? 'disabled title="You cannot deactivate your own account."' : '' ?>
                          class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= (int) $acc['is_active'] === 1 ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-zinc-200 text-zinc-500 hover:bg-zinc-300' ?> <?= $isSelf ? 'cursor-not-allowed opacity-60' : '' ?>">
                    <?= icon((int) $acc['is_active'] === 1 ? 'check' : 'x', 'h-3.5 w-3.5') ?>
                    <?= (int) $acc['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </td>
              <td class="px-4 py-3 text-zinc-600"><?= (int) $acc['enquiry_count'] ?></td>
              <td class="px-4 py-3 whitespace-nowrap text-zinc-600"><?= e(format_date_medium((string) $acc['created_at'])) ?></td>
              <td class="px-4 py-3 text-right">
                <a href="<?= e(url('/admin/staff?edit=' . rawurlencode((string) $acc['id']))) ?>"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                  <?= icon('pencil', 'h-3.5 w-3.5') ?> Edit
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
