<?php
/**
 * Admin — Site settings (ADMIN only). Saves via settings-save.php.
 */

$staff = require_admin();

$admin_title  = 'Settings';
$admin_active = 'settings';

$s = settings();

$inputCls = 'h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20';
$areaCls  = 'w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20';

require APP_ROOT . '/app/views/layout/admin-header.php';
?>
<form method="post" action="<?= url('/admin/settings-save') ?>" class="flex flex-col gap-6">
  <?= csrf_field() ?>

  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Business identity">
    <h2 class="text-lg font-bold text-zinc-900">Business identity</h2>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div>
        <label for="st-business" class="block text-sm font-medium text-zinc-700 mb-1">Business name <span class="text-brand">*</span></label>
        <input id="st-business" name="businessName" type="text" required maxlength="120" value="<?= e((string) $s['business_name']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-motto" class="block text-sm font-medium text-zinc-700 mb-1">Motto <span class="text-brand">*</span></label>
        <input id="st-motto" name="motto" type="text" required maxlength="120" value="<?= e((string) $s['motto']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-legal" class="block text-sm font-medium text-zinc-700 mb-1">Legal name <span class="text-brand">*</span></label>
        <input id="st-legal" name="legalName" type="text" required maxlength="200" value="<?= e((string) $s['legal_name']) ?>" class="<?= $inputCls ?>">
        <p class="mt-1 text-xs text-zinc-500">Printed as the FROM party on invoices and offers.</p>
      </div>
      <div>
        <label for="st-address" class="block text-sm font-medium text-zinc-700 mb-1">Address <span class="text-brand">*</span></label>
        <input id="st-address" name="address" type="text" required maxlength="300" value="<?= e((string) $s['address']) ?>" class="<?= $inputCls ?>">
      </div>
      <div class="lg:col-span-2">
        <label for="st-about" class="block text-sm font-medium text-zinc-700 mb-1">About text</label>
        <textarea id="st-about" name="aboutText" rows="5" maxlength="6000" class="<?= $areaCls ?>"><?= e((string) $s['about_text']) ?></textarea>
      </div>
    </div>
  </section>

  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Contact details">
    <h2 class="text-lg font-bold text-zinc-900">Contact details</h2>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div>
        <label for="st-phone1" class="block text-sm font-medium text-zinc-700 mb-1">Primary phone <span class="text-brand">*</span></label>
        <input id="st-phone1" name="primaryPhone" type="text" required maxlength="40" value="<?= e((string) $s['phone_primary']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-phone2" class="block text-sm font-medium text-zinc-700 mb-1">Secondary phone</label>
        <input id="st-phone2" name="secondaryPhone" type="text" maxlength="40" value="<?= e((string) $s['phone_secondary']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-wa" class="block text-sm font-medium text-zinc-700 mb-1">WhatsApp number <span class="text-brand">*</span></label>
        <input id="st-wa" name="whatsappNumber" type="text" required maxlength="40" value="<?= e((string) $s['whatsapp_number']) ?>" class="<?= $inputCls ?>">
        <p class="mt-1 text-xs text-zinc-500">Used for the floating WhatsApp button and staff share links.</p>
      </div>
      <div>
        <label for="st-email" class="block text-sm font-medium text-zinc-700 mb-1">Display email <span class="text-brand">*</span></label>
        <input id="st-email" name="displayEmail" type="email" required maxlength="200" value="<?= e((string) $s['display_email']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-ig" class="block text-sm font-medium text-zinc-700 mb-1">Instagram URL</label>
        <input id="st-ig" name="instagramUrl" type="url" maxlength="300" value="<?= e((string) $s['instagram_url']) ?>" class="<?= $inputCls ?>">
      </div>
      <div>
        <label for="st-tiktok" class="block text-sm font-medium text-zinc-700 mb-1">TikTok URL <span class="text-zinc-400">(optional)</span></label>
        <input id="st-tiktok" name="tiktokUrl" type="url" maxlength="300" value="<?= e((string) ($s['tiktok_url'] ?? '')) ?>" class="<?= $inputCls ?>">
      </div>
      <div class="lg:col-span-2">
        <label for="st-maps" class="block text-sm font-medium text-zinc-700 mb-1">Google Maps URL</label>
        <input id="st-maps" name="mapsUrl" type="url" maxlength="300" value="<?= e((string) $s['maps_url']) ?>" class="<?= $inputCls ?>">
      </div>
    </div>
  </section>

  <section class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6" aria-label="Invoice notes">
    <h2 class="text-lg font-bold text-zinc-900">Notes &amp; payments instructions</h2>
    <div class="mt-4">
      <label for="st-notes" class="block text-sm font-medium text-zinc-700 mb-1">Invoice notes</label>
      <textarea id="st-notes" name="invoiceNotes" rows="6" maxlength="6000" class="<?= $areaCls ?>"><?= e((string) $s['invoice_notes']) ?></textarea>
      <p class="mt-1 text-xs text-zinc-500">Bank name, account number, account name, payment terms… (printed on every invoice PDF)</p>
      <p class="mt-2 text-xs text-zinc-400">Existing invoices keep the notes they were created with — changes only affect future invoices.</p>
    </div>
  </section>

  <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="note">
    <?= icon('info', 'mr-1 inline h-4 w-4') ?> SOLD vehicles are automatically hidden from the public inventory.
  </div>

  <div class="flex justify-end">
    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-6 text-sm font-semibold text-white hover:bg-brand-dark">
      <?= icon('check', 'h-4 w-4') ?> Save settings
    </button>
  </div>
</form>
<?php require APP_ROOT . '/app/views/layout/admin-footer.php'; ?>
