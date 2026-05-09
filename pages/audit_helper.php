<?php
declare(strict_types=1);

function log_admin_action(
    PDO $pdo,
    string $action,
    string $targetType,
    ?int $targetId = null,
    ?string $details = null
): void {
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
}

?>