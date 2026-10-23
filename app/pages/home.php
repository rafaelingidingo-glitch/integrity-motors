<?php
/**
 * Home — hero (featured cover), featured vehicles, latest arrivals,
 * motto band, why-choose-us teaser, WhatsApp contact strip.
 * Parity with the SPA home-view + hero + vehicle-card components.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'home';
$s = settings();

// ---------------------------------------------------------------------------
// Hero background: cover image of the first featured (else latest) published
// AVAILABLE vehicle that actually has a photo. Single query — the cover is a
// correlated subquery with the same cover-first ordering as
// vehicle_attach_covers(), so no second round-trip is needed.
// ---------------------------------------------------------------------------
$heroImage = null;
$heroAlt   = 'Featured vehicle';
$heroRows  = db_all(
    "SELECT v.*,
            (SELECT vi.url FROM vehicle_images vi
             WHERE vi.vehicle_id = v.id
             ORDER BY vi.is_cover DESC, vi.sort_order DESC, vi.id ASC
             LIMIT 1) AS cover
     FROM vehicles v
     WHERE v.published = 1 AND v.status = 'AVAILABLE'
     ORDER BY v.featured DESC, v.created_at DESC
     LIMIT 12"
);
foreach ($heroRows as $row) {
    if (!empty($row['cover'])) {
        $heroImage = (string) $row['cover'];
        $heroAlt   = vehicle_name($row);
        break;
    }
}
unset($heroRows);

// ---------------------------------------------------------------------------
// Featured vehicles (up to 8): featured first, then newest available to fill.
// vehicle_items() = list without the COUNT(*) (no page totals shown here).
// ---------------------------------------------------------------------------
$featured = vehicle_items(['featured' => true], false, 8);
$latestItems = vehicle_items([], false, 12);
$seen = [];
foreach ($featured as $v) {
    $seen[$v['id']] = true;
}
foreach ($latestItems as $v) {
    if (count($featured) >= 8) {
        break;
    }
    if (!isset($seen[$v['id']])) {
        $seen[$v['id']] = true;
        $featured[] = $v;
    }
}

// Latest arrivals preview (6) — first six of the same newest-first list above
// (identical WHERE + ORDER BY, so the rows are reused instead of re-queried).
$latestList  = array_slice($latestItems, 0, 6);
// One COUNT for the "Showing X of Y available vehicles" line.
$latestTotal = (int) db_scalar("SELECT COUNT(*) FROM vehicles WHERE published = 1 AND status = 'AVAILABLE'");
$latestResult = ['items' => $latestList, 'total' => $latestTotal];

/**
 * Vehicle card — exact port of src/components/site/vehicle-card.tsx.
 * Whole card is a stretched link to the detail page; buttons stay z-10.
 */
