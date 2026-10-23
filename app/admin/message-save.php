<?php
/**
 * Admin — POST /admin/message-save (update contact message status)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$id = trim((string) ($_POST['id'] ?? ''));
if ($id === '') {
    flash('error', 'Message is required.');
    redirect('/admin/messages');
}

$message = db_one('SELECT id FROM contact_messages WHERE id = ?', [$id]);
if ($message === null) {
    flash('error', 'Message not found.');
    redirect('/admin/messages');
}

[$newStatus, $err] = v_enum('status', $_POST, MESSAGE_STATUSES);
if ($err !== null || $newStatus === null) {
    flash('error', 'Invalid status.');
    redirect('/admin/messages');
}

db_exec('UPDATE contact_messages SET status = ? WHERE id = ?', [$newStatus, $id]);

audit_log($staff['id'], $staff['name'], 'CONTACT_UPDATE', 'ContactMessage', $id, "status -> {$newStatus}");

flash('ok', 'Message updated.');
back('/admin/messages');
