<?php
/**
 * Validation helpers — mirror the zod schemas from the Next.js API routes.
 * Each validator returns [value, error]; collect with validate_collect().
 */

declare(strict_types=1);

/** Trimmed string within a length range; $required=true → non-empty. */
function v_str(string $field, array $src, int $min, int $max, bool $required = true): array
{
    $value = trim((string) ($src[$field] ?? ''));
    if ($value === '') {
        return $required ? [null, "$field is required"] : ['', null];
    }
    if (mb_strlen($value) < $min) {
        return [null, "$field must be at least $min characters"];
    }
    if (mb_strlen($value) > $max) {
        return [null, "$field must be at most $max characters"];
    }
    return [$value, null];
}

/** Optional trimmed string (empty string allowed → null). */
function v_str_opt(string $field, array $src, int $max): array
{
    [$value, $err] = v_str($field, $src, 0, $max, false);
    return [$value !== '' ? $value : null, $err];
}

function v_int(string $field, array $src, int $min, int $max): array
{
    $raw = $src[$field] ?? null;
    if (!is_numeric($raw)) {
        return [null, "$field must be a number"];
    }
    $value = (int) $raw;
    if ($value < $min || $value > $max) {
        return [null, "$field must be between $min and $max"];
    }
    return [$value, null];
}

function v_int_opt(string $field, array $src, int $min, int $max): array
{
    $raw = $src[$field] ?? '';
    if ($raw === '' || $raw === null) {
        return [null, null];
    }
    return v_int($field, $src, $min, $max);
}

function v_email(string $field, array $src, bool $required = true): array
{
    $value = strtolower(trim((string) ($src[$field] ?? '')));
    if ($value === '') {
        return $required ? [null, "$field is required"] : [null, null];
    }
    if (mb_strlen($value) > 254 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return [null, "$field must be a valid email address"];
    }
    return [$value, null];
}

function v_enum(string $field, array $src, array $allowed, ?string $default = null): array
{
    $value = trim((string) ($src[$field] ?? ''));
    if ($value === '' && $default !== null) {
        return [$default, null];
    }
    if (!in_array($value, $allowed, true)) {
        return [null, "$field must be one of: " . implode(', ', $allowed)];
    }
    return [$value, null];
}

function v_bool(string $field, array $src, bool $default = false): bool
{
    $value = $src[$field] ?? null;
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }
    return $default;
}

/** Date (Y-m-d) → 'Y-m-d H:i:s' at 00:00:00 EAT; empty → null. */
function v_date_opt(string $field, array $src): array
{
    $raw = trim((string) ($src[$field] ?? ''));
    if ($raw === '') {
        return [null, null];
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return [null, "$field must be a valid date"];
    }
    return [date('Y-m-d H:i:s', $ts), null];
}

/** Collect errors from [value, error] pairs; null value when errors exist. */
function validate_collect(array $pairs): ?array
{
    $values = [];
    $errors = [];
    foreach ($pairs as $key => [$value, $error]) {
        if ($error !== null) {
            $errors[$key] = $error;
        } else {
            $values[$key] = $value;
        }
    }
    return $errors === [] ? $values : null;
}

/** Implode error map into a sentence. */
function validate_message(array $errors): string
{
    return ucfirst(implode('. ', array_map(fn ($k, $v) => str_replace('_', ' ', $k) . ': ' . $v, array_keys($errors), $errors)));
}