$renderVehicleCard = function (array $v): void {
    $isSold = $v['status'] === 'SOLD';
    $name   = $v['make'] . ' ' . $v['model'];
    $slug   = url('/vehicle/' . $v['slug']);
    $offerActive = offer_active($v);
    $discount    = discount_percent($v);
    $km   = format_km((int) $v['mileage_km']);
    $year = (string) $v['year'];
    ?>
    <article class="group relative flex h-full min-w-[260px] snap-start flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-shadow hover:shadow-md sm:min-w-0">
      <div class="relative">
        <?php if ($isSold): ?>
          <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 bg-zinc-100 text-zinc-500" role="img" aria-label="<?= e(t('card_sold_aria', ['name' => $name])) ?>">
            <span class="text-2xl font-black uppercase tracking-[0.3em] text-zinc-700 sm:text-3xl"><?= e(t('card_sold_word')) ?></span>
            <?= icon('car', 'h-10 w-10') ?>
          </div>
        <?php elseif (!empty($v['cover'])): ?>
          <img src="<?= e((string) $v['cover']) ?>" alt="<?= e($name . ' ' . $year) ?>" loading="lazy"
               class="aspect-[4/3] h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
        <?php else: ?>
          <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 text-zinc-400" role="img" aria-label="<?= e(t('card_nophoto_aria', ['name' => $name])) ?>">
            <?= icon('car', 'h-9 w-9') ?>
            <span class="text-xs uppercase tracking-widest"><?= e(t('card_no_photo')) ?></span>
          </div>
        <?php endif; ?>

        <div class="absolute left-2 top-2 flex flex-col items-start gap-1.5">
          <?php if ($isSold): ?>
            <span class="rounded-md bg-zinc-900/80 px-2 py-0.5 text-xs font-semibold text-white backdrop-blur"><?= e(t('card_sold')) ?></span>
          <?php else: ?>
            <span class="rounded-md bg-green-600 px-2 py-0.5 text-xs font-semibold text-white"><?= e(t('card_available')) ?></span>
          <?php endif; ?>
          <span class="rounded-md border border-zinc-200 bg-white/80 px-2 py-0.5 text-xs backdrop-blur"><?= e(t_cond((string) $v['condition'])) ?></span>
        </div>
        <?php if ($offerActive && $discount > 0): ?>
          <span class="absolute right-2 top-2 rounded-md bg-brand px-2 py-0.5 text-xs font-semibold text-white">&minus;<?= $discount ?>%</span>
        <?php endif; ?>
      </div>

      <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="space-y-1">
          <h3 class="line-clamp-1 text-base font-semibold"><?= e($name) ?> <span class="font-normal text-zinc-500"><?= e($year) ?></span></h3>
          <?php if ($offerActive): ?>
            <?php $save = max((int) $v['price'] - (int) $v['offer_price'], 0); ?>
            <div class="space-y-0.5">
              <p class="text-xl font-bold text-brand"><?= e(format_tsh((int) $v['offer_price'])) ?></p>
              <p class="text-sm text-zinc-500">
                <span class="line-through"><?= e(format_tsh((int) $v['price'])) ?></span>
                <?php if ($save > 0): ?><span class="ml-2 font-medium text-brand"><?= e(t('card_you_save', ['amount' => format_tsh_compact($save)])) ?></span><?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <p class="text-xl font-bold"><?= e(format_tsh((int) $v['price'])) ?></p>
          <?php endif; ?>
        </div>

        <ul class="flex flex-wrap gap-1.5 text-xs text-zinc-500" aria-label="<?= e(t('card_specs_aria')) ?>">
          <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e($year) ?></li>
          <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e($km) ?></li>
          <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e((string) $v['fuel_type']) ?></li>
          <?php if ($v['engine_cc'] !== null && $v['engine_cc'] !== ''): ?>
            <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e((string) $v['engine_cc']) ?>cc</li>
          <?php endif; ?>
        </ul>

        <div class="relative z-10 mt-auto grid grid-cols-2 gap-2">
          <a href="<?= e($slug) ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"><?= e(t('card_view_details')) ?></a>
          <?php if ($isSold): ?>
            <span class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-400" aria-disabled="true"><?= e(t('card_enquire_now')) ?></span>
          <?php else: ?>
            <a href="<?= e(url('/enquire?vehicle=' . $v['slug'])) ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('card_enquire_now')) ?></a>
          <?php endif; ?>
        </div>
      </div>

      <a href="<?= e($slug) ?>" aria-label="<?= e(t('card_view_aria', ['name' => $name . ' ' . $year])) ?>" class="absolute inset-0 rounded-xl"></a>
    </article>
    <?php
};

