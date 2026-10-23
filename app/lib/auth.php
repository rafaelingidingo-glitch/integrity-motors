<?php
/**
 * Staff authentication — native PHP sessions.
 *
 * Parity notes vs the Next.js app:
 * - Cookie `iac_session`, 12 h lifetime, HttpOnly, SameSite=Lax, Secure when HTTPS.
 * - Passwords: password_hash()/password_verify() (bcrypt). The SQLite data was
 *   seeded with Next.js scrypt hashes, so the migration script re-seeds staff
 *   passwords with bcrypt (documented in DEPLOY.md) — nothing else changes.
 * - current_staff() re-checks `is_active` on every request (disabled accounts
 *   are logged out immediately).
 */

declare(strict_types=1);

function current_staff(): ?array
{
    static $cached = false;
    static $staff  = null;
    if ($cached) {
        return $staff;
    }
    $cached = true;

    $id = $_SESSION['staff_id'] ?? null;
    if (!is_string($id) || $id === '') {
        return $staff = null;
    }

    $row = db_one('SELECT id, email, name, role, is_active FROM staff_users WHERE id = ?', [$id]);
    if ($row === null || (int) $row['is_active'] !== 1) {
        unset($_SESSION['staff_id']);
        return $staff = null;
    }
    return $staff = $row;
}

function is_staff(): bool
{
    return current_staff() !== null;
}

function is_admin(): bool
{
    return (current_staff()['role'] ?? null) === 'ADMIN';
}

/** Guard: any active staff. Admin pages call require_admin() after this. */
function require_staff(): array
{
    $staff = current_staff();
    if ($staff === null) {
        redirect('/admin/login');
    }
    return $staff;
}

function require_admin(): array
{
    $staff = require_staff();
    if ($staff['role'] !== 'ADMIN') {
        http_response_code(403);
        exit('Admin permission required');
    }
    return $staff;
}

/**
 * Attempt a login. Returns error message or null on success.
 * Rate limit: 10 attempts / 60 s per IP (parity with /api/auth/login).
 */
function attempt_login(string $email, string $password): ?string
{
    if (!rate_limit('auth:login', 10, 60_000)) {
        return 'Too many attempts. Please wait a minute and try again.';
    }

    $email = strtolower(trim($email));
    $user  = db_one('SELECT * FROM staff_users WHERE email = ?', [$email]);

    if ($user === null) {
        audit_log(null, null, 'LOGIN_FAILED', 'StaffUser', null, 'Unknown email: ' . $email);
        return 'Invalid email or password';
    }
    if ((int) $user['is_active'] !== 1) {
        audit_log(null, null, 'LOGIN_FAILED', 'StaffUser', $user['id'], 'Account disabled: ' . $email);
        return 'Invalid email or password';
    }
    if (!password_verify($password, $user['password_hash'])) {
        audit_log(null, null, 'LOGIN_FAILED', 'StaffUser', $user['id'], 'Wrong password: ' . $email);
        return 'Invalid email or password';
    }

    session_regenerate_id(true); // prevent session fixation
    $_SESSION['staff_id'] = $user['id'];
    audit_log($user['id'], $user['name'], 'LOGIN_SUCCESS', 'StaffUser', $user['id'], $user['name'] . ' signed in');
    return null;
}

function logout_staff(): void
{
    $staff = current_staff();
    if ($staff !== null) {
        audit_log($staff['id'], $staff['name'], 'LOGOUT', 'StaffUser', $staff['id'], $staff['name'] . ' signed out');
    }
    unset($_SESSION['staff_id']);
    session_regenerate_id(true);
}
