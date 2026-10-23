<?php
/**
 * /admin/login — standalone full-screen sign-in page (no admin shell).
 * POST handling first (PRG), then GET rendering.
 */

declare(strict_types=1);

if (request_method() === 'POST') {
    csrf_verify();
    $err = attempt_login(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''));
    if ($err === null) {
        redirect('/admin');
    }
    flash('error', $err);
    redirect('/admin/login');
}

if (current_staff() !== null) {
    redirect('/admin');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign In | Integrity Autocars Admin</title>
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

    <div class="relative w-full max-w-md">
      <div class="rounded-2xl bg-white/95 p-6 shadow-2xl backdrop-blur sm:p-8">
        <img src="<?= url('/assets/img/logo-wide.png') ?>" alt="Integrity Autocars"
             class="logo-light mx-auto h-12 w-auto" width="192" height="48">
        <img src="<?= url('/assets/img/logo-wide-dark.png') ?>" alt="" aria-hidden="true"
             class="logo-dark mx-auto hidden h-12 w-auto" width="192" height="48">

        <h1 class="mt-6 text-2xl font-bold text-zinc-900">Welcome Back!</h1>
        <p class="mt-1 text-sm text-zinc-500">Sign in to manage vehicles, enquiries, invoices and offers.</p>

        <div class="mt-5">
          <?php render_flashes(); ?>
        </div>

        <form method="post" action="<?= url('/admin/login') ?>" class="mt-1 space-y-4" novalidate>
          <?= csrf_field() ?>
          <div>
            <label for="login-email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
            <input id="login-email" name="email" type="email" autocomplete="email" required
                   placeholder="you@integrityautocars.co.tz"
                   class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          </div>
          <div>
            <label for="login-password" class="block text-sm font-medium text-zinc-700 mb-1">Password</label>
            <div class="relative">
              <input id="login-password" name="password" type="password" autocomplete="current-password" required
                     placeholder="••••••••"
                     class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 pr-11 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
              <button type="button" data-toggle-password="login-password"
                      class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-lg text-zinc-400 hover:text-zinc-600"
                      aria-label="Show password">
                <span data-icon-eye><?= icon('eye') ?></span>
                <span data-icon-eye-off class="hidden"><?= icon('eye-off') ?></span>
              </button>
            </div>
          </div>

          <div class="flex justify-end">
            <a href="<?= url('/admin/forgot-password') ?>" class="text-sm font-medium text-brand hover:underline">Forgot password?</a>
          </div>

          <button type="submit"
                  class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark">
            Sign In
          </button>
        </form>

        <p class="mt-6 border-t border-zinc-200 pt-4 text-center text-xs text-zinc-500">
          Authorized staff only. All activity is recorded in the audit log.
        </p>
      </div>
    </div>
  </div>

  <script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
