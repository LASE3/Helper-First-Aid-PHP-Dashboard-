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

$stmt = $pdo->query("
    SELECT 
        s.id,
        s.description,
        s.step_number,
        s.image,
        c.name_en AS category
    FROM steps s
    LEFT JOIN categories c ON s.category_id = c.id
    ORDER BY s.category_id, s.step_number
");

$steps = $stmt->fetchAll();

echo json_encode([
    "steps" => $steps
]);

try {
    $stmt = $pdo->query("
        SELECT 
            steps.id,
            steps.category_id,
            categories.name_en AS category_name,
            steps.step_number,
            steps.description,
            steps.image
        FROM steps
        LEFT JOIN categories 
            ON steps.category_id = categories.id
        ORDER BY steps.category_id ASC, steps.step_number ASC
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