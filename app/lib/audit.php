<?php
/**
 * Audit logging — same action vocabulary as the Next.js app.
 */

declare(strict_types=1);

function audit_log(
    ?string $staffId,
    ?string $staffName,
    string $action,
    string $entity,
    ?string $entityId = null,
    ?string $detail = null
): void {
    try {
        db_exec(
            'INSERT INTO audit_logs (id, staff_id, staff_name, action, entity, entity_id, detail, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [new_id(), $staffId, $staffName, $action, $entity, $entityId, $detail, now_eat()]
        );
    } catch (Throwable $e) {
        error_log('[audit] write failed: ' . $e->getMessage());
    }
}
