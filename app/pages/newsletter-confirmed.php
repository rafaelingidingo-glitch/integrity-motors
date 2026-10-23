<?php
/**
 * GET /newsletter-confirmed?token=<64 hex> — double opt-in confirmation.
 * One-shot: the token is cleared after a successful confirmation.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'home';

$token  = trim((string) ($_GET['token'] ?? ''));
$ok     = false;
if (preg_match('/^[0-9a-f]{64}$/', $token) === 1) {
    $subscriber = db_one('SELECT id, is_confirmed FROM subscribers WHERE confirm_token = ?', [$token]);
    if ($subscriber !== null) {
        db_exec(
            'UPDATE subscribers SET is_confirmed = 1, confirm_token = NULL WHERE id = ?',
            [$subscriber['id']]
        );
        $ok = true;
    }
}

$pageTitle       = t('meta_newsletter_title');
$metaDescription = t('meta_newsletter_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/newsletter-confirmed')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('nl_aria')) ?>" class="mx-auto flex w-full max-w-xl px-4 py-20">
    <div class="flex w-full flex-col items-center gap-4 rounded-xl border border-zinc-200 bg-white p-10 text-center shadow-sm">
      <?php if ($ok): ?>
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700" aria-hidden="true"><?= icon('check', 'h-8 w-8') ?></span>
        <h1 class="text-2xl font-bold"><?= e(t('nl_ok_t')) ?></h1>
        <p class="text-sm text-zinc-500"><?= e(t('nl_ok_s')) ?></p>
        <a href="<?= url('/inventory') ?>" class="mt-2 inline-flex min-h-12 items-center justify-center rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark"><?= e(t('nl_browse')) ?></a>
      <?php else: ?>
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-700" aria-hidden="true"><?= icon('x', 'h-8 w-8') ?></span>
        <h1 class="text-2xl font-bold"><?= e(t('nl_fail_t')) ?></h1>
        <p class="text-sm text-zinc-500"><?= e(t('nl_fail_s')) ?></p>
        <p class="text-xs text-zinc-500">
          <?= e(t('nl_fail_hint')) ?>
        </p>
        <a href="<?= url('/') ?>" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-300 px-4 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('nl_back_home')) ?></a>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
