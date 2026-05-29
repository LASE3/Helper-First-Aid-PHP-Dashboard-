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

$fullName = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = normalize_phone((string)($data['phone'] ?? ''));
$password = (string)($data['password'] ?? '');
$deviceId = trim((string)($data['device_id'] ?? ''));

if ($fullName === '' || $email === '' || $phone === '' || $password === '') {
    json_response(400, ['success' => false, 'message' => 'Full name, email, phone, and password are required']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'message' => 'Invalid email address']);
}

if (!preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, ['success' => false, 'message' => 'Invalid Jordanian phone number', 'debug_phone' => $phone]);
}

if (strlen($password) < 8) {
    json_response(400, ['success' => false, 'message' => 'Password must be at least 8 characters']);
}

try {
    $check = $pdo->prepare('SELECT id, email, phone FROM app_users WHERE email = :email OR phone = :phone LIMIT 1');
    $check->execute([':email' => $email, ':phone' => $phone]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (($existing['email'] ?? '') === $email) {
            json_response(409, ['success' => false, 'message' => 'Email is already registered']);
        }
        if (($existing['phone'] ?? '') === $phone) {
            json_response(409, ['success' => false, 'message' => 'Phone number is already registered']);
        }
        json_response(409, ['success' => false, 'message' => 'Account already exists']);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO app_users
            (device_id, email, phone, password_hash, full_name, age, sex, blood_type, allergies, conditions, medications, notes, questionnaire_json, language, country_code, emergency_number, ambulance_number, fire_number, created_at, updated_at)
        VALUES
            (:device_id, :email, :phone, :password_hash, :full_name, :age, :sex, :blood_type, :allergies, :conditions, :medications, :notes, :questionnaire_json, :language, :country_code, :emergency_number, :ambulance_number, :fire_number, NOW(), NOW())
    ');

    $stmt->execute([
        ':device_id' => $deviceId,
        ':email' => $email,
        ':phone' => $phone,
        ':password_hash' => $passwordHash,
        ':full_name' => $fullName,
        ':age' => ($data['age'] ?? '') === '' ? null : (int)$data['age'],
        ':sex' => trim((string)($data['sex'] ?? '')) ?: null,
        ':blood_type' => trim((string)($data['blood_type'] ?? '')) ?: null,
        ':allergies' => trim((string)($data['allergies'] ?? '')) ?: null,
        ':conditions' => trim((string)($data['conditions'] ?? '')) ?: null,
        ':medications' => trim((string)($data['medications'] ?? '')) ?: null,
        ':notes' => trim((string)($data['notes'] ?? '')) ?: null,
        ':questionnaire_json' => isset($data['questionnaire_json']) ? (is_array($data['questionnaire_json']) ? json_encode($data['questionnaire_json'], JSON_UNESCAPED_UNICODE) : (string)$data['questionnaire_json']) : null,
        ':language' => (string)($data['language'] ?? 'en'),
        ':country_code' => (string)($data['country_code'] ?? '+962'),
        ':emergency_number' => (string)($data['emergency_number'] ?? '911'),
        ':ambulance_number' => (string)($data['ambulance_number'] ?? '193'),
        ':fire_number' => (string)($data['fire_number'] ?? '199'),
    ]);

    $id = (int)$pdo->lastInsertId();

    json_response(200, [
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $id,
            'device_id' => $deviceId,
            'email' => $email,
            'phone' => $phone,
            'full_name' => $fullName,
            'age' => ($data['age'] ?? '') === '' ? null : (int)$data['age'],
            'sex' => trim((string)($data['sex'] ?? '')),
            'blood_type' => trim((string)($data['blood_type'] ?? '')),
            'allergies' => trim((string)($data['allergies'] ?? '')),
            'conditions' => trim((string)($data['conditions'] ?? '')),
            'medications' => trim((string)($data['medications'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'birth_date' => trim((string)($data['birth_date'] ?? '')),
            'country' => trim((string)($data['country'] ?? 'Jordan')),
            'language' => (string)($data['language'] ?? 'en'),
            'country_code' => (string)($data['country_code'] ?? '+962'),
            'emergency_number' => (string)($data['emergency_number'] ?? '911'),
            'ambulance_number' => (string)($data['ambulance_number'] ?? '193'),
            'fire_number' => (string)($data['fire_number'] ?? '199'),
        ]
    ]);
} catch (Throwable $e) {
    json_response(500, ['success' => false, 'message' => 'Server error during registration', 'error' => $e->getMessage()]);
}
?>