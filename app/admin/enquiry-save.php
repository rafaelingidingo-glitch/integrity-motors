<?php
/**
 * Admin — POST /admin/enquiry-save
 * action=status : update status (+ assignment when posted together by the
 *                 combined form on the enquiry detail panel)
 * action=assign : update assigned staff only
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

$enquiry = db_one('SELECT * FROM enquiries WHERE id = ?', [$enquiryId]);
if ($enquiry === null) {
    flash('error', 'Enquiry not found.');
    redirect('/admin/enquiries');
}

$redirectTo = fn (): never => redirect('/admin/enquiries?id=' . rawurlencode($enquiryId));

// Assignment value shared by both actions ('' = unassigned)
$assignedRaw = array_key_exists('assigned_to_id', $_POST) ? trim((string) $_POST['assigned_to_id']) : null;
$assignedId  = $assignedRaw !== null && $assignedRaw !== '' ? $assignedRaw : null;
if ($assignedId !== null) {
    $assignee = db_one("SELECT id, name FROM staff_users WHERE id = ? AND is_active = 1", [$assignedId]);
    if ($assignee === null) {
        flash('error', 'Selected staff member is not active.');
        $redirectTo();
    }
}

$action = (string) ($_POST['action'] ?? 'status');

if ($action === 'assign') {
    db_exec('UPDATE enquiries SET assigned_to_id = ?, updated_at = ? WHERE id = ?', [
        $assignedId,
        now_eat(),
        $enquiryId,
    ]);
    audit_log(
        $staff['id'],
        $staff['name'],
        'ENQUIRY_UPDATE',
        'Enquiry',
        $enquiryId,
        $assignedId !== null ? 'Assigned to ' . db_scalar('SELECT name FROM staff_users WHERE id = ?', [$assignedId]) : 'Unassigned'
    );
    flash('ok', 'Enquiry updated.');
    $redirectTo();
}

if ($action !== 'status') {
    flash('error', 'Unknown action.');
    $redirectTo();
}

[$newStatus, $err] = v_enum('status', $_POST, ENQUIRY_STATUSES);
if ($err !== null || $newStatus === null) {
    flash('error', 'Invalid status.');
    $redirectTo();
}

// Combined form: status + assignment in one update
db_exec('UPDATE enquiries SET status = ?, assigned_to_id = ?, updated_at = ? WHERE id = ?', [
    $newStatus,
    array_key_exists('assigned_to_id', $_POST) ? $assignedId : $enquiry['assigned_to_id'],
    now_eat(),
    $enquiryId,
]);

audit_log($staff['id'], $staff['name'], 'ENQUIRY_UPDATE', 'Enquiry', $enquiryId, json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE));

flash('ok', 'Enquiry updated.');
$redirectTo();