$pageTitle    = t('meta_home_title');
$metaDescription = t('meta_home_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url(current_path())) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">

  <?php render_flashes(); // rare on home — e.g. newsletter fallback redirect ?>

  <!-- 1) Hero -->
  <section class="relative flex min-h-[300px] items-center overflow-hidden bg-zinc-900 text-white sm:min-h-[360px] md:min-h-[420px]" aria-label="<?= e(t('home_hero_aria')) ?>">
    <?php if ($heroImage !== null): ?>
      <img src="<?= e($heroImage) ?>" alt="<?= e($heroAlt) ?>" class="absolute inset-0 h-full w-full object-cover" loading="eager" fetchpriority="high">
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/65 to-black/35" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-6xl px-4 py-12 sm:py-16">
      <div class="max-w-2xl space-y-5">
        <h1 class="text-4xl font-bold leading-tight text-white sm:text-5xl"><?= e(t('home_hero_title')) ?></h1>
        <p class="max-w-xl text-sm text-white/85 sm:text-base">
          <?= e(t('home_hero_sub')) ?>
        </p>
        <div class="flex flex-wrap gap-3">
          <a href="<?= url('/inventory') ?>" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-white px-6 text-base font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-zinc-200"><?= e(t('home_browse')) ?></a>
          <a href="<?= e(wa_link((string) $s['whatsapp_number'], wa_preset('hero'))) ?>" target="_blank" rel="noopener noreferrer"
             class="inline-flex min-h-12 items-center justify-center rounded-lg border border-white/60 bg-white/10 px-6 text-base font-semibold text-white transition-colors hover:bg-white hover:text-zinc-900">
            <?= e(t('home_contact')) ?>
          </a>
        </div>
        <form method="get" action="<?= url('/inventory') ?>" class="flex max-w-md gap-2 pt-2" role="search" aria-label="<?= e(t('home_search_aria')) ?>">
          <label for="hero-search" class="sr-only"><?= e(t('home_search_label')) ?></label>
          <input id="hero-search" type="search" name="search" placeholder="<?= e(t('home_search_ph')) ?>"
                 class="h-11 min-w-0 flex-1 rounded-lg border border-white/30 bg-white/95 px-3 text-sm text-zinc-900 outline-none placeholder:text-zinc-500 focus:border-brand focus:ring-2 focus:ring-brand/20">
          <button type="submit" aria-label="<?= e(t('home_search_btn_aria')) ?>"
                  class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white transition-colors hover:bg-brand-dark">
            <?= icon('search', 'h-4 w-4') ?><span class="hidden sm:inline"><?= e(t('home_search_btn')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </section>

  <!-- 2) Featured Vehicles -->
  <?php if ($featured !== []): ?>
  <section aria-label="<?= e(t('home_featured_aria')) ?>" class="py-10 sm:py-12">
    <div class="mx-auto w-full max-w-6xl px-4">
      <div class="mb-6 flex items-center justify-between gap-4">
        <h2 class="text-2xl font-bold sm:text-3xl"><?= e(t('home_featured')) ?></h2>
        <a href="<?= url('/inventory') ?>" class="inline-flex min-h-11 items-center text-sm font-medium text-brand hover:underline"><?= e(t('home_view_all')) ?> &rarr;</a>
      </div>
      <div class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 scrollbar-slim sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0 lg:grid-cols-4">
        <?php foreach ($featured as $v) { ($renderVehicleCard)($v); } ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3) Latest Arrivals -->
  <section aria-label="<?= e(t('home_latest_aria')) ?>" class="pb-10 sm:pb-12">
    <div class="mx-auto w-full max-w-6xl px-4">
      <div class="mb-6 flex items-center justify-between gap-4">
        <h2 class="text-2xl font-bold sm:text-3xl"><?= e(t('home_latest')) ?></h2>
        <a href="<?= url('/inventory') ?>" class="inline-flex min-h-11 items-center text-sm font-medium text-brand hover:underline"><?= e(t('home_view_all')) ?> &rarr;</a>
      </div>
      <?php if ($latestResult['items'] === []): ?>
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center">
          <p class="text-sm text-zinc-500"><?= e(t('home_latest_empty')) ?></p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($latestResult['items'] as $v) { ($renderVehicleCard)($v); } ?>
        </div>
        <p class="mt-4 text-sm text-zinc-500">
          <?= e(t('home_showing', ['n' => count($latestResult['items']), 't' => (int) $latestResult['total']])) ?>
          <a href="<?= url('/inventory') ?>" class="font-medium text-brand hover:underline"><?= e(t('home_see_full')) ?></a>
        </p>
      <?php endif; ?>
    </div>
  </section>

  <!-- 4) Motto band -->
  <section aria-label="<?= e(t('home_motto_aria')) ?>" class="border-y border-zinc-200 bg-zinc-50">
    <div class="mx-auto max-w-4xl px-4 py-12 text-center sm:px-6">
      <p class="text-sm font-semibold uppercase tracking-[0.25em] text-brand"><?= e((string) $s['motto']) ?></p>
      <h2 class="mt-3 text-2xl font-bold sm:text-3xl"><?= e(t('home_motto_title')) ?></h2>
      <p class="mx-auto mt-3 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-zinc-600 sm:text-base"><?= e((string) $s['about_text']) ?></p>
      <a href="<?= url('/about') ?>" class="mt-5 inline-flex min-h-12 items-center justify-center rounded-lg border border-zinc-300 bg-white px-6 font-semibold text-zinc-900 transition-colors hover:border-brand hover:text-brand"><?= e(t('home_learn_more')) ?></a>
    </div>
  </section>

  <!-- 5) Why choose us teaser -->
  <section aria-label="<?= e(t('home_why_aria')) ?>" class="mx-auto w-full max-w-6xl px-4 py-12">
    <h2 class="text-center text-2xl font-bold sm:text-3xl"><?= e(t('home_why_title')) ?></h2>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center">
        <?= icon('shield-check', 'mx-auto h-8 w-8 text-brand') ?>
        <h3 class="mt-3 font-semibold"><?= e(t('home_val1_t')) ?></h3>
        <p class="mt-1 text-sm text-zinc-500"><?= e(t('home_val1_s')) ?></p>
      </div>
      <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center">
        <?= icon('globe', 'mx-auto h-8 w-8 text-brand') ?>
        <h3 class="mt-3 font-semibold"><?= e(t('home_val2_t')) ?></h3>
        <p class="mt-1 text-sm text-zinc-500"><?= e(t('home_val2_s')) ?></p>
      </div>
      <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center">
        <?= icon('sparkles', 'mx-auto h-8 w-8 text-brand') ?>
        <h3 class="mt-3 font-semibold"><?= e(t('home_val3_t')) ?></h3>
        <p class="mt-1 text-sm text-zinc-500"><?= e(t('home_val3_s')) ?></p>
      </div>
      <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center">
        <?= icon('message-circle', 'mx-auto h-8 w-8 text-brand') ?>
        <h3 class="mt-3 font-semibold"><?= e(t('home_val4_t')) ?></h3>
        <p class="mt-1 text-sm text-zinc-500"><?= e(t('home_val4_s')) ?></p>
      </div>
    </div>
    <div class="mt-6 text-center">
      <a href="<?= url('/why-us') ?>" class="inline-flex min-h-11 items-center font-medium text-brand hover:underline"><?= e(t('home_why_link')) ?> &rarr;</a>
    </div>
  </section>

  <!-- 6) Contact strip -->
  <section aria-label="<?= e(t('home_strip_aria')) ?>" class="mx-auto w-full max-w-6xl px-4 pb-12">
    <div class="flex flex-col items-center gap-5 rounded-2xl bg-ink p-8 text-center text-white sm:p-10">
      <h2 class="text-2xl font-bold sm:text-3xl"><?= e(t('home_strip_title')) ?></h2>
      <p class="max-w-xl text-sm text-white/80 sm:text-base">
        <?= e(t('home_strip_sub')) ?>
      </p>
      <a href="<?= e(wa_link((string) $s['whatsapp_number'], wa_preset('general'))) ?>" target="_blank" rel="noopener noreferrer"
         class="inline-flex min-h-12 items-center justify-center rounded-lg bg-wa px-6 text-base font-semibold text-white transition-colors hover:bg-wa-dark">
        <?= e(t('home_contact')) ?>
      </a>
    </div>
  </section>

</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
