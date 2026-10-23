<?php
/**
 * Inventory — public GET-only listing page: sidebar filters (search, brand
 * chips, condition, price, year), sort select, 12-per-page grid with
 * Prev/Next pagination. Filters ride entirely on the query string.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'inventory';
$s = settings();

// ---------------------------------------------------------------------------
// Read + whitelist query-string filters (keys mirror app/lib/vehicles.php).
// ---------------------------------------------------------------------------
$FILTER_KEYS = ['search', 'make', 'condition', 'minPrice', 'maxPrice', 'yearFrom', 'yearTo', 'sort'];
$q = [];
foreach ($FILTER_KEYS as $key) {
    $q[$key] = trim((string) ($_GET[$key] ?? ''));
}
if (!in_array($q['sort'], VEHICLE_SORTS, true)) {
    $q['sort'] = 'newest';
}
if ($q['condition'] !== '' && !in_array($q['condition'], ['New', 'Used'], true)) {
    $q['condition'] = '';
}
foreach (['minPrice', 'maxPrice', 'yearFrom', 'yearTo'] as $numKey) {
    if ($q[$numKey] !== '' && !preg_match('/^\d+$/', $q[$numKey])) {
        $q[$numKey] = '';
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = vehicle_list($q, false, $page, 12);
$totalPages = max(1, $result['totalPages']);
$brands = vehicle_brands();

/** Build /inventory URL from the current filters with overrides; empty values dropped. */
$invUrl = function (array $overrides = [], bool $keepPage = false): string {
    static $keys = null;
    $keys ??= ['search', 'make', 'condition', 'minPrice', 'maxPrice', 'yearFrom', 'yearTo', 'sort'];
    $params = [];
    foreach ($keys as $k) {
        $params[$k] = $overrides[$k] ?? (($_GET[$k] ?? '') !== '' ? (string) $_GET[$k] : '');
    }
    if ($keepPage && isset($overrides['page'])) {
        $params['page'] = (string) $overrides['page'];
    }
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    if (!$keepPage) {
        unset($params['page']); // changing filters always resets to page 1
    }
    $qs = http_build_query($params);
    return url('/inventory') . ($qs !== '' ? '?' . $qs : '');
};

$hasFilters = false;
foreach ($q as $v) {
    if ($v !== '' && $v !== 'newest') {
        $hasFilters = true;
        break;
    }
}

