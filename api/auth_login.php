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

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON body"
    ]);
    exit;
}

$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            device_id,
            email,
            phone,
            password_hash,
            profile_image_path,
            full_name,
            age,
            sex,
            blood_type,
            allergies,
            conditions,
            medications,
            notes,
            questionnaire_json,
            language,
            country_code,
            emergency_number,
            ambulance_number,
            fire_number,
            created_at,
            updated_at
        FROM app_users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        empty($user['password_hash']) ||
        !password_verify($password, $user['password_hash'])
    ) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    unset($user['password_hash']);

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => $user
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
    exit;
}

?>