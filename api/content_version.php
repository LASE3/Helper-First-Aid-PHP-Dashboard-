<?php

declare(strict_types=1);
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $stmt = $pdo->query("SELECT content_version, updated_at FROM content_meta WHERE id = 1");
    $data = $stmt->fetch();

    if (!$data) {
        echo json_encode([
            "success" => false,
            "message" => "No content version found"
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "version" => (int)$data['content_version'],
        "updated_at" => $data['updated_at']
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
}
?>