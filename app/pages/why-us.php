<?php
/**
 * Why Choose Us — four reason cards + CTA band.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'why-us';
$s      = settings();

$pageTitle       = t('meta_why_title');
$metaDescription = t('meta_why_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/why-us')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('why_aria')) ?>" class="mx-auto w-full max-w-6xl px-4 py-8">
    <?php render_flashes(); ?>

    <div class="mx-auto max-w-2xl space-y-3 text-center">
      <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand"><?= e(t('why_kicker')) ?></p>
      <h1 class="text-3xl font-extrabold sm:text-4xl"><?= e(t('why_title')) ?></h1>
      <p class="text-zinc-500">
        <?= e(t('why_sub')) ?>
      </p>
    </div>

    <div class="mt-10 grid gap-4 sm:grid-cols-2">
      <div class="h-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex h-full gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('file-text', 'h-6 w-6 text-brand') ?></span>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('why1_t')) ?></h2>
            <p class="text-sm leading-relaxed text-zinc-500"><?= e(t('why1_s')) ?></p>
          </div>
        </div>
      </div>
      <div class="h-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex h-full gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('sparkles', 'h-6 w-6 text-brand') ?></span>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('why2_t')) ?></h2>
            <p class="text-sm leading-relaxed text-zinc-500"><?= e(t('why2_s')) ?></p>
          </div>
        </div>
      </div>
      <div class="h-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex h-full gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('message-circle', 'h-6 w-6 text-brand') ?></span>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('why3_t')) ?></h2>
            <p class="text-sm leading-relaxed text-zinc-500"><?= e(t('why3_s')) ?></p>
          </div>
        </div>
      </div>
      <div class="h-full rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex h-full gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('globe', 'h-6 w-6 text-brand') ?></span>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('why4_t')) ?></h2>
            <p class="text-sm leading-relaxed text-zinc-500"><?= e(t('why4_s')) ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-10 flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 p-8 text-center">
      <h2 class="text-xl font-bold"><?= e(t('why_cta_t')) ?></h2>
      <p class="text-sm text-zinc-500"><?= e(t('why_cta_s')) ?></p>
      <div class="flex flex-wrap justify-center gap-3">
        <a href="<?= url('/inventory') ?>" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark"><?= e(t('home_browse')) ?></a>
        <a href="<?= e(wa_link((string) $s['whatsapp_number'], wa_preset('general'))) ?>" target="_blank" rel="noopener noreferrer"
           class="inline-flex min-h-12 items-center justify-center rounded-lg border border-zinc-300 bg-white px-6 font-semibold text-zinc-900 transition-colors hover:border-brand hover:text-brand"><?= e(t('home_contact')) ?></a>
      </div>
    </div>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
