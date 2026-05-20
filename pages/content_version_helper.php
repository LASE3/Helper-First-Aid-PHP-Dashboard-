<?php

declare(strict_types=1);

function bump_content_version(PDO $pdo, ?int $adminId = null, string $summary = 'Content updated'): void
{
    try {
        $pdo->beginTransaction();

        $pdo->exec("
            UPDATE content_meta 
            SET content_version = content_version + 1 
            WHERE id = 1
        ");

        $stmt = $pdo->query("
            SELECT content_version 
            FROM content_meta 
            WHERE id = 1
        ");

        $newVersion = (int)$stmt->fetchColumn();

        $insert = $pdo->prepare("
            INSERT INTO content_versions 
            (version_number, change_summary, published_by_admin_id)
            VALUES (?, ?, ?)
        ");

        $insert->execute([
            $newVersion,
            $summary,
            $adminId
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
?>