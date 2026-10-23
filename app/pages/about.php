<?php
/**
 * About — business story from settings, motto, photo accent, values and a
 * contact summary card.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'about';
$s      = settings();

$pageTitle       = t('meta_about_title');
$metaDescription = t('meta_about_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/about')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('about_aria')) ?>" class="mx-auto w-full max-w-6xl px-4 py-8">
    <?php render_flashes(); ?>

    <div class="grid items-center gap-8 lg:grid-cols-2">
      <div class="space-y-5">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand"><?= e(t('about_kicker')) ?></p>
        <h1 class="text-3xl font-extrabold sm:text-4xl"><?= e((string) $s['business_name']) ?></h1>
        <p class="text-xl font-bold uppercase tracking-widest text-brand sm:text-2xl"><?= e((string) $s['motto']) ?></p>
        <p class="whitespace-pre-line leading-relaxed text-zinc-500"><?= e((string) $s['about_text']) ?></p>
      </div>

      <div class="relative pb-6">
        <img src="<?= url('/uploads/seed/bmw-x3-1.jpg') ?>" alt="<?= e(t('about_photo_alt')) ?>" loading="lazy"
             class="aspect-[4/3] w-full rounded-2xl border border-zinc-200 object-cover shadow-md">
        <div class="absolute bottom-0 left-4 rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-md">
          <p class="text-sm font-semibold"><?= e(t('about_visit', ['address' => (string) $s['address']])) ?></p>
        </div>
      </div>
    </div>

    <!-- Values -->
    <div class="mt-14">
      <h2 class="mb-6 text-2xl font-bold sm:text-3xl"><?= e(t('about_values_t')) ?></h2>
      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
          <?= icon('globe', 'h-8 w-8 text-brand') ?>
          <h3 class="mt-3 text-lg font-semibold"><?= e(t('about_val1_t')) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-zinc-500">
            <?= e(t('about_val1_s')) ?>
          </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
          <?= icon('shield-check', 'h-8 w-8 text-brand') ?>
          <h3 class="mt-3 text-lg font-semibold"><?= e(t('about_val2_t')) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-zinc-500">
            <?= e(t('about_val2_s')) ?>
          </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
          <?= icon('map-pin', 'h-8 w-8 text-brand') ?>
          <h3 class="mt-3 text-lg font-semibold"><?= e(t('about_val3_t')) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-zinc-500">
            <?= e(t('about_val3_s')) ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Contact summary -->
    <div class="mt-10 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <p class="font-semibold"><?= e(t('about_contact_line')) ?></p>
          <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
            <a href="<?= e(tel_link((string) $s['phone_primary'])) ?>" class="inline-flex items-center gap-1.5 hover:text-brand">
              <?= icon('phone', 'h-4 w-4 text-brand') ?> <?= e((string) $s['phone_primary']) ?>
            </a>
            <?php if ((string) ($s['phone_secondary'] ?? '') !== ''): ?>
              <a href="<?= e(tel_link((string) $s['phone_secondary'])) ?>" class="inline-flex items-center gap-1.5 hover:text-brand">
                <?= icon('phone', 'h-4 w-4 text-brand') ?> <?= e((string) $s['phone_secondary']) ?>
              </a>
            <?php endif; ?>
            <a href="mailto:<?= e((string) $s['display_email']) ?>" class="inline-flex items-center gap-1.5 hover:text-brand">
              <?= icon('mail', 'h-4 w-4 text-brand') ?> <?= e((string) $s['display_email']) ?>
            </a>
            <span class="inline-flex items-center gap-1.5">
              <?= icon('map-pin', 'h-4 w-4 text-brand') ?> <?= e((string) $s['address']) ?>
            </span>
          </p>
        </div>
        <a href="<?= url('/contact') ?>" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark"><?= e(t('about_contact_btn')) ?></a>
      </div>
    </div>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
