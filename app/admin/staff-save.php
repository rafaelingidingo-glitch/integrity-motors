<?php
/**
 * Admin — POST /admin/staff-save (ADMIN only)
 * action=create : new staff account (bcrypt, dup-email guard)
 * action=update : rename / change role / reset password
 * action=toggle : activate / deactivate an account (never your own)
 */

declare(strict_types=1);

method_must_be('POST');
csrf_verify();
$staff = require_admin();

$action = (string) ($_POST['action'] ?? '');

// ------------------------------------------------------------------ create
if ($action === 'create') {
    [$name, $e1] = v_str('name', $_POST, 1, 120);
    [$email, $e2] = v_email('email', $_POST, true);
    $password = (string) ($_POST['password'] ?? '');
    [$role, $e3] = v_enum('role', $_POST, STAFF_ROLES, 'STAFF');

    $errors = [];
    foreach (['name' => $e1, 'email' => $e2, 'role' => $e3] as $field => $fieldErr) {
        if ($fieldErr !== null) {
            $errors[$field] = $fieldErr;
        }
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'password must be at least 8 characters';
    }

    if ($errors !== [] || $name === null || $email === null || $role === null) {
        flash('error', $errors !== [] ? validate_message($errors) : 'Please fill in all required fields.');
        redirect('/admin/staff?new=1');
    }

    $dup = db_scalar('SELECT COUNT(*) FROM staff_users WHERE LOWER(email) = LOWER(?)', [$email]);
    if ((int) $dup > 0) {
        flash('error', 'An account with that email already exists.');
        redirect('/admin/staff?new=1');
    }

    $newId = new_id();
    db_exec(
        'INSERT INTO staff_users (id, email, name, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
        [$newId, $email, $name, password_hash($password, PASSWORD_BCRYPT), $role, now_eat(), now_eat()]
    );

    audit_log($staff['id'], $staff['name'], 'STAFF_CREATED', 'StaffUser', $newId, "Created {$name} <{$email}> ({$role})");

    flash('ok', "Staff account created for {$name}.");
    redirect('/admin/staff');
}

// ------------------------------------------------------------------ update
if ($action === 'update') {
    $userId = trim((string) ($_POST['staff_id'] ?? ''));
    $user   = $userId !== '' ? db_one('SELECT * FROM staff_users WHERE id = ?', [$userId]) : null;
    if ($user === null) {
        flash('error', 'Staff account not found.');
        redirect('/admin/staff');
    }

    [$name, $e1] = v_str('name', $_POST, 1, 120);
    [$role, $e2] = v_enum('role', $_POST, STAFF_ROLES);
    $newPassword = (string) ($_POST['newPassword'] ?? '');

    $errors = [];
    foreach (['name' => $e1, 'role' => $e2] as $field => $fieldErr) {
        if ($fieldErr !== null) {
            $errors[$field] = $fieldErr;
        }
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors['newPassword'] = 'new password must be at least 8 characters';
    }
    if ($errors !== [] || $name === null || $role === null) {
        flash('error', $errors !== [] ? validate_message($errors) : 'Please fill in all required fields.');
        redirect('/admin/staff?edit=' . rawurlencode($userId));
    }

    $changes = [];
    if ($name !== $user['name']) {
        $changes[] = 'name';
    }
    if ($role !== $user['role']) {
        $changes[] = 'role';
    }
    if ($newPassword !== '') {
        $changes[] = 'password';
    }

    // Business-continuity guard: never demote the last active ADMIN — that
    // would leave the admin area without any admin account.
    if ($user['role'] === 'ADMIN' && $role === 'STAFF') {
        $activeAdmins = (int) db_scalar(
            "SELECT COUNT(*) FROM staff_users WHERE role = 'ADMIN' AND is_active = 1 AND id != ?",
            [$userId]
        );
        if ($activeAdmins === 0) {
            flash('error', 'Cannot change this role — this is the last active admin account.');
            redirect('/admin/staff?edit=' . rawurlencode($userId));
        }
    }

    if ($newPassword !== '') {
        db_exec(
            'UPDATE staff_users SET name = ?, role = ?, password_hash = ?, updated_at = ? WHERE id = ?',
            [$name, $role, password_hash($newPassword, PASSWORD_BCRYPT), now_eat(), $userId]
        );
    } else {
        db_exec(
            'UPDATE staff_users SET name = ?, role = ?, updated_at = ? WHERE id = ?',
            [$name, $role, now_eat(), $userId]
        );
    }

    audit_log(
        $staff['id'],
        $staff['name'],
        'STAFF_UPDATED',
        'StaffUser',
        $userId,
        'Updated ' . $name . ' <' . $user['email'] . '>: ' . ($changes !== [] ? implode(', ', $changes) : 'no changes')
    );

    flash('ok', 'Staff account updated.');
    redirect('/admin/staff');
}

// ------------------------------------------------------------------ toggle
if ($action === 'toggle') {
    $userId = trim((string) ($_POST['staff_id'] ?? ''));
    $user   = $userId !== '' ? db_one('SELECT * FROM staff_users WHERE id = ?', [$userId]) : null;
    if ($user === null) {
        flash('error', 'Staff account not found.');
        redirect('/admin/staff');
    }

    if ($userId === $staff['id']) {
        flash('error', 'You cannot deactivate your own account.');
        redirect('/admin/staff');
    }

    $newActive = (int) $user['is_active'] === 1 ? 0 : 1;
    db_exec('UPDATE staff_users SET is_active = ?, updated_at = ? WHERE id = ?', [$newActive, now_eat(), $userId]);

    audit_log(
        $staff['id'],
        $staff['name'],
        'STAFF_DISABLED',
        'StaffUser',
        $userId,
        ($newActive === 1 ? 'Reactivated ' : 'Deactivated ') . $user['name'] . ' <' . $user['email'] . '>'
    );

    flash('ok', ($newActive === 1 ? 'Account reactivated.' : 'Account deactivated.'));
    redirect('/admin/staff');
}

flash('error', 'Unknown action.');
redirect('/admin/staff');
