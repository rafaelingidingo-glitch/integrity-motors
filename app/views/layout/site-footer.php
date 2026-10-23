<?php
/**
 * Site footer — 4 columns + newsletter form + bottom bar.
 * Sticky-to-bottom behaviour: page wrapper uses min-h-screen flex flex-col and
 * the <footer> sits after <main class="flex-1"> (see each view file).
 */
$s = settings();
$year = date('Y');
$waDefault = wa_preset('general');
$footerLinks = [
    [t('nav_home'), '/'], [t('nav_inventory'), '/inventory'], [t('nav_about'), '/about'], [t('nav_why'), '/why-us'], [t('nav_contact'), '/contact'],
];
?>
<footer class="mt-auto border-t border-zinc-200 bg-zinc-50 pb-[env(safe-area-inset-bottom)]">
  <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
    <div>
      <img src="<?= url('/assets/img/logo-wide.png') ?>" alt="<?= e((string) $s['business_name']) ?> logo" class="logo-light h-11 w-auto" width="161" height="44" loading="lazy">
      <img src="<?= url('/assets/img/logo-wide-dark.png') ?>" alt="" aria-hidden="true" class="logo-dark hidden h-11 w-auto" width="161" height="44" loading="lazy">
      <p class="mt-3 text-sm leading-relaxed text-zinc-600">
        <?= e(t('footer_tagline')) ?>
      </p>
    </div>

    <nav aria-label="Quick links">
      <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-900"><?= e(t('footer_quick_links')) ?></h3>
      <ul class="mt-3 space-y-2">
        <?php foreach ($footerLinks as [$label, $path]): ?>
          <li><a href="<?= url($path) ?>" class="text-sm text-zinc-600 transition-colors hover:text-brand"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div>
      <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-900"><?= e(t('footer_contact')) ?></h3>
      <ul class="mt-3 space-y-2 text-sm text-zinc-600">
        <li><a href="<?= e(tel_link((string) $s['phone_primary'])) ?>" class="flex items-center gap-2 hover:text-brand"><?= icon('phone', 'h-4 w-4 shrink-0') ?> <?= e((string) $s['phone_primary']) ?></a></li>
        <?php if ((string) ($s['phone_secondary'] ?? '') !== ''): ?>
          <li><a href="<?= e(tel_link((string) $s['phone_secondary'])) ?>" class="flex items-center gap-2 hover:text-brand"><?= icon('phone', 'h-4 w-4 shrink-0') ?> <?= e((string) $s['phone_secondary']) ?></a></li>
        <?php endif; ?>
        <li><a href="mailto:<?= e((string) $s['display_email']) ?>" class="flex items-center gap-2 hover:text-brand"><?= icon('mail', 'h-4 w-4 shrink-0') ?> <?= e((string) $s['display_email']) ?></a></li>
        <li><span class="flex items-center gap-2"><?= icon('map-pin', 'h-4 w-4 shrink-0') ?> <?= e((string) $s['address']) ?></span></li>
      </ul>
      <div class="mt-3 flex items-center gap-2">
        <?php if (!empty($s['tiktok_url'])): ?>
          <a href="<?= e((string) $s['tiktok_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="TikTok" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition-colors hover:text-brand">
            <?= icon('music-2', 'h-4 w-4') ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($s['instagram_url'])): ?>
          <a href="<?= e((string) $s['instagram_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition-colors hover:text-brand">
            <?= icon('instagram', 'h-4 w-4') ?>
          </a>
        <?php endif; ?>
        <a href="<?= e(wa_link((string) $s['whatsapp_number'], $waDefault)) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition-colors hover:bg-wa hover:text-white">
          <?= icon('whatsapp', 'h-4 w-4') ?>
        </a>
      </div>
    </div>

    <div>
      <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-900"><?= e(t('footer_newsletter')) ?></h3>
      <p class="mt-3 text-sm text-zinc-600"><?= e(t('footer_newsletter_blurb')) ?></p>
      <form method="post" action="<?= url('/newsletter') ?>" class="mt-3 flex gap-2">
        <?= csrf_field() ?>
        <label for="footer-newsletter-email" class="sr-only"><?= e(t('footer_email_label')) ?></label>
        <input id="footer-newsletter-email" type="email" name="email" required maxlength="254" placeholder="you@example.com"
               class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        <button type="submit" aria-label="<?= e(t('footer_subscribe')) ?>"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand text-white transition-colors hover:bg-brand-dark">
          <?= icon('send', 'h-4 w-4') ?>
        </button>
      </form>
      <?php if ($flashNewsletter = flash('newsletter')): ?>
        <p class="mt-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-700"><?= e($flashNewsletter) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="border-t border-zinc-200">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-1 px-4 py-4 text-center text-xs text-zinc-500 sm:flex-row sm:text-left">
      <p>&copy; <?= $year ?> <?= e((string) $s['business_name']) ?> (<?= e((string) $s['legal_name']) ?>). <?= e(t('footer_rights')) ?></p>
      <p><?= e(t('footer_design_by')) ?> <a href="https://www.rwextech.co.tz/" target="_blank" rel="noopener noreferrer" class="font-medium text-zinc-700 underline-offset-2 hover:text-brand hover:underline">RwexTech</a></p>
    </div>
  </div>
</footer>
