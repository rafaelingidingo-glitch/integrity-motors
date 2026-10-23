<?php
/**
 * Site header — sticky navbar, mobile drawer, globe language switcher.
 * Expects: $active (string) — one of: home | inventory | about | why-us | contact
 */
$s = settings();
$navItems = [
    ['label' => t('nav_home'),      'path' => '/',          'key' => 'home'],
    ['label' => t('nav_inventory'), 'path' => '/inventory', 'key' => 'inventory', 'also' => ['vehicle']],
    ['label' => t('nav_about'),     'path' => '/about',     'key' => 'about'],
    ['label' => t('nav_why'),       'path' => '/why-us',    'key' => 'why-us'],
    ['label' => t('nav_contact'),   'path' => '/contact',   'key' => 'contact', 'also' => ['enquire']],
];
$isActive = function (array $item) use ($active): bool {
    return $active === $item['key'] || in_array($active, $item['also'] ?? [], true);
};
?>
<header class="sticky top-0 z-40 w-full border-b border-zinc-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
    <a href="<?= url('/') ?>" class="flex items-center" aria-label="<?= e((string) $s['business_name']) ?> — home">
      <img src="<?= url('/assets/img/logo-wide.png') ?>" alt="<?= e((string) $s['business_name']) ?> logo" class="logo-light h-12 w-auto" width="176" height="48">
      <img src="<?= url('/assets/img/logo-wide-dark.png') ?>" alt="" aria-hidden="true" class="logo-dark hidden h-12 w-auto" width="176" height="48">
    </a>

    <nav class="hidden items-center gap-1 md:flex" aria-label="Main navigation">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= url($item['path']) ?>"
           class="rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $isActive($item) ? 'bg-zinc-100 text-brand' : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900' ?>">
          <?= e($item['label']) ?>
        </a>
      <?php endforeach; ?>
      <?= lang_switcher() ?>
      <?= theme_toggle_button() ?>
      <a href="<?= url('/admin') ?>"
         class="ml-2 inline-flex min-h-11 items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-dark">
        <?= e(t('nav_login')) ?>
      </a>
    </nav>

    <div class="flex items-center gap-2 md:hidden">
      <?= lang_switcher() ?>
      <?= theme_toggle_button() ?>
      <button id="nav-toggle" type="button"
              class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-200 text-zinc-700"
              aria-controls="mobile-nav" aria-expanded="false" aria-label="<?= e(t('nav_open_menu')) ?>">
        <?= icon('menu', 'h-5 w-5') ?>
      </button>
    </div>
  </div>

  <div id="mobile-nav" class="hidden border-t border-zinc-200 bg-white md:hidden">
    <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3" aria-label="Mobile navigation">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= url($item['path']) ?>"
           class="rounded-lg px-3 py-3 text-base font-medium <?= $isActive($item) ? 'bg-zinc-100 text-brand' : 'text-zinc-700' ?>">
          <?= e($item['label']) ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= url('/admin') ?>" class="mt-1 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 py-3 text-base font-semibold text-white">
        <?= e(t('nav_staff_login')) ?>
      </a>
    </nav>
  </div>
</header>
