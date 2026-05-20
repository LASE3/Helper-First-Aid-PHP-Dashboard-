<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
}

$serverId = isset($_POST['server_id']) ? (int)$_POST['server_id'] : 0;

if ($serverId <= 0) {
    respond(400, [
        'success' => false,
        'message' => 'server_id is required.'
    ]);
}

if (!isset($_FILES['image'])) {
    respond(400, [
        'success' => false,
        'message' => 'image file is required.'
    ]);
}

$image = $_FILES['image'];

if ($image['error'] !== UPLOAD_ERR_OK) {
    respond(400, [
        'success' => false,
        'message' => 'Image upload failed.',
        'error_code' => $image['error']
    ]);
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $image['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($mimeType, $allowedTypes)) {
    respond(400, [
        'success' => false,
        'message' => 'Invalid image type. Only JPG, PNG, and WEBP are allowed.'
    ]);
}

$maxSize = 5 * 1024 * 1024; // 5 MB

if ($image['size'] > $maxSize) {
    respond(400, [
        'success' => false,
        'message' => 'Image is too large. Max size is 5MB.'
    ]);
}

try {
    $checkIncident = $pdo->prepare("
        SELECT id 
        FROM incidents 
        WHERE id = :id 
        LIMIT 1
    ");

    $checkIncident->execute([
        ':id' => $serverId
    ]);

    $incident = $checkIncident->fetch();

    if (!$incident) {
        respond(404, [
            'success' => false,
            'message' => 'Incident not found.'
        ]);
    }

    $uploadDir = __DIR__ . '/../uploads/incidents/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $extension = $allowedTypes[$mimeType];

    $fileName = 'incident_' . $serverId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($image['tmp_name'], $targetPath)) {
        respond(500, [
            'success' => false,
            'message' => 'Failed to save uploaded image.'
        ]);
    }

    $relativePath = 'uploads/incidents/' . $fileName;

    $stmt = $pdo->prepare("
        INSERT INTO incident_images (
            incident_id,
            image_path,
            original_name,
            uploaded_at
        ) VALUES (
            :incident_id,
            :image_path,
            :original_name,
            NOW()
        )
    ");

    $stmt->execute([
        ':incident_id' => $serverId,
        ':image_path' => $relativePath,
        ':original_name' => $image['name'] ?? null
    ]);

    respond(200, [
        'success' => true,
        'message' => 'Image uploaded successfully.',
        'data' => [
            'image_id' => (int)$pdo->lastInsertId(),
            'incident_id' => $serverId,
            'image_path' => $relativePath
        ]
    ]);
} catch (Throwable $e) {
    respond(500, [
        'success' => false,
        'message' => 'Server error while uploading image.'
    ]);
}
?>