<?php
/**
 * Admin — POST /admin/enquiry-note (add an internal note to an enquiry)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_staff();

$enquiryId = trim((string) ($_POST['enquiry_id'] ?? ''));
if ($enquiryId === '') {
    flash('error', 'Enquiry is required.');
    redirect('/admin/enquiries');
}

$enquiry = db_one('SELECT id FROM enquiries WHERE id = ?', [$enquiryId]);
if ($enquiry === null) {
    flash('error', 'Enquiry not found.');
    redirect('/admin/enquiries');
}

[$content, $err] = v_str('content', $_POST, 1, 5000);
if ($err !== null || $content === null) {
    flash('error', $err !== null ? validate_message(['content' => $err]) : 'Note is required.');
    redirect('/admin/enquiries?id=' . rawurlencode($enquiryId));
}

db_exec(
    'INSERT INTO enquiry_notes (id, enquiry_id, staff_id, content, created_at) VALUES (?, ?, ?, ?, ?)',
    [new_id(), $enquiryId, $staff['id'], $content, now_eat()]
);

audit_log($staff['id'], $staff['name'], 'ENQUIRY_NOTE_ADD', 'Enquiry', $enquiryId, 'Note added (' . mb_strlen($content) . ' chars)');

flash('ok', 'Note added.');
redirect('/admin/enquiries?id=' . rawurlencode($enquiryId));
