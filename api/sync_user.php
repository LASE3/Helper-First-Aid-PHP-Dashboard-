<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

function json_response(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_phone(string $raw): string
{
    $phone = trim($raw);
    $phone = str_replace([' ', '-', '(', ')'], '', $phone);
    if (str_starts_with($phone, '+962')) {
        $phone = '0' . substr($phone, 4);
    } elseif (str_starts_with($phone, '00962')) {
        $phone = '0' . substr($phone, 5);
    } elseif (str_starts_with($phone, '962')) {
        $phone = '0' . substr($phone, 3);
    }
    return $phone;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'message' => 'Only POST method is allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(400, ['success' => false, 'message' => 'Invalid JSON body']);
}

$deviceId = trim((string)($data['device_id'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$fullName = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
$phone = normalize_phone((string)($data['phone'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($deviceId === '' || $email === '' || $fullName === '') {
    json_response(400, ['success' => false, 'message' => 'device_id, email, and full_name are required']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'message' => 'Invalid email address']);
}

if ($phone !== '' && !preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, ['success' => false, 'message' => 'Invalid Jordanian phone number', 'debug_phone' => $phone]);
}

try {
    $stmt = $pdo->prepare('SELECT id FROM app_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $passwordSql = '';
    $passwordParam = [];
    if ($password !== '') {
        $passwordSql = ', password_hash = :password_hash';
        $passwordParam[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($existing) {
        $update = $pdo->prepare("\n            UPDATE app_users\n            SET device_id = :device_id,\n                full_name = :full_name,\n                phone = :phone,\n                updated_at = NOW()\n                {$passwordSql}\n            WHERE id = :id\n        ");
        $update->execute([
            ':device_id' => $deviceId,
            ':full_name' => $fullName,
            ':phone' => $phone,
            ':id' => (int)$existing['id'],
        ] + $passwordParam);
        $id = (int)$existing['id'];
    } else {
        $insert = $pdo->prepare('\n            INSERT INTO app_users\n                (device_id, email, phone, password_hash, full_name, language, country_code, emergency_number, ambulance_number, fire_number, created_at, updated_at)\n            VALUES\n                (:device_id, :email, :phone, :password_hash, :full_name, :language, :country_code, :emergency_number, :ambulance_number, :fire_number, NOW(), NOW())\n        ');
        $insert->execute([
            ':device_id' => $deviceId,
            ':email' => $email,
            ':phone' => $phone,
            ':password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            ':full_name' => $fullName,
            ':language' => (string)($data['language'] ?? 'en'),
            ':country_code' => (string)($data['country_code'] ?? '+962'),
            ':emergency_number' => (string)($data['emergency_number'] ?? '911'),
            ':ambulance_number' => (string)($data['ambulance_number'] ?? '193'),
            ':fire_number' => (string)($data['fire_number'] ?? '199'),
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    $load = $pdo->prepare('SELECT id, device_id, email, phone, full_name, age, sex, blood_type, allergies, conditions, medications, notes, language, country_code, emergency_number, ambulance_number, fire_number, created_at, updated_at FROM app_users WHERE id = :id LIMIT 1');
    $load->execute([':id' => $id]);
    $user = $load->fetch(PDO::FETCH_ASSOC);

    json_response(200, [
        'success' => true,
        'message' => 'User synced successfully',
        'user' => $user,
    ]);
} catch (Throwable $e) {
    json_response(500, ['success' => false, 'message' => 'Server error while syncing user', 'error' => $e->getMessage()]);
}
?>