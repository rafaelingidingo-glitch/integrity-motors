<?php
/**
 * /admin/forgot-password — request a password reset link.
 * Anti-enumeration: the success flash is identical whether or not the email
 * exists. Rate limit 5 / 10 min per IP (exceeded → same generic redirect).
 */

declare(strict_types=1);

if (request_method() === 'POST') {
    csrf_verify();

    [$email] = v_email('email', $_POST);
    $allowed = rate_limit('auth:forgot', 5, 600_000);

    if ($email !== null && $allowed) {
        $user = db_one('SELECT id, email, name FROM staff_users WHERE email = ? AND is_active = 1', [$email]);
        if ($user !== null) {
            $token = random_hex(24); // 48 hex chars, mirrors the Next.js reset flow
            IacDb::transaction(function () use ($user, $token): void {
                db_exec('DELETE FROM password_reset_tokens WHERE staff_id = ? AND used_at IS NULL', [$user['id']]);
                db_exec(
                    'INSERT INTO password_reset_tokens (id, token, staff_id, expires_at, used_at, created_at) VALUES (?, ?, ?, ?, NULL, ?)',
                    [new_id(), $token, $user['id'], date('Y-m-d H:i:s', time() + 3600), now_eat()]
                );
            });
            send_email(
                'PASSWORD_RESET',
                (string) $user['email'],
                'Reset your Integrity Autocars password',
                email_password_reset((string) $user['name'], app_url('/admin/reset-password?token=' . $token))
            );
            audit_log($user['id'], (string) $user['name'], 'PASSWORD_RESET_REQUESTED', 'StaffUser', $user['id'], 'Reset link requested for ' . $email);
        }
    }

    flash('ok', "If an account exists for that email, a reset link has been sent. Check your inbox.");
    redirect('/admin/forgot-password');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Forgot Password | Integrity Autocars Admin</title>
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

        <h1 class="mt-6 text-xl font-bold text-zinc-900">Forgot your password?</h1>
        <p class="mt-1 text-sm text-zinc-500">Enter your staff email and we'll send you a reset link.</p>

        <div class="mt-5">
          <?php render_flashes(); ?>
        </div>

        <form method="post" action="<?= url('/admin/forgot-password') ?>" class="mt-1 space-y-4" novalidate>
          <?= csrf_field() ?>
          <div>
            <label for="forgot-email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
            <input id="forgot-email" name="email" type="email" autocomplete="email" required
                   placeholder="you@integrityautocars.co.tz"
                   class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          </div>

          <button type="submit"
                  class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
            Send reset link
          </button>
        </form>

        <p class="mt-6 border-t border-zinc-200 pt-4 text-center text-sm">
          <a href="<?= url('/admin/login') ?>" class="font-medium text-brand hover:underline">Back to sign in</a>
        </p>
      </div>
    </div>
  </div>

  <script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