/** Filter panel (rendered twice: desktop aside + mobile <details>). */
$renderFilters = function () use ($q, $brands, $invUrl, $hasFilters): void {
    ?>
    <form method="get" action="<?= url('/inventory') ?>" class="space-y-5">
      <div class="space-y-1.5">
        <label for="inv-search" class="text-sm font-medium"><?= e(t('inv_search')) ?></label>
        <input id="inv-search" type="search" name="search" value="<?= e($q['search']) ?>" placeholder="<?= e(t('inv_search_ph')) ?>"
               class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none placeholder:text-zinc-400 focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>

      <div class="space-y-1.5">
        <span class="text-sm font-medium" id="inv-brand-label"><?= e(t('inv_brand')) ?></span>
        <div class="flex flex-wrap gap-1.5" role="group" aria-labelledby="inv-brand-label">
          <a href="<?= e($invUrl(['make' => ''])) ?>" aria-pressed="<?= $q['make'] === '' ? 'true' : 'false' ?>"
             class="inline-flex min-h-9 items-center rounded-full border px-3 py-1 text-sm transition-colors <?= $q['make'] === '' ? 'border-brand bg-brand text-white' : 'border-zinc-300 bg-white text-zinc-800 hover:border-brand/50' ?>"><?= e(t('inv_all')) ?></a>
          <?php foreach ($brands as $b): ?>
            <a href="<?= e($invUrl(['make' => $q['make'] === $b['make'] ? '' : (string) $b['make']])) ?>"
               aria-pressed="<?= $q['make'] === $b['make'] ? 'true' : 'false' ?>"
               class="inline-flex min-h-9 items-center rounded-full border px-3 py-1 text-sm transition-colors <?= $q['make'] === $b['make'] ? 'border-brand bg-brand text-white' : 'border-zinc-300 bg-white text-zinc-800 hover:border-brand/50' ?>">
              <?= e((string) $b['make']) ?> <span class="ml-1 text-xs opacity-70">(<?= (int) $b['count'] ?>)</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="space-y-1.5">
        <label for="inv-cond" class="text-sm font-medium"><?= e(t('inv_condition')) ?></label>
        <select id="inv-cond" name="condition"
                class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
          <option value=""><?= e(t('inv_any_condition')) ?></option>
          <option value="New" <?= $q['condition'] === 'New' ? 'selected' : '' ?>><?= e(t('cond_New')) ?></option>
          <option value="Used" <?= $q['condition'] === 'Used' ? 'selected' : '' ?>><?= e(t('cond_Used')) ?></option>
        </select>
      </div>

      <div class="space-y-1.5">
        <span class="text-sm font-medium" id="inv-price-label"><?= e(t('inv_price')) ?></span>
        <div class="grid grid-cols-2 gap-2">
          <input type="number" name="minPrice" inputmode="numeric" min="0" value="<?= e($q['minPrice']) ?>" placeholder="<?= e(t('inv_min')) ?>"
                 aria-labelledby="inv-price-label" aria-label="<?= e(t('inv_price_min_aria')) ?>"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none placeholder:text-zinc-400 focus:border-brand focus:ring-2 focus:ring-brand/20">
          <input type="number" name="maxPrice" inputmode="numeric" min="0" value="<?= e($q['maxPrice']) ?>" placeholder="<?= e(t('inv_max')) ?>"
                 aria-labelledby="inv-price-label" aria-label="<?= e(t('inv_price_max_aria')) ?>"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none placeholder:text-zinc-400 focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>

      <div class="space-y-1.5">
        <span class="text-sm font-medium" id="inv-year-label"><?= e(t('inv_year')) ?></span>
        <div class="grid grid-cols-2 gap-2">
          <input type="number" name="yearFrom" inputmode="numeric" min="1950" max="2100" value="<?= e($q['yearFrom']) ?>" placeholder="<?= e(t('inv_from')) ?>"
                 aria-labelledby="inv-year-label" aria-label="<?= e(t('inv_year_from_aria')) ?>"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none placeholder:text-zinc-400 focus:border-brand focus:ring-2 focus:ring-brand/20">
          <input type="number" name="yearTo" inputmode="numeric" min="1950" max="2100" value="<?= e($q['yearTo']) ?>" placeholder="<?= e(t('inv_to')) ?>"
                 aria-labelledby="inv-year-label" aria-label="<?= e(t('inv_year_to_aria')) ?>"
                 class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none placeholder:text-zinc-400 focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>

      <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 font-semibold text-white transition-colors hover:bg-brand-dark">
        <?= icon('filter', 'h-4 w-4') ?> <?= e(t('inv_apply')) ?>
      </button>

      <?php if ($hasFilters): ?>
        <a href="<?= e(url('/inventory')) ?>" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-brand hover:underline">
          <?= icon('x', 'h-4 w-4') ?> <?= e(t('inv_clear')) ?>
        </a>
      <?php endif; ?>
    </form>
    <?php
};

/** Vehicle card — same markup as the home page card (shared spec §1.4). */
$renderVehicleCard = function (array $v): void {
    $isSold = $v['status'] === 'SOLD';
    $name   = $v['make'] . ' ' . $v['model'];
    $slug   = url('/vehicle/' . $v['slug']);
    $offerActive = offer_active($v);
    $discount    = discount_percent($v);
    ?>
    <article class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-shadow hover:shadow-md">
      <div class="relative">
        <?php if ($isSold): ?>
          <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 bg-zinc-100 text-zinc-500" role="img" aria-label="<?= e(t('card_sold_aria', ['name' => $name])) ?>">
            <span class="text-2xl font-black uppercase tracking-[0.3em] text-zinc-700 sm:text-3xl"><?= e(t('card_sold_word')) ?></span>
            <?= icon('car', 'h-10 w-10') ?>
          </div>
        <?php elseif (!empty($v['cover'])): ?>
          <img src="<?= e((string) $v['cover']) ?>" alt="<?= e($name . ' ' . $v['year']) ?>" loading="lazy"
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
          <h3 class="line-clamp-1 text-base font-semibold"><?= e($name) ?> <span class="font-normal text-zinc-500"><?= e((string) $v['year']) ?></span></h3>
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
          <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e((string) $v['year']) ?></li>
          <li class="rounded-md bg-zinc-100 px-2 py-1"><?= e(format_km((int) $v['mileage_km'])) ?></li>
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

      <a href="<?= e($slug) ?>" aria-label="<?= e(t('card_view_aria', ['name' => $name . ' ' . $v['year']])) ?>" class="absolute inset-0 rounded-xl"></a>
    </article>
    <?php
};

$SORT_OPTIONS = [
    'newest'     => t('sort_newest'),
    'price_asc'  => t('sort_price_asc'),
    'price_desc' => t('sort_price_desc'),
    'year_desc'  => t('sort_year_desc'),
];

