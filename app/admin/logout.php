<?php
/**
 * /admin/logout — POST (with CSRF) only. A plain GET link would let any
 * third-party page log a signed-in admin out with a hidden <img> request.
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();

logout_staff();
flash('ok', 'Signed out.');
redirect('/admin/login');
