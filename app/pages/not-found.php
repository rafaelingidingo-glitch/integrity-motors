<?php
/**
 * 404 page — required by the front controller (unknown paths) and by
 * vehicle.php (missing/unpublished vehicle). The caller normally sets the
 * 404 status; enforce it here too so unknown routes never return 200.
 *
 * Accepts an optional pre-set $active (e.g. 'vehicle') and $pageTitle.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

http_response_code(404);

$active    = isset($active) && is_string($active) ? $active : '';
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : t('meta_404_title');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(t('meta_404_desc')) ?>">
<link rel="canonical" href="<?= e(app_url(current_path())) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section class="mx-auto flex w-full max-w-2xl flex-col items-center gap-4 px-4 py-20 text-center">
    <p class="text-7xl font-black text-brand sm:text-8xl" aria-hidden="true">404</p>
    <h1 class="text-2xl font-bold sm:text-3xl"><?= e(t('nf_t')) ?></h1>
    <p class="text-sm text-zinc-500"><?= e(t('nf_s')) ?></p>
    <a href="<?= url('/') ?>" class="mt-2 inline-flex min-h-12 items-center justify-center rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark"><?= e(t('nf_back')) ?></a>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