$pageTitle       = t('meta_inventory_title');
$metaDescription = t('meta_inventory_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/inventory')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <div class="mx-auto w-full max-w-6xl px-4 py-8">
    <?php render_flashes(); ?>

    <header class="mb-6">
      <h1 class="text-3xl font-bold sm:text-4xl"><?= e(t('inv_title')) ?></h1>
      <p class="mt-1 text-sm text-zinc-500" aria-live="polite">
        <?= e((int) $result['total'] === 1 ? t('inv_found_one') : t('inv_found_many', ['n' => (int) $result['total']])) ?>
      </p>
    </header>

    <div class="lg:flex lg:items-start lg:gap-6">
      <!-- Mobile: collapsible filters / Desktop: sticky sidebar -->
      <details class="mb-4 rounded-xl border border-zinc-200 bg-white lg:hidden" <?= $hasFilters ? 'open' : '' ?>>
        <summary class="inline-flex min-h-11 w-full cursor-pointer list-none items-center justify-between gap-2 rounded-xl px-4 py-2 font-medium">
          <span class="inline-flex items-center gap-2"><?= icon('filter', 'h-4 w-4') ?> <?= e(t('inv_filters')) ?><?= $hasFilters ? ' <span class="text-xs text-brand">' . e(t('inv_filters_active')) . '</span>' : '' ?></span>
          <span aria-hidden="true" class="text-xs text-zinc-500"><?= e(t('inv_open_close')) ?></span>
        </summary>
        <div class="border-t border-zinc-100 p-4">
          <?php ($renderFilters)(); ?>
        </div>
      </details>

      <aside aria-label="<?= e(t('inv_filters_aria')) ?>" class="hidden w-72 shrink-0 rounded-xl border border-zinc-200 bg-white p-4 lg:sticky lg:top-20 lg:block">
        <?php ($renderFilters)(); ?>
      </aside>

      <!-- Results -->
      <section aria-label="<?= e(t('inv_results_aria')) ?>" class="min-w-0 flex-1">
        <!-- Sort select (GET form, auto-submits on change) -->
        <form method="get" action="<?= url('/inventory') ?>" class="mb-4 flex flex-wrap items-center justify-end gap-2">
          <?php foreach (['search', 'make', 'condition', 'minPrice', 'maxPrice', 'yearFrom', 'yearTo'] as $hiddenKey): ?>
            <?php if ($q[$hiddenKey] !== ''): ?>
              <input type="hidden" name="<?= e($hiddenKey) ?>" value="<?= e($q[$hiddenKey]) ?>">
            <?php endif; ?>
          <?php endforeach; ?>
          <label for="inv-sort" class="text-sm text-zinc-500"><?= e(t('inv_sort_by')) ?></label>
          <select id="inv-sort" name="sort" data-autosubmit
                  class="h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
            <?php foreach ($SORT_OPTIONS as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $q['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-zinc-300 px-4 text-sm font-semibold"><?= e(t('inv_go')) ?></button></noscript>
        </form>

        <?php if ($result['items'] === []): ?>
          <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 p-10 text-center">
            <?= icon('car', 'h-10 w-10 text-zinc-400') ?>
            <h2 class="text-lg font-semibold"><?= e(t('inv_nomatch_t')) ?></h2>
            <p class="text-sm text-zinc-500"><?= e(t('inv_nomatch_s')) ?></p>
            <a href="<?= e(url('/inventory')) ?>" class="mt-1 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 px-4 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('inv_clear')) ?></a>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($result['items'] as $v) { ($renderVehicleCard)($v); } ?>
          </div>

          <nav class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="<?= e(t('inv_pagination_aria')) ?>">
            <p class="text-sm text-zinc-500"><?= e(t('inv_showing', ['n' => count($result['items']), 't' => (int) $result['total'], 'p' => (int) $result['page'], 'pt' => $totalPages])) ?></p>
            <div class="flex items-center gap-2">
              <?php if ($result['page'] > 1): ?>
                <a href="<?= e($invUrl(['page' => (string) ($result['page'] - 1)], true)) ?>"
                   class="inline-flex min-h-11 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand">
                  <?= icon('chevron-left', 'h-4 w-4') ?> <?= e(t('inv_prev')) ?>
                </a>
              <?php else: ?>
                <span class="inline-flex min-h-11 cursor-not-allowed items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-4 text-sm font-semibold text-zinc-400" aria-disabled="true">
                  <?= icon('chevron-left', 'h-4 w-4') ?> <?= e(t('inv_prev')) ?>
                </span>
              <?php endif; ?>
              <?php if ($result['page'] < $totalPages): ?>
                <a href="<?= e($invUrl(['page' => (string) ($result['page'] + 1)], true)) ?>"
                   class="inline-flex min-h-11 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand">
                  <?= e(t('inv_next')) ?> <?= icon('chevron-right', 'h-4 w-4') ?>
                </a>
              <?php else: ?>
                <span class="inline-flex min-h-11 cursor-not-allowed items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-4 text-sm font-semibold text-zinc-400" aria-disabled="true">
                  <?= e(t('inv_next')) ?> <?= icon('chevron-right', 'h-4 w-4') ?>
                </span>
              <?php endif; ?>
            </div>
          </nav>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
