<?php
/**
 * Public invoice page — opened by CUSTOMERS via the share link an admin sends
 * over WhatsApp: /invoice/<id>?t=<share_token>.
 * ?t=<token>&pdf=1 streams the branded PDF inline (before any HTML output).
 * A wrong/missing token renders an "Invoice link not valid" card.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active  = '';
$invoice = db_one(
    'SELECT * FROM invoices WHERE id = ? AND share_token IS NOT NULL',
    [$GLOBALS['invoice_id'] ?? '']
);
$token = (string) ($_GET['t'] ?? '');
$tokenOk = $invoice !== null && hash_equals((string) $invoice['share_token'], $token);
if (!$tokenOk) {
    http_response_code(404); // share link missing/unknown — SPA rendered the same error state in-page
}

// ---------------------------------------------------------------------------
// PDF stream — must happen BEFORE any HTML output. invoice_pdf() exits.
// ---------------------------------------------------------------------------
if ($tokenOk && ($_GET['pdf'] ?? '') === '1') {
    invoice_pdf($invoice, false); // never returns
}

$s        = settings();
$biz      = pdf_business(); // name / legalName / phone / email / address / city
$snap     = $tokenOk ? snapshot_decode((string) $invoice['vehicle_snapshot']) : [];
$seq      = $tokenOk ? invoice_seq((string) $invoice['number']) : '';
$number   = $tokenOk ? (string) $invoice['number'] : '';
$discount = $tokenOk ? (int) $invoice['discount'] : 0;
$charges  = $tokenOk ? (int) $invoice['additional_charges'] : 0;
$total    = $tokenOk ? (int) $invoice['total'] : 0;

$PAYMENT_BADGES = [
    'UNPAID'  => 'bg-red-100 text-red-700',
    'PARTIAL' => 'bg-amber-100 text-amber-700',
    'PAID'    => 'bg-green-100 text-green-700',
];
$paymentBadge = $tokenOk ? ($PAYMENT_BADGES[(string) $invoice['payment_status']] ?? 'bg-zinc-200 text-zinc-700') : '';

$waContactHref = wa_link('255756428835', wa_preset('invoice_bad'));
$waAskHref     = $tokenOk
    ? wa_link((string) $s['whatsapp_number'], wa_preset('invoice', ['number' => $number]))
    : '';

$pageTitle       = $tokenOk ? t('inv_word') . ' ' . $number . ' | Integrity Autocars' : t('inv_word') . ' | Integrity Autocars';
$metaDescription = 'Invoice from Integrity Autocars.';
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
<?php if (!$tokenOk): ?>

  <section class="mx-auto w-full max-w-xl px-4 py-20 text-center">
    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand" aria-hidden="true"><?= icon('alert-triangle', 'h-6 w-6') ?></span>
    <h1 class="mt-4 text-2xl font-bold"><?= e(t('inv_bad_t')) ?></h1>
    <p class="mt-2 text-sm text-zinc-500"><?= e(t('inv_bad_s')) ?></p>
    <p class="mt-1 text-sm text-zinc-500"><?= e(t('inv_bad_s2')) ?></p>
    <a href="<?= e($waContactHref) ?>" target="_blank" rel="noopener noreferrer"
       class="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-wa px-6 font-semibold text-white transition-colors hover:bg-wa-dark">
      <?= icon('whatsapp', 'h-4 w-4') ?> <?= e(t('inv_bad_wa')) ?>
    </a>
  </section>

<?php else: ?>

  <section aria-label="<?= e(t('inv_bad_aria', ['number' => $number])) ?>" class="mx-auto w-full max-w-2xl px-4 py-8 sm:py-12">
    <?php render_flashes(); ?>

    <!-- Business header + payment status -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <img src="<?= url('/assets/img/logo.png') ?>" alt="<?= e((string) $s['business_name']) ?> logo" class="h-12 w-12 object-contain">
        <div>
          <p class="text-lg font-bold leading-tight"><?= e((string) $s['business_name']) ?></p>
          <?php if ((string) $s['legal_name'] !== ''): ?>
            <p class="text-xs text-zinc-500"><?= e((string) $s['legal_name']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <span class="rounded-full px-3 py-1 text-xs font-semibold <?= e($paymentBadge) ?>"><?= e(t_pay((string) $invoice['payment_status'])) ?></span>
    </div>

    <!-- Invoice card -->
    <div class="mt-6 rounded-xl border border-zinc-200 bg-white shadow-sm">
      <div class="p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <h1 class="text-2xl font-black tracking-widest">INVOICE</h1>
          <div class="text-right">
            <p class="font-mono text-sm font-bold text-brand">#<?= e($number) ?></p>
            <p class="text-xs text-zinc-500"><?= e(t('inv_issued', ['date' => format_date_dmy((string) $invoice['created_at'])])) ?></p>
          </div>
        </div>
        <div class="mt-3 h-0.5 bg-brand" aria-hidden="true"></div>

        <!-- From / Bill To -->
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500"><?= e(t('inv_from')) ?></p>
            <p class="mt-1.5 text-sm font-semibold"><?= e((string) $biz['legalName']) ?></p>
            <p class="text-sm text-zinc-500"><?= e((string) $biz['phone']) ?></p>
            <p class="text-sm text-zinc-500"><?= e((string) $biz['email']) ?></p>
            <p class="text-sm text-zinc-500"><?= e((string) $biz['city']) ?></p>
          </div>
          <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500"><?= e(t('inv_billto')) ?></p>
            <p class="mt-1.5 text-sm font-semibold"><?= e((string) $invoice['customer_name']) ?></p>
            <p class="text-sm text-zinc-500"><?= e((string) $invoice['customer_phone']) ?></p>
            <?php if ((string) $invoice['customer_email'] !== ''): ?>
              <p class="break-all text-sm text-zinc-500"><?= e((string) $invoice['customer_email']) ?></p>
            <?php endif; ?>
            <?php if ((string) $invoice['customer_city'] !== ''): ?>
              <p class="text-sm text-zinc-500"><?= e((string) $invoice['customer_city']) ?></p>
            <?php endif; ?>
            <?php if ((string) ($invoice['customer_address'] ?? '') !== ''): ?>
              <p class="text-sm text-zinc-500"><?= e((string) $invoice['customer_address']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Items -->
        <div class="mt-6 overflow-hidden rounded-lg border border-zinc-200">
          <div class="grid grid-cols-[1fr_auto] gap-2 bg-zinc-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            <span><?= e(t('inv_desc')) ?></span>
            <span class="text-right"><?= e(t('inv_amount')) ?></span>
          </div>
          <div class="grid grid-cols-[1fr_auto] gap-2 border-t border-zinc-200 px-3 py-3 text-sm">
            <div class="min-w-0">
              <p><?= e((string) ($snap['name'] ?? 'Vehicle')) ?></p>
              <?php if (!empty($snap['chassisNumber'])): ?>
                <p class="mt-0.5 text-xs text-zinc-500"><?= e(t('inv_chasis')) ?> <?= e((string) $snap['chassisNumber']) ?></p>
              <?php endif; ?>
            </div>
            <span class="text-right font-medium"><?= e(format_tsh((int) $invoice['price_at_issue'])) ?></span>
          </div>
          <?php if ($discount > 0): ?>
            <div class="grid grid-cols-[1fr_auto] gap-2 border-t border-zinc-200 px-3 py-2.5 text-sm">
              <span class="text-zinc-500"><?= e(t('inv_discount')) ?></span>
              <span class="text-right">-<?= e(format_tsh($discount)) ?></span>
            </div>
          <?php endif; ?>
          <?php if ($charges > 0): ?>
            <div class="grid grid-cols-[1fr_auto] gap-2 border-t border-zinc-200 px-3 py-2.5 text-sm">
              <span class="text-zinc-500"><?= e(t('inv_charges')) ?></span>
              <span class="text-right"><?= e(format_tsh($charges)) ?></span>
            </div>
          <?php endif; ?>
          <div class="grid grid-cols-[1fr_auto] gap-2 border-t border-zinc-200 bg-zinc-50/60 px-3 py-3 text-base font-bold">
            <span><?= e(t('inv_total')) ?></span>
            <span class="text-right"><?= e(format_tsh($total)) ?></span>
          </div>
        </div>

        <!-- Notes -->
        <div class="mt-6">
          <p class="text-sm font-bold tracking-wide"><?= e(t('inv_notes')) ?></p>
          <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-zinc-500"><?= e(trim((string) $invoice['notes']) !== '' ? (string) $invoice['notes'] : '—') ?></p>
        </div>

        <hr class="my-5 border-zinc-200">

        <!-- Actions -->
        <div class="flex flex-col gap-2 sm:flex-row">
          <a href="<?= e(url('/invoice/' . $invoice['id'] . '?t=' . rawurlencode($token) . '&pdf=1')) ?>" target="_blank" rel="noopener noreferrer"
             class="inline-flex min-h-12 flex-1 items-center justify-center gap-1.5 rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark">
            <?= icon('download', 'h-4 w-4') ?> <?= e(t('inv_pdf')) ?>
          </a>
          <a href="<?= e($waAskHref) ?>" target="_blank" rel="noopener noreferrer"
             class="inline-flex min-h-12 flex-1 items-center justify-center gap-1.5 rounded-lg border border-wa px-6 font-semibold text-green-700 transition-colors hover:bg-wa/10">
            <?= icon('whatsapp', 'h-4 w-4') ?> <?= e(t('inv_ask')) ?>
          </a>
        </div>
      </div>
    </div>

    <p class="mt-6 flex items-center justify-center gap-1.5 text-center text-xs text-zinc-500">
      <?= icon('check', 'h-3.5 w-3.5 text-green-600') ?>
      <?= e(t('inv_issued_by', ['biz' => (string) $s['business_name']])) ?>
      <span class="inline-flex items-center gap-1">
        <?= icon('clock', 'h-3 w-3') ?> <?= e(format_date_dmy((string) $invoice['created_at'])) ?>
      </span>
    </p>
  </section>

<?php endif; ?>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
