<?php
/**
 * Admin shell — header, topbar and sidebar.
 * Expects: $staff (from require_staff()/require_admin()), $admin_title (string),
 *          $admin_active (string key: dashboard|vehicles|enquiries|messages|
 *          invoices|offers|staff|settings|audit|login...)
 */
$s = settings();
$navGroups = [
    ['Management', [
        ['dashboard',  'Dashboard',  '/admin',               'layout-dashboard'],
        ['vehicles',   'Vehicles',   '/admin/vehicles',      'package'],
        ['enquiries',  'Enquiries',  '/admin/enquiries',     'inbox'],
        ['messages',   'Messages',   '/admin/messages',      'mail'],
        ['invoices',   'Invoices',   '/admin/invoices',      'file-text'],
        ['offers',     'Offers',     '/admin/offers',        'ticket'],
    ]],
    ['Administration', [
        ['staff',      'Staff',        '/admin/staff',    'users'],
        ['settings',   'Settings',     '/admin/settings', 'settings'],
        ['audit',      'Activity Log', '/admin/audit',    'scroll-text'],
        ['emails',     'Email Log',    '/admin/emails',   'send'],
    ]],
];
$isAdmin = $staff['role'] === 'ADMIN';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($admin_title) ?> | Integrity Autocars Admin</title>
  <link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
  <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
  <?= theme_boot_script() ?>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-zinc-200 bg-white lg:flex">
    <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-5">
      <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo" class="h-9 w-9" width="36" height="36">
      <div class="leading-tight">
        <p class="text-sm font-bold">Integrity Autocars</p>
        <p class="text-[11px] uppercase tracking-wide text-zinc-500">Staff &amp; Admin Area</p>
      </div>
    </div>
    <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4 scrollbar-slim" aria-label="Admin navigation">
      <?php foreach ($navGroups as $gi => [$groupLabel, $items]): ?>
        <?php if ($gi === 1 && !$isAdmin) continue; ?>
        <div>
          <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-400"><?= e($groupLabel) ?></p>
          <div class="space-y-0.5">
            <?php foreach ($items as [$key, $label, $path, $iconName]): ?>
              <a href="<?= url($path) ?>"
                 class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors <?= $admin_active === $key ? 'bg-brand/10 text-brand' : 'text-zinc-700 hover:bg-zinc-100' ?>">
                <?= icon($iconName, 'h-4 w-4 shrink-0') ?> <?= e($label) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-zinc-200 p-3">
      <!-- POST + CSRF: a plain GET link would allow drive-by logout CSRF -->
      <form method="post" action="<?= url('/admin/logout') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-zinc-600 transition-colors hover:bg-red-50 hover:text-red-700">
          <?= icon('log-out', 'h-4 w-4') ?> Sign out
        </button>
      </form>
    </div>
  </aside>

  <!-- Content column -->
  <div class="flex min-h-screen w-full flex-1 flex-col lg:pl-64">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-zinc-200 bg-white/95 px-4 backdrop-blur lg:px-8">
      <div class="flex items-center gap-3">
        <button id="admin-nav-toggle" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-200 lg:hidden" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Toggle admin menu">
          <?= icon('menu', 'h-5 w-5') ?>
        </button>
        <h1 class="text-base font-semibold sm:text-lg"><?= e($admin_title) ?></h1>
      </div>
      <div class="flex items-center gap-3">
        <?= theme_toggle_button() ?>
        <div class="text-right leading-tight">
          <p class="text-sm font-medium"><?= e((string) $staff['name']) ?></p>
          <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide <?= $isAdmin ? 'bg-brand/10 text-brand' : 'bg-zinc-200 text-zinc-600' ?>"><?= e((string) $staff['role']) ?></span>
        </div>
        <!-- Decorative avatar: hidden on the narrowest phones so the topbar
             never pushes past the viewport (320px devices). -->
        <img src="<?= url('/assets/img/logo.png') ?>" alt="" class="hidden h-9 w-9 rounded-full border border-zinc-200 sm:block" width="36" height="36">
      </div>
    </header>

    <!-- Mobile sidebar drawer -->
    <div id="admin-mobile-nav" class="fixed inset-0 z-40 hidden lg:hidden">
      <div class="absolute inset-0 bg-black/40" data-admin-nav-close></div>
      <aside class="absolute inset-y-0 left-0 flex w-64 flex-col bg-white shadow-xl">
        <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4">
          <span class="text-sm font-bold">Integrity Autocars Admin</span>
          <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500" data-admin-nav-close aria-label="Close menu"><?= icon('x', 'h-5 w-5') ?></button>
        </div>
        <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4 scrollbar-slim" aria-label="Admin navigation">
          <?php foreach ($navGroups as $gi => [$groupLabel, $items]): ?>
            <?php if ($gi === 1 && !$isAdmin) continue; ?>
            <div>
              <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-400"><?= e($groupLabel) ?></p>
              <div class="space-y-0.5">
                <?php foreach ($items as [$key, $label, $path, $iconName]): ?>
                  <a href="<?= url($path) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $admin_active === $key ? 'bg-brand/10 text-brand' : 'text-zinc-700' ?>">
                    <?= icon($iconName, 'h-4 w-4 shrink-0') ?> <?= e($label) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </nav>
        <div class="border-t border-zinc-200 p-3">
          <form method="post" action="<?= url('/admin/logout') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-zinc-600"><?= icon('log-out', 'h-4 w-4') ?> Sign out</button>
          </form>
        </div>
      </aside>
    </div>

    <main class="flex-1 p-4 pb-16 lg:p-8">
      <?php render_flashes(); ?>
