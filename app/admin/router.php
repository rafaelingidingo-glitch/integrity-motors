<?php
/**
 * Admin router — dispatches /admin/* paths after authentication checks.
 * Every handler itself enforces require_staff()/require_admin().
 */

declare(strict_types=1);

$path  = current_path();
$ADMIN = APP_ROOT . '/app/admin';

// Publicly reachable admin pages (no session needed)
match (true) {
    $path === '/admin'                      => require $ADMIN . '/dashboard.php',
    $path === '/admin/login'                => require $ADMIN . '/login.php',
    $path === '/admin/logout'               => require $ADMIN . '/logout.php',
    $path === '/admin/forgot-password'      => require $ADMIN . '/forgot-password.php',
    $path === '/admin/reset-password'       => require $ADMIN . '/reset-password.php',
    // Staff (ADMIN + STAFF)
    $path === '/admin/vehicles'             => require $ADMIN . '/vehicles.php',
    $path === '/admin/vehicle-edit'         => require $ADMIN . '/vehicle-edit.php',
    $path === '/admin/vehicle-save'         => require $ADMIN . '/vehicle-save.php',
    $path === '/admin/vehicle-image'        => require $ADMIN . '/vehicle-image.php',
    $path === '/admin/vehicle-delete'       => require $ADMIN . '/vehicle-delete.php',
    $path === '/admin/enquiries'            => require $ADMIN . '/enquiries.php',
    $path === '/admin/enquiry-save'         => require $ADMIN . '/enquiry-save.php',
    $path === '/admin/enquiry-note'         => require $ADMIN . '/enquiry-note.php',
    $path === '/admin/enquiry-invoice'      => require $ADMIN . '/enquiry-invoice.php',
    $path === '/admin/messages'             => require $ADMIN . '/messages.php',
    $path === '/admin/message-save'         => require $ADMIN . '/message-save.php',
    $path === '/admin/invoices'             => require $ADMIN . '/invoices.php',
    $path === '/admin/invoice-edit'         => require $ADMIN . '/invoice-edit.php',
    $path === '/admin/invoice-save'         => require $ADMIN . '/invoice-save.php',
    $path === '/admin/invoice-share'        => require $ADMIN . '/invoice-share.php',
    $path === '/admin/invoice-pdf'          => require $ADMIN . '/invoice-pdf.php',
    $path === '/admin/offers'               => require $ADMIN . '/offers.php',
    $path === '/admin/offer-save'           => require $ADMIN . '/offer-save.php',
    $path === '/admin/offer-toggle'         => require $ADMIN . '/offer-toggle.php',
    $path === '/admin/offer-pdf'            => require $ADMIN . '/offer-pdf.php',
    // ADMIN only
    $path === '/admin/staff'                => require $ADMIN . '/staff.php',
    $path === '/admin/staff-save'           => require $ADMIN . '/staff-save.php',
    $path === '/admin/settings'             => require $ADMIN . '/settings.php',
    $path === '/admin/settings-save'        => require $ADMIN . '/settings-save.php',
    $path === '/admin/audit'                => require $ADMIN . '/audit.php',
    $path === '/admin/emails'               => require $ADMIN . '/emails.php',
    default                                 => (function () { http_response_code(404); exit('Not found'); })(),
};
