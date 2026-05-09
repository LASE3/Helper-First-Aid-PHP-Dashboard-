<?php
declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

require_perm('admins.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT version, updated_at FROM content_version WHERE id = 1";
$result = $pdo->query($sql);

if ($row = $result->fetchAll()) {
    echo json_encode($row);
} else {
    echo json_encode(["error" => "No version data found"]);
}

try {
    $stmt = $pdo->query("
        SELECT version, updated_at
        FROM content_version
        WHERE id = 1
    ");

    $version = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($version, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch content version'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

?>