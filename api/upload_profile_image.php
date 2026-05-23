<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit;
}

$deviceId = $_POST['device_id'] ?? null;

if (!$deviceId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "device_id is required"
    ]);
    exit;
}

if (!isset($_FILES['profile_image'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "profile_image file is required"
    ]);
    exit;
}

$file = $_FILES['profile_image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Image upload failed"
    ]);
    exit;
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Only JPG, PNG, and WEBP images are allowed"
    ]);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/profiles/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$extension = $allowedTypes[$mimeType];
$fileName = 'profile_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $deviceId) . '_' . time() . '.' . $extension;

$targetPath = $uploadDir . $fileName;
$dbPath = 'uploads/profiles/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to save image"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE app_users
    SET profile_image_path = :profile_image_path,
        updated_at = NOW()
    WHERE device_id = :device_id
");

$stmt->execute([
    ':profile_image_path' => $dbPath,
    ':device_id' => $deviceId
]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User not found for this device_id"
    ]);
    exit;
}

$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));

echo json_encode([
    "success" => true,
    "message" => "Profile image uploaded successfully",
    "profile_image_path" => $dbPath,
    "profile_image_url" => $baseUrl . "/" . $dbPath
]);
?>