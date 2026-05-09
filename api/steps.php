<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$categoryCode = $_GET['category_code'] ?? '';

if ($categoryCode === '') {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'category_code is required'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, category_code, step_order, title, description, image, status
        FROM steps
        WHERE category_code = :category_code
        AND status = 'active'
        ORDER BY step_order ASC, id ASC
    ");

    $stmt->execute([
        ':category_code' => $categoryCode
    ]);

    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch steps'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>