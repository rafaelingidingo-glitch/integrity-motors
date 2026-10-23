<?php
/**
 * Vehicle detail — gallery, badges, price (with offer), spec grid, features,
 * CTA stack (enquire / WhatsApp / phone), similar vehicles.
 * Expects $GLOBALS['vehicle_slug'] (set by the front controller).
 * Staff sessions see SOLD vehicles with an amber notice; the public gets 404.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active  = 'vehicle';
$vehicle = vehicle_find($GLOBALS['vehicle_slug'] ?? null, null, is_staff());

if ($vehicle === null) {
    http_response_code(404);
    require APP_ROOT . '/app/pages/not-found.php';
    exit;
}

$s      = settings();
$isSold = $vehicle['status'] === 'SOLD';
$name   = vehicle_name($vehicle);
$shortName = $vehicle['make'] . ' ' . $vehicle['model'];
$offerActive   = offer_active($vehicle);
$discount      = discount_percent($vehicle);
$effective     = effective_price($vehicle);
$saveAmount    = $offerActive ? max((int) $vehicle['price'] - (int) $vehicle['offer_price'], 0) : 0;
$features      = array_values(array_filter(array_map('trim', explode("\n", (string) $vehicle['features'])), fn ($f) => $f !== ''));
$waVehicleHref = wa_link((string) $s['whatsapp_number'], wa_preset('vehicle', [
    'name'  => $shortName,
    'year'  => (string) $vehicle['year'],
    'price' => $effective,
]));

$specs = [
    [t('spec_Year'), (string) $vehicle['year']],
    [t('spec_Mileage'), format_km((int) $vehicle['mileage_km'])],
    [t('spec_Engine CC'), $vehicle['engine_cc'] !== null && $vehicle['engine_cc'] !== '' ? (string) $vehicle['engine_cc'] . ' cc' : '—'],
    [t('spec_Fuel'), (string) $vehicle['fuel_type']],
    [t('spec_Transmission'), (string) ($vehicle['transmission'] ?? '—')],
    [t('spec_Drive'), (string) ($vehicle['drive_type'] ?? '—')],
    [t('spec_Color'), (string) ($vehicle['exterior_color'] ?? '—')],
    [t('spec_Condition'), t_cond((string) $vehicle['condition'])],
];
if (!empty($vehicle['chassis_number'])) {
    $specs[] = [t('spec_Chassis'), (string) $vehicle['chassis_number']];
}

$metaDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string) $vehicle['description'])) ?? '');
if ($metaDescription === '') {
    $metaDescription = $shortName . ' ' . $vehicle['year'] . ' for sale in Dar es Salaam — ' . format_km((int) $vehicle['mileage_km']) . ', ' . $vehicle['fuel_type'] . ', ' . $vehicle['condition'] . '.';
}
$metaDescription = mb_substr($metaDescription, 0, 160);

$similar = vehicle_similar($vehicle, 4);

/** Similar-vehicle mini card (parity with SimilarCard in vehicle-detail-view.tsx). */
$renderSimilarCard = function (array $v): void {
    $vName = $v['make'] . ' ' . $v['model'];
    ?>
    <article class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
      <a href="<?= e(url('/vehicle/' . $v['slug'])) ?>" class="block" aria-label="<?= e(t('card_view_aria', ['name' => $vName . ' ' . $v['year']])) ?>">
        <?php if (!empty($v['cover'])): ?>
          <img src="<?= e((string) $v['cover']) ?>" alt="<?= e($vName . ' ' . $v['year']) ?>" loading="lazy" class="aspect-[4/3] w-full object-cover">
        <?php else: ?>
          <div class="flex aspect-[4/3] w-full items-center justify-center text-zinc-400" role="img" aria-label="<?= e(t('card_nophoto_aria', ['name' => $vName])) ?>">
            <?= icon('car', 'h-9 w-9') ?>
          </div>
        <?php endif; ?>
        <div class="space-y-1 p-3">
          <p class="truncate text-sm font-semibold"><?= e($vName) ?> <span class="font-normal text-zinc-500"><?= e((string) $v['year']) ?></span></p>
          <p class="text-sm font-bold text-brand"><?= e(format_tsh(effective_price($v))) ?></p>
          <p class="text-xs text-zinc-500"><?= e(format_km((int) $v['mileage_km'])) ?></p>
        </div>
      </a>
    </article>
    <?php
};

$pageTitle       = vehicle_name($vehicle) . ' | Integrity Autocars';
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/vehicle/' . $vehicle['slug'])) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('veh_details_aria', ['name' => $shortName, 'year' => (string) $vehicle['year']])) ?>" class="mx-auto w-full max-w-6xl px-4 py-8">
    <?php render_flashes(); ?>

    <a href="<?= url('/inventory') ?>" class="mb-4 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-zinc-500 transition-colors hover:text-brand">
      <?= icon('arrow-left', 'h-4 w-4') ?> <?= e(t('veh_back')) ?>
    </a>

    <?php if ($isSold): ?>
      <div class="mb-6 rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3" role="alert">
        <p class="flex items-center gap-2 font-semibold text-amber-800"><?= icon('alert-triangle', 'h-4 w-4 text-amber-600') ?> <?= e(t('veh_sold_title')) ?></p>
        <p class="mt-1 text-sm text-amber-800/90">
          <?= e(t('veh_sold_text', ['name' => $shortName])) ?>
        </p>
      </div>
    <?php endif; ?>

    <div class="grid gap-8 lg:grid-cols-2">
      <!-- Gallery -->
      <div class="space-y-2" data-gallery>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100">
          <?php if ($vehicle['images'] !== []): ?>
            <img src="<?= e((string) $vehicle['images'][0]['url']) ?>"
                 alt="<?= e($shortName . ' ' . $vehicle['year'] . ' — photo 1 of ' . count($vehicle['images'])) ?>"
                 data-gallery-main
                 class="aspect-[16/10] w-full object-cover">
          <?php else: ?>
            <div class="flex aspect-[16/10] w-full flex-col items-center justify-center gap-2 text-zinc-400" role="img" aria-label="<?= e(t('veh_no_photos')) ?>">
              <?= icon('car', 'h-12 w-12') ?>
              <p class="text-sm"><?= e(t('veh_no_photos')) ?></p>
            </div>
          <?php endif; ?>
        </div>

        <?php if (count($vehicle['images']) > 1): ?>
          <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-slim" role="listbox" aria-label="<?= e(t('veh_photos_aria')) ?>">
            <?php foreach ($vehicle['images'] as $i => $img): ?>
              <button type="button" role="option" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= e(t('veh_show_photo', ['n' => $i + 1])) ?>"
                      data-gallery-thumb="<?= e((string) $img['url']) ?>"
                      data-gallery-alt="<?= e($shortName . ' ' . $vehicle['year'] . ' — photo ' . ($i + 1) . ' of ' . count($vehicle['images'])) ?>"
                      class="h-16 w-24 shrink-0 overflow-hidden rounded-md border-2 transition-colors sm:h-20 sm:w-28 <?= $i === 0 ? 'border-brand' : 'border-transparent hover:border-zinc-300' ?>">
                <img src="<?= e((string) $img['url']) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Details -->
      <div class="space-y-5">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <?php if ($isSold): ?>
              <span class="rounded-md bg-zinc-900/85 px-2 py-0.5 text-xs font-semibold text-white"><?= e(t('card_sold')) ?></span>
            <?php else: ?>
              <span class="rounded-md bg-green-600 px-2 py-0.5 text-xs font-semibold text-white"><?= e(t('card_available')) ?></span>
            <?php endif; ?>
            <span class="rounded-md border border-zinc-300 px-2 py-0.5 text-xs"><?= e(t_cond((string) $vehicle['condition'])) ?></span>
            <?php if ((int) $vehicle['featured'] === 1): ?>
              <span class="rounded-md border border-brand/50 px-2 py-0.5 text-xs text-brand"><?= e(t('veh_featured')) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="text-2xl font-bold sm:text-3xl"><?= e($shortName) ?> <span class="font-normal text-zinc-500"><?= e((string) $vehicle['year']) ?></span></h1>
          <?php if ($offerActive): ?>
            <div class="space-y-1">
              <p class="text-2xl font-bold text-brand sm:text-3xl"><?= e(format_tsh((int) $vehicle['offer_price'])) ?></p>
              <p class="text-sm text-zinc-500">
                <span class="line-through"><?= e(t('veh_regular')) ?> <?= e(format_tsh((int) $vehicle['price'])) ?></span>
                <?php if ($saveAmount > 0): ?>
                  <span class="ml-2 font-semibold text-brand"><?= e(t('card_you_save', ['amount' => format_tsh_compact($saveAmount)])) ?></span>
                <?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <p class="text-2xl font-bold sm:text-3xl"><?= e(format_tsh((int) $vehicle['price'])) ?></p>
          <?php endif; ?>
        </div>

        <!-- Spec grid -->
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-3">
          <?php foreach ($specs as [$label, $value]): ?>
            <div class="min-w-0">
              <dt class="text-xs uppercase tracking-wide text-zinc-500"><?= e($label) ?></dt>
              <dd class="truncate text-sm font-medium" title="<?= e($value) ?>"><?= e($value) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <?php if ((string) $vehicle['description'] !== ''): ?>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('veh_description')) ?></h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-zinc-500"><?= e((string) $vehicle['description']) ?></p>
          </div>
        <?php endif; ?>

        <?php if ($features !== []): ?>
          <div class="space-y-1.5">
            <h2 class="text-lg font-semibold"><?= e(t('veh_features')) ?></h2>
            <ul class="grid grid-cols-1 gap-1 text-sm text-zinc-500 sm:grid-cols-2">
              <?php foreach ($features as $f): ?>
                <li class="flex items-start gap-2"><?= icon('check', 'mt-0.5 h-4 w-4 shrink-0 text-green-600') ?> <?= e($f) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <hr class="border-zinc-200">

        <!-- CTA stack -->
        <div class="space-y-3">
          <?php if ($isSold): ?>
            <button type="button" disabled aria-disabled="true"
                    class="inline-flex min-h-12 w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-zinc-300 px-6 font-semibold text-zinc-500">
              <?= icon('send', 'h-4 w-4') ?> <?= e(t('veh_enquiry_off')) ?>
            </button>
          <?php else: ?>
            <a href="<?= e(url('/enquire?vehicle=' . $vehicle['slug'])) ?>"
               class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark">
              <?= icon('send', 'h-4 w-4') ?> <?= e(t('card_enquire_now')) ?>
            </a>
          <?php endif; ?>

          <div class="grid gap-2 sm:grid-cols-2">
            <a href="<?= e($waVehicleHref) ?>" target="_blank" rel="noopener noreferrer"
               class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-wa px-6 font-semibold text-white transition-colors hover:bg-wa-dark">
              <?= icon('whatsapp', 'h-4 w-4') ?> <?= e(t('veh_whatsapp')) ?>
            </a>
            <a href="<?= e(tel_link((string) $s['phone_primary'])) ?>"
               class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-6 font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand">
              <?= icon('phone', 'h-4 w-4') ?> <?= e(t('veh_call', ['phone' => (string) $s['phone_primary']])) ?>
            </a>
          </div>
          <?php if ((string) ($s['phone_secondary'] ?? '') !== ''): ?>
            <a href="<?= e(tel_link((string) $s['phone_secondary'])) ?>"
               class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-6 text-sm font-medium text-zinc-500 transition-colors hover:text-brand">
              <?= icon('phone', 'h-4 w-4') ?> <?= e(t('veh_call', ['phone' => (string) $s['phone_secondary']])) ?>
            </a>
          <?php endif; ?>
          <p class="text-xs text-zinc-500">
            <?php if ($offerActive && (string) ($vehicle['offer_terms'] ?? '') !== ''): ?>
              <?= e(t('veh_offer_terms', ['terms' => (string) $vehicle['offer_terms']])) ?>
            <?php else: ?>
              <?= e(t('veh_listed', ['date' => format_date_medium((string) $vehicle['created_at'])])) ?>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>

    <?php if ($similar !== []): ?>
      <div class="mt-14">
        <h2 class="mb-4 text-2xl font-bold"><?= e(t('veh_similar')) ?></h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach ($similar as $v) { ($renderSimilarCard)($v); } ?>
        </div>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
