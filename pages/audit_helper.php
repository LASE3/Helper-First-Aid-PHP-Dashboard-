<?php

declare(strict_types=1);

/**
 * Writes an admin action to admin_audit_logs.
 * This helper must never break the dashboard page if audit logging fails.
 */
function log_admin_action(
    PDO $pdo,
    string $action,
    string $targetType,
    ?int $targetId = null,
    ?string $details = null
): void {
    try {
        $adminId = $_SESSION['admin_id'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_logs 
            (admin_id, action, target_type, target_id, details)
            VALUES (:admin_id, :action, :target_type, :target_id, :details)
        ");

        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':details' => $details,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
?>
