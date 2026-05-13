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
$stmt = $pdo->query("
    SELECT 
        id,
        category_id,
        category_code,
        step_no,
        title_en,
        title_ar,
        body_en,
        body_ar,
        warning_en,
        warning_ar,
        image_path,
        audio_path,
        is_active
    FROM guidance_steps
    WHERE is_active = 1
    ORDER BY category_code ASC, step_no ASC
");

    $steps = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "steps" => $steps
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
}

?>