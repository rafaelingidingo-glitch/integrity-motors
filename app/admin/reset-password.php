<?php
/**
 * /admin/reset-password?token=… — consume a reset token and set a new password.
 * Tokens: 48-hex, single use, expire after 1 hour.
 */

declare(strict_types=1);

const RESET_TOKEN_RE = '/^[0-9a-f]{48}$/';

if (request_method() === 'POST') {
    csrf_verify();

    $token = (string) ($_POST['token'] ?? '');
    if (!preg_match(RESET_TOKEN_RE, $token)) {
        flash('error', 'This password reset link is invalid or has expired.');
        redirect('/admin/reset-password');
    }

    $row = db_one(
        'SELECT t.*, s.email, s.name, s.is_active
         FROM password_reset_tokens t
         JOIN staff_users s ON s.id = t.staff_id
         WHERE t.token = ?',
        [$token]
    );

    if (
        $row === null
        || $row['used_at'] !== null
        || $row['expires_at'] < now_eat()
        || (int) $row['is_active'] !== 1
    ) {
        flash('error', 'This password reset link is invalid or has expired.');
        redirect('/admin/reset-password');
    }

    [$password] = v_str('password', $_POST, 8, 200);
    $confirm    = (string) ($_POST['password_confirm'] ?? '');

    if ($password === null || $password !== $confirm) {
        flash('error', $password === null
            ? 'Password must be at least 8 characters.'
            : 'Passwords do not match.');
        redirect('/admin/reset-password?token=' . urlencode($token));
    }

    IacDb::transaction(function () use ($row, $password, $token): void {
        db_exec(
            'UPDATE staff_users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT), now_eat(), $row['staff_id']]
        );
        db_exec('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?', [now_eat(), $row['id']]);
        db_exec('DELETE FROM password_reset_tokens WHERE staff_id = ? AND used_at IS NULL', [$row['staff_id']]);
    });

    audit_log(
        (string) $row['staff_id'],
        (string) $row['name'],
        'PASSWORD_RESET_COMPLETED',
        'StaffUser',
        (string) $row['staff_id'],
        'Password reset completed for ' . $row['email']
    );

    flash('ok', 'Password updated — you can sign in now.');
    redirect('/admin/login');
}

// ---------------------------------------------------------------------------
// GET — validate the token format, then render either the form or the error
// state (dead tokens are fully rejected on POST).
// ---------------------------------------------------------------------------
$token       = trim((string) ($_GET['token'] ?? ''));
$tokenValid  = preg_match(RESET_TOKEN_RE, $token) === 1;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Reset Password | Integrity Autocars Admin</title>
  <link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
  <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
  <?= theme_boot_script() ?>
</head>
<body class="min-h-screen bg-zinc-950 antialiased">
  <div class="relative flex min-h-screen items-center justify-center p-4">
    <div class="absolute right-4 top-4 z-10"><?= theme_toggle_button() ?></div>
    <img src="<?= url('/assets/img/login-bg.png') ?>" alt="" aria-hidden="true"
         class="absolute inset-0 h-full w-full object-cover">
    <div class="absolute inset-0 bg-black/55" aria-hidden="true"></div>

    <div class="relative w-full max-w-sm">
      <div class="rounded-2xl bg-white/95 p-6 shadow-2xl backdrop-blur sm:p-8">
        <img src="<?= url('/assets/img/logo-wide.png') ?>" alt="Integrity Autocars"
             class="logo-light mx-auto h-12 w-auto" width="192" height="48">
        <img src="<?= url('/assets/img/logo-wide-dark.png') ?>" alt="" aria-hidden="true"
             class="logo-dark mx-auto hidden h-12 w-auto" width="192" height="48">

        <div class="mt-5">
          <?php render_flashes(); ?>
        </div>

        <?php if (!$tokenValid): ?>
          <div class="mt-4 flex flex-col items-center text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-brand">
              <?= icon('alert-triangle', 'h-6 w-6') ?>
            </span>
            <h1 class="mt-4 text-xl font-bold text-zinc-900">Link no longer valid</h1>
            <p class="mt-2 text-sm text-zinc-500">This password reset link is invalid or has expired.</p>
            <a href="<?= url('/admin/forgot-password') ?>"
               class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
              Request a new link
            </a>
          </div>
        <?php else: ?>
          <h1 class="mt-6 text-xl font-bold text-zinc-900">Choose a new password</h1>
          <p class="mt-1 text-sm text-zinc-500">Minimum 8 characters. You'll use it to sign in to the admin area.</p>

          <form method="post" action="<?= url('/admin/reset-password') ?>" class="mt-5 space-y-4" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div>
              <label for="reset-password" class="block text-sm font-medium text-zinc-700 mb-1">New password</label>
              <input id="reset-password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="200"
                     placeholder="••••••••"
                     class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>
            <div>
              <label for="reset-confirm" class="block text-sm font-medium text-zinc-700 mb-1">Confirm password</label>
              <input id="reset-confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="8" maxlength="200"
                     placeholder="••••••••"
                     class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>

            <button type="submit"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
              Reset password
            </button>
          </form>
        <?php endif; ?>

        <p class="mt-6 border-t border-zinc-200 pt-4 text-center text-sm">
          <a href="<?= url('/admin/login') ?>" class="font-medium text-brand hover:underline">Back to sign in</a>
        </p>
      </div>
    </div>
  </div>

  <script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
