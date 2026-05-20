<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $catStmt = $pdo->query("
        SELECT
            CODE AS code,
            name_en,
            name_ar,
            urgency_level,
            COALESCE(icon_key, '') AS icon_key,
            sort_order
        FROM categories
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");

    $stepStmt = $pdo->query("
        SELECT
            category_code,
            step_no,
            COALESCE(title_en, '') AS title_en,
            COALESCE(title_ar, '') AS title_ar,
            COALESCE(body_en, '') AS body_en,
            COALESCE(body_ar, '') AS body_ar,
            COALESCE(warning_en, '') AS warning_en,
            COALESCE(warning_ar, '') AS warning_ar,
            COALESCE(image_path, '') AS image_path,
            COALESCE(image_path, '') AS image_asset,
            COALESCE(updated_at, NOW()) AS updated_at
        FROM guidance_steps
        WHERE is_active = 1
        ORDER BY category_code ASC, step_no ASC, id ASC
    ");

    echo json_encode([
        'success' => true,
        'categories' => $catStmt->fetchAll(PDO::FETCH_ASSOC),
        'steps' => $stepStmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
