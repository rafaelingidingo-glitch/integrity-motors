<?php
/**
 * Enquire — GET renders the enquiry form (with optional vehicle summary),
 * POST stores the enquiry, fires the admin + acknowledgement emails (never
 * fatal) and redirects to the ?sent=1 success state. Exact parity with
 * POST /api/enquiries (5/60s rate limit, SOLD + validation rules).
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active      = 'enquire';
$vehicleSlug = trim((string) ($_GET['vehicle'] ?? ''));
$s           = settings();

// ---------------------------------------------------------------------------
// POST handler — create enquiry
// ---------------------------------------------------------------------------
if (request_method() === 'POST') {
    method_must_be('POST');
    csrf_verify();

    if (!rate_limit('enquiries', 5, 60_000)) {
        flash('error', t('enq_flash_rate'));
        redirect($vehicleSlug !== '' ? '/enquire?vehicle=' . rawurlencode($vehicleSlug) : '/enquire');
    }

    // --- validate -----------------------------------------------------------
    [$fullName, $err['fullName']] = v_str('fullName', $_POST, 2, 120);
    [$phone,   $err['phone']]     = v_str('phone', $_POST, 6, 40);
    [$email,   $err['email']]     = v_email('email', $_POST);
    if ($err['email'] === null && $email !== null && mb_strlen($email) > 200) {
        $err['email'] = 'email must be at most 200 characters';
    }
    [$city,    $err['city']]     = v_str('city', $_POST, 2, 120);
    [$address, $err['address']]  = v_str_opt('address', $_POST, 300);
    [$message, $err['message']]   = v_str_opt('message', $_POST, 3000);
    $err = array_filter($err, fn ($e) => $e !== null);

    $errors = [];
    if ($err !== []) {
        $labels = ['fullName' => t('label_fullName'), 'phone' => t('label_phone'), 'email' => t('label_email'), 'city' => t('label_city'), 'address' => t('label_address'), 'message' => t('label_message')];
        foreach ($err as $k => $msg) {
            $errors[$k] = isset($labels[$k]) ? t_err(preg_replace('/^' . preg_quote($k, '/') . '/', $labels[$k], $msg, 1)) : t_err($msg);
        }
    }

    // --- validate vehicleId (must exist + be published, never SOLD) ---------
    $postVehicle  = null;
    $vehicleIdRaw = trim((string) ($_POST['vehicleId'] ?? ''));
    if ($vehicleIdRaw !== '') {
        $postVehicle = vehicle_find(null, $vehicleIdRaw, false);
        if ($postVehicle === null) {
            flash('error', t('enq_flash_missing'));
            redirect('/enquire');
        }
        if ($postVehicle['status'] === 'SOLD') {
            flash('error', t('enq_flash_sold'));
            redirect('/enquire');
        }
    }

    $old = [
        'fullName' => trim((string) ($_POST['fullName'] ?? '')),
        'phone'    => trim((string) ($_POST['phone'] ?? '')),
        'email'    => trim((string) ($_POST['email'] ?? '')),
        'city'     => trim((string) ($_POST['city'] ?? '')),
        'address'  => trim((string) ($_POST['address'] ?? '')),
        'message'  => trim((string) ($_POST['message'] ?? '')),
    ];

    if ($errors !== []) {
        // Re-render the form below with per-field messages + old values.
    } else {
        // --- store ----------------------------------------------------------
        $id  = new_id();
        $now = now_eat();
        $snapshotJson = $postVehicle !== null
            ? vehicle_snapshot($postVehicle, (int) $postVehicle['price'])
            : vehicle_snapshot(null, 0);
        db_exec(
            'INSERT INTO enquiries (id, full_name, email, phone, city, address, message, vehicle_id, vehicle_snapshot, status, assigned_to_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)',
            [$id, $fullName, $email, $phone, $city, $address, $message, $postVehicle['id'] ?? null, $snapshotJson, 'NEW', $now, $now]
        );

        // --- emails (never fatal) -------------------------------------------
        $vehicleLabel = $postVehicle !== null ? vehicle_name($postVehicle) : 'General enquiry';
        try {
            send_email(
                'ENQUIRY_ADMIN',
                admin_notify_email(),
                'New enquiry: ' . $fullName . ' — ' . $vehicleLabel,
                email_enquiry_admin([
                    'fullName'     => $fullName,
                    'phone'        => $phone,
                    'email'        => $email,
                    'city'         => $city,
                    'vehicleName'  => $vehicleLabel,
                    'vehiclePrice' => $postVehicle !== null ? format_tsh((int) $postVehicle['price']) : null,
                    'message'      => $message,
                ])
            );
        } catch (Throwable $e) {
            error_log('[enquiry] admin email failed: ' . $e->getMessage());
        }
        try {
            send_email(
                'ENQUIRY_ACK',
                $email,
                'We received your enquiry — Integrity Autocars',
                email_enquiry_ack($fullName, $vehicleLabel)
            );
        } catch (Throwable $e) {
            error_log('[enquiry] ack email failed: ' . $e->getMessage());
        }

        redirect('/enquire?sent=1&id=' . rawurlencode($id));
    }
}

// ---------------------------------------------------------------------------
// GET — resolve the optional vehicle (public rules) and render
// ---------------------------------------------------------------------------
$vehicle  = $vehicleSlug !== '' ? vehicle_find($vehicleSlug, null, false) : null;
$vehicleMissing = $vehicle === null && $vehicleSlug !== '';
$isSoldVehicle  = $vehicle !== null && $vehicle['status'] === 'SOLD'; // staff preview only — public gets null above

$sentId = ($_GET['sent'] ?? '') === '1' ? trim((string) ($_GET['id'] ?? '')) : '';
$showSuccess = $sentId !== '' && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $sentId) === 1;

$errors = $errors ?? [];
$old    = $old ?? [];
$defaultValue = ($vehicle !== null && !$isSoldVehicle) ? t('enq_default_msg') : '';
$old += ['fullName' => '', 'phone' => '', 'email' => '', 'city' => '', 'address' => '', 'message' => $defaultValue];

$pageTitle       = t('meta_enquire_title');
$metaDescription = t('meta_enquire_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/enquire')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('enq_aria')) ?>" class="mx-auto w-full max-w-3xl px-4 py-8">
    <?php render_flashes(); ?>

    <h1 class="text-2xl font-bold sm:text-3xl">
      <?php if ($vehicle !== null && !$isSoldVehicle): ?>
        <?= e(t('enq_title_vehicle', ['name' => $vehicle['make'] . ' ' . $vehicle['model']])) ?>
      <?php else: ?>
        <?= e(t('enq_title')) ?>
      <?php endif; ?>
    </h1>
    <p class="mt-1 text-sm text-zinc-500"><?= e(t('enq_sub')) ?></p>

    <div class="mt-6 space-y-6">

      <?php if ($showSuccess): ?>
        <!-- Success state -->
        <div class="flex flex-col items-center gap-4 rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
          <span class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700" aria-hidden="true"><?= icon('check', 'h-8 w-8') ?></span>
          <h2 class="text-2xl font-bold"><?= e(t('enq_success_t')) ?></h2>
          <p class="text-sm text-zinc-500"><?= e(t('enq_success_s')) ?></p>
          <p class="text-xs text-zinc-500"><?= e(t('enq_reference')) ?> <span class="font-mono"><?= e($sentId) ?></span></p>
          <a href="<?= e(wa_link((string) $s['whatsapp_number'], wa_preset('enquiry'))) ?>" target="_blank" rel="noopener noreferrer"
             class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-wa px-6 font-semibold text-white transition-colors hover:bg-wa-dark">
            <?= icon('whatsapp', 'h-4 w-4') ?> <?= e(t('enq_continue_wa')) ?>
          </a>
          <a href="<?= url('/inventory') ?>" class="inline-flex min-h-11 items-center text-sm font-medium text-zinc-500 transition-colors hover:text-brand"><?= e(t('enq_browse_more')) ?></a>
        </div>

      <?php else: ?>

        <?php if ($vehicleMissing): ?>
          <div class="rounded-lg border border-zinc-300 bg-zinc-50 px-4 py-3" role="alert">
            <p class="font-semibold"><?= e(t('enq_missing_t')) ?></p>
            <p class="mt-1 text-sm text-zinc-600">
              <?= e(t('enq_missing_s')) ?>
            </p>
          </div>
        <?php endif; ?>

        <?php if ($vehicle !== null && !$isSoldVehicle): ?>
          <!-- Vehicle summary card -->
          <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="flex flex-col sm:flex-row">
              <?php if (!empty($vehicle['cover'])): ?>
                <img src="<?= e((string) $vehicle['cover']) ?>" alt="<?= e(vehicle_name($vehicle)) ?>" class="aspect-[4/3] w-full object-cover sm:w-56 sm:shrink-0">
              <?php else: ?>
                <div class="flex aspect-[4/3] w-full items-center justify-center text-zinc-400 sm:w-56 sm:shrink-0" role="img" aria-label="<?= e(t('veh_no_photos')) ?>">
                  <?= icon('car', 'h-9 w-9') ?>
                </div>
              <?php endif; ?>
              <div class="flex-1 space-y-1 p-4">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-md bg-green-600 px-2 py-0.5 text-xs font-semibold text-white"><?= e(t('card_available')) ?></span>
                  <span class="rounded-md border border-zinc-300 px-2 py-0.5 text-xs"><?= e(t_cond((string) $vehicle['condition'])) ?></span>
                </div>
                <p class="text-lg font-semibold"><?= e($vehicle['make'] . ' ' . $vehicle['model']) ?> <span class="font-normal text-zinc-500"><?= e((string) $vehicle['year']) ?></span></p>
                <p class="font-bold text-brand"><?= e(format_tsh(effective_price($vehicle))) ?></p>
                <p class="text-xs text-zinc-500"><?= e(t('enq_autonote')) ?></p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($isSoldVehicle): ?>
          <div class="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3" role="alert">
            <p class="font-semibold text-amber-800"><?= e(t('enq_sold_t')) ?></p>
            <p class="mt-1 text-sm text-amber-800/90">
              <?= e(t('enq_sold_s')) ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- Form card -->
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
          <div class="border-b border-zinc-100 p-6 pb-4">
            <h2 class="text-lg font-semibold"><?= e(t('enq_details_t')) ?></h2>
            <p class="mt-0.5 text-sm text-zinc-500"><?= e(t('enq_details_s')) ?></p>
          </div>
          <div class="p-6">
            <?php if ($errors !== []): ?>
              <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <?= e(t('enq_err_summary')) ?>
              </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/enquire' . ($vehicleSlug !== '' ? '?vehicle=' . rawurlencode($vehicleSlug) : ''))) ?>" class="space-y-4" novalidate>
              <?= csrf_field() ?>
              <?php if ($vehicle !== null && !$isSoldVehicle): ?>
                <input type="hidden" name="vehicleId" value="<?= e((string) $vehicle['id']) ?>">
              <?php endif; ?>

              <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                  <label for="enq-name" class="text-sm font-medium"><?= e(t('enq_full_name')) ?></label>
                  <input id="enq-name" name="fullName" required maxlength="120" autocomplete="name" value="<?= e($old['fullName']) ?>"
                         aria-invalid="<?= isset($errors['fullName']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['fullName']) ? 'enq-name-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['fullName'])): ?>
                    <p id="enq-name-error" class="text-xs text-red-600"><?= e($errors['fullName']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="enq-phone" class="text-sm font-medium"><?= e(t('enq_phone')) ?></label>
                  <input id="enq-phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="<?= e(t('enq_phone_ph')) ?>" value="<?= e($old['phone']) ?>"
                         aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['phone']) ? 'enq-phone-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['phone'])): ?>
                    <p id="enq-phone-error" class="text-xs text-red-600"><?= e($errors['phone']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="enq-email" class="text-sm font-medium"><?= e(t('enq_email')) ?></label>
                  <input id="enq-email" name="email" type="email" required maxlength="200" autocomplete="email" value="<?= e($old['email']) ?>"
                         aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['email']) ? 'enq-email-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['email'])): ?>
                    <p id="enq-email-error" class="text-xs text-red-600"><?= e($errors['email']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="enq-city" class="text-sm font-medium"><?= e(t('enq_city')) ?></label>
                  <input id="enq-city" name="city" required maxlength="120" autocomplete="address-level2" placeholder="<?= e(t('enq_city_ph')) ?>" value="<?= e($old['city']) ?>"
                         aria-invalid="<?= isset($errors['city']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['city']) ? 'enq-city-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['city'])): ?>
                    <p id="enq-city-error" class="text-xs text-red-600"><?= e($errors['city']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="space-y-1.5">
                <label for="enq-address" class="text-sm font-medium"><?= e(t('enq_address')) ?></label>
                <input id="enq-address" name="address" maxlength="300" autocomplete="street-address" value="<?= e($old['address']) ?>"
                       class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <?php if (isset($errors['address'])): ?>
                  <p class="text-xs text-red-600"><?= e($errors['address']) ?></p>
                <?php endif; ?>
              </div>

              <div class="space-y-1.5">
                <label for="enq-message" class="text-sm font-medium"><?= e(t('enq_message')) ?></label>
                <textarea id="enq-message" name="message" rows="4" maxlength="3000"
                          class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= e($old['message']) ?></textarea>
                <?php if (isset($errors['message'])): ?>
                  <p class="text-xs text-red-600"><?= e($errors['message']) ?></p>
                <?php endif; ?>
              </div>

              <button type="submit"
                      class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark">
                <?= icon('send', 'h-4 w-4') ?> <?= e(t('enq_submit')) ?>
              </button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>
</main>
<?php require APP_ROOT . '/app/views/layout/site-footer.php'; ?>
<script src="<?= url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
