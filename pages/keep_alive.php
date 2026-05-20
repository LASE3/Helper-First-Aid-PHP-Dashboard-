<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$_SESSION['last_activity'] = time();

echo json_encode(['success' => true, 'message' => 'Session restored']);
?>