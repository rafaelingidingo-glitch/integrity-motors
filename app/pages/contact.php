<?php
/**
 * Contact — info cards (phone/WhatsApp, email, Instagram, location) + message
 * form. POST stores a contact message, emails the admin (never fatal) and
 * redirects to ?sent=1. Parity with POST /api/contact (5/60s rate limit,
 * phone-or-email refine).
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
}

$active = 'contact';
$s      = settings();

const CONTACT_SUBJECTS = ['General', 'Vehicle Enquiry', 'Auto Parts', 'Other'];

// ---------------------------------------------------------------------------
// POST handler
// ---------------------------------------------------------------------------
if (request_method() === 'POST') {
    method_must_be('POST');
    csrf_verify();

    if (!rate_limit('contact', 5, 60_000)) {
        flash('error', t('ct_flash_rate'));
        redirect('/contact');
    }

    // --- validate -----------------------------------------------------------
    [$name,    $err['name']]    = v_str('name', $_POST, 2, 120);
    [$subject, $err['subject']] = v_str('subject', $_POST, 2, 200);
    [$message, $err['message']] = v_str('message', $_POST, 5, 3000);
    [$phone,   $err['phone']]   = v_str_opt('phone', $_POST, 40);
    [$email,   $err['email']]   = v_email('email', $_POST, false);
    if ($err['email'] === null && $email !== null && mb_strlen($email) > 200) {
        $err['email'] = 'email must be at most 200 characters';
    }
    if (($phone === null || $phone === '') && ($email === null || $email === '')) {
        $err['phone'] = t('err_channel');
    }
    $err = array_filter($err, fn ($e) => $e !== null);

    $errors = [];
    if ($err !== []) {
        $labels = ['name' => t('label_name'), 'subject' => t('label_subject'), 'message' => t('label_message'), 'phone' => t('label_phone'), 'email' => t('label_email')];
        foreach ($err as $k => $msg) {
            $errors[$k] = isset($labels[$k]) ? t_err(preg_replace('/^' . preg_quote($k, '/') . '/', $labels[$k], $msg, 1)) : t_err($msg);
        }
    }

    $old = [
        'name'    => trim((string) ($_POST['name'] ?? '')),
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
        'phone'   => trim((string) ($_POST['phone'] ?? '')),
        'email'   => trim((string) ($_POST['email'] ?? '')),
    ];

    if ($errors !== []) {
        // Re-render the form below with per-field messages + old values.
    } else {
        // --- store ----------------------------------------------------------
        $id = new_id();
        db_exec(
            'INSERT INTO contact_messages (id, name, phone, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $name, $phone, $email, $subject, $message, 'NEW', now_eat()]
        );

        // --- admin email (never fatal) ---------------------------------------
        try {
            send_email(
                'CONTACT_ADMIN',
                admin_notify_email(),
                'Contact form: ' . $subject,
                email_contact_admin([
                    'name'    => $name,
                    'phone'   => $phone,
                    'email'   => $email,
                    'subject' => $subject,
                    'message' => $message,
                ])
            );
        } catch (Throwable $e) {
            error_log('[contact] admin email failed: ' . $e->getMessage());
        }

        redirect('/contact?sent=1');
    }
}

$showSuccess = ($_GET['sent'] ?? '') === '1';
$errors  = $errors ?? [];
$old     = $old ?? [];
$old    += ['name' => '', 'subject' => 'General', 'message' => '', 'phone' => '', 'email' => ''];
if (!in_array($old['subject'], CONTACT_SUBJECTS, true)) {
    $old['subject'] = 'General';
}

$pageTitle       = t('meta_contact_title');
$metaDescription = t('meta_contact_desc');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(app_url('/contact')) ?>">
<link rel="icon" href="<?= url('/assets/img/logo.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
<?= theme_boot_script() ?>
</head>
<body class="bg-white text-zinc-900 antialiased min-h-screen flex flex-col">
<?php require APP_ROOT . '/app/views/layout/site-header.php'; ?>
<main class="flex-1">
  <section aria-label="<?= e(t('ct_aria')) ?>" class="mx-auto w-full max-w-6xl px-4 py-8">
    <?php render_flashes(); ?>

    <div class="mx-auto max-w-2xl space-y-3 text-center">
      <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand"><?= e(t('ct_kicker')) ?></p>
      <h1 class="text-3xl font-extrabold sm:text-4xl"><?= e(t('ct_title')) ?></h1>
      <p class="text-zinc-500"><?= e(t('ct_sub')) ?></p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
      <!-- Info cards -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('phone', 'h-4 w-4 text-brand') ?></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold"><?= e(t('ct_card_phone')) ?></h2>
              <div class="mt-0.5 space-y-0.5 text-sm text-zinc-500">
                <a href="<?= e(tel_link((string) $s['phone_primary'])) ?>" class="block hover:text-brand"><?= e((string) $s['phone_primary']) ?></a>
                <?php if ((string) ($s['phone_secondary'] ?? '') !== ''): ?>
                  <a href="<?= e(tel_link((string) $s['phone_secondary'])) ?>" class="block hover:text-brand"><?= e((string) $s['phone_secondary']) ?></a>
                <?php endif; ?>
              </div>
              <a href="<?= e(wa_link((string) $s['whatsapp_number'], wa_preset('contact_card'))) ?>" target="_blank" rel="noopener noreferrer"
                 class="mt-2 inline-flex min-h-9 items-center gap-1 rounded-lg bg-wa px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-wa-dark">
                <?= icon('whatsapp', 'h-3.5 w-3.5') ?> WhatsApp
              </a>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('mail', 'h-4 w-4 text-brand') ?></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold"><?= e(t('ct_card_email')) ?></h2>
              <a href="mailto:<?= e((string) $s['display_email']) ?>" class="mt-0.5 block break-all text-sm text-zinc-500 hover:text-brand"><?= e((string) $s['display_email']) ?></a>
            </div>
          </div>
        </div>

        <?php if (!empty($s['instagram_url'])): ?>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('instagram', 'h-4 w-4 text-brand') ?></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold"><?= e(t('ct_card_instagram')) ?></h2>
              <a href="<?= e((string) $s['instagram_url']) ?>" target="_blank" rel="noopener noreferrer"
                 class="mt-2 inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 px-2.5 py-1 text-xs font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('ct_open_instagram')) ?></a>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/10"><?= icon('map-pin', 'h-4 w-4 text-brand') ?></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold"><?= e(t('ct_card_location')) ?></h2>
              <p class="mt-0.5 text-sm leading-snug text-zinc-500"><?= e((string) $s['address']) ?></p>
              <?php if ((string) ($s['maps_url'] ?? '') !== ''): ?>
                <a href="<?= e((string) $s['maps_url']) ?>" target="_blank" rel="noopener noreferrer"
                   class="mt-2 inline-flex min-h-9 items-center gap-1 rounded-lg border border-zinc-300 px-2.5 py-1 text-xs font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('ct_directions')) ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Form -->
      <?php if ($showSuccess): ?>
        <div class="flex flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-10 text-center shadow-sm">
          <span class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700" aria-hidden="true"><?= icon('check', 'h-8 w-8') ?></span>
          <h2 class="text-2xl font-bold"><?= e(t('ct_success_t')) ?></h2>
          <p class="text-sm text-zinc-500"><?= e(t('ct_success_s')) ?></p>
          <a href="<?= url('/contact') ?>" class="mt-2 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 px-4 text-sm font-semibold text-zinc-800 transition-colors hover:border-brand hover:text-brand"><?= e(t('ct_send_another')) ?></a>
        </div>
      <?php else: ?>
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
          <div class="border-b border-zinc-100 p-6 pb-4">
            <h2 class="text-lg font-semibold"><?= e(t('ct_form_t')) ?></h2>
            <p class="mt-0.5 text-sm text-zinc-500"><?= e(t('ct_form_s')) ?></p>
          </div>
          <div class="p-6">
            <?php if ($errors !== []): ?>
              <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <?= e(t('ct_err_summary')) ?>
              </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/contact')) ?>" class="space-y-4" novalidate>
              <?= csrf_field() ?>

              <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                  <label for="ct-name" class="text-sm font-medium"><?= e(t('ct_name')) ?></label>
                  <input id="ct-name" name="name" required maxlength="120" autocomplete="name" value="<?= e($old['name']) ?>"
                         aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['name']) ? 'ct-name-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['name'])): ?>
                    <p id="ct-name-error" class="text-xs text-red-600"><?= e($errors['name']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="ct-subject" class="text-sm font-medium"><?= e(t('ct_subject')) ?></label>
                  <select id="ct-subject" name="subject" aria-label="<?= e(t('ct_subject_aria')) ?>"
                          class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <?php foreach (CONTACT_SUBJECTS as $subjectOption): ?>
                      <option value="<?= e($subjectOption) ?>" <?= $old['subject'] === $subjectOption ? 'selected' : '' ?>><?= e(t('subj_' . $subjectOption)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['subject'])): ?>
                    <p class="text-xs text-red-600"><?= e($errors['subject']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="ct-phone" class="text-sm font-medium"><?= e(t('ct_phone')) ?></label>
                  <input id="ct-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= e($old['phone']) ?>"
                         aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['phone']) ? 'ct-phone-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['phone'])): ?>
                    <p id="ct-phone-error" class="text-xs text-red-600"><?= e($errors['phone']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="space-y-1.5">
                  <label for="ct-email" class="text-sm font-medium"><?= e(t('ct_email')) ?></label>
                  <input id="ct-email" name="email" type="email" maxlength="200" autocomplete="email" value="<?= e($old['email']) ?>"
                         aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['email']) ? 'ct-email-error' : null ?>"
                         class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                  <?php if (isset($errors['email'])): ?>
                    <p id="ct-email-error" class="text-xs text-red-600"><?= e($errors['email']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="space-y-1.5">
                <label for="ct-message" class="text-sm font-medium"><?= e(t('ct_message')) ?></label>
                <textarea id="ct-message" name="message" rows="5" required placeholder="<?= e(t('ct_message_ph')) ?>"
                          aria-invalid="<?= isset($errors['message']) ? 'true' : 'false' ?>" aria-describedby="<?= isset($errors['message']) ? 'ct-message-error' : null ?>"
                          class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><?= e($old['message']) ?></textarea>
                <?php if (isset($errors['message'])): ?>
                  <p id="ct-message-error" class="text-xs text-red-600"><?= e($errors['message']) ?></p>
                <?php endif; ?>
              </div>

              <button type="submit"
                      class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark">
                <?= icon('send', 'h-4 w-4') ?> <?= e(t('ct_send')) ?>
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
