<?php

declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

function json_response(int $status, array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute([':column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, [
            'success' => false,
            'message' => 'Only POST method is allowed',
        ]);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        json_response(400, [
            'success' => false,
            'message' => 'Invalid JSON body',
        ]);
    }

    $fullName = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? $data['phone_number'] ?? $data['mobile'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $deviceId = trim((string)($data['device_id'] ?? ''));

    $age = ($data['age'] ?? '') === '' ? null : (int)$data['age'];
    $sex = trim((string)($data['sex'] ?? ''));
    $bloodType = trim((string)($data['blood_type'] ?? ''));
    $allergies = trim((string)($data['allergies'] ?? ''));
    $conditions = trim((string)($data['conditions'] ?? ''));
    $medications = trim((string)($data['medications'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $language = trim((string)($data['language'] ?? 'en'));
    $countryCode = trim((string)($data['country_code'] ?? '+962'));
    $emergencyNumber = trim((string)($data['emergency_number'] ?? '911'));
    $ambulanceNumber = trim((string)($data['ambulance_number'] ?? '193'));
    $fireNumber = trim((string)($data['fire_number'] ?? '199'));

    $questionnaire = $data['questionnaire_json'] ?? $data['questionnaire'] ?? $data['survey'] ?? null;
    if (is_array($questionnaire)) {
        $questionnaire = json_encode($questionnaire, JSON_UNESCAPED_UNICODE);
    } elseif ($questionnaire !== null) {
        $questionnaire = trim((string)$questionnaire);
    }

    if ($fullName === '' || $email === '' || $password === '') {
        json_response(400, [
            'success' => false,
            'message' => 'Full name, email, and password are required',
        ]);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(400, [
            'success' => false,
            'message' => 'Invalid email format',
        ]);
    }

    if (strlen($password) < 8) {
        json_response(400, [
            'success' => false,
            'message' => 'Password must be at least 8 characters',
        ]);
    }

    if ($deviceId === '') {
        $deviceId = 'device_' . bin2hex(random_bytes(16));
    }

    // These columns are required by the Flutter auth flow.
    ensure_column($pdo, 'app_users', 'phone', "`phone` VARCHAR(20) NULL AFTER `email`");
    ensure_column($pdo, 'app_users', 'password_hash', "`password_hash` VARCHAR(255) NULL AFTER `phone`");

    $check = $pdo->prepare('SELECT id FROM app_users WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        json_response(409, [
            'success' => false,
            'message' => 'Email already registered',
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(" 
        INSERT INTO app_users
            (device_id, email, phone, password_hash, full_name, age, sex, blood_type,
             allergies, conditions, medications, notes, questionnaire_json, language,
             country_code, emergency_number, ambulance_number, fire_number)
        VALUES
            (:device_id, :email, :phone, :password_hash, :full_name, :age, :sex, :blood_type,
             :allergies, :conditions, :medications, :notes, :questionnaire_json, :language,
             :country_code, :emergency_number, :ambulance_number, :fire_number)
    ");

    $stmt->execute([
        ':device_id' => $deviceId,
        ':email' => $email,
        ':phone' => $phone !== '' ? $phone : null,
        ':password_hash' => $passwordHash,
        ':full_name' => $fullName,
        ':age' => $age,
        ':sex' => $sex !== '' ? $sex : null,
        ':blood_type' => $bloodType !== '' ? $bloodType : null,
        ':allergies' => $allergies !== '' ? $allergies : null,
        ':conditions' => $conditions !== '' ? $conditions : null,
        ':medications' => $medications !== '' ? $medications : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':questionnaire_json' => ($questionnaire !== null && $questionnaire !== '') ? (string)$questionnaire : null,
        ':language' => $language !== '' ? $language : 'en',
        ':country_code' => $countryCode !== '' ? $countryCode : '+962',
        ':emergency_number' => $emergencyNumber !== '' ? $emergencyNumber : '911',
        ':ambulance_number' => $ambulanceNumber !== '' ? $ambulanceNumber : '193',
        ':fire_number' => $fireNumber !== '' ? $fireNumber : '199',
    ]);

    $userId = (int)$pdo->lastInsertId();

    $getUser = $pdo->prepare(' 
        SELECT id, device_id, email, phone, profile_image_path, full_name, age, sex,
               blood_type, allergies, conditions, medications, notes, questionnaire_json,
               language, country_code, emergency_number, ambulance_number, fire_number,
               created_at, updated_at
        FROM app_users
        WHERE id = :id
        LIMIT 1
    ');
    $getUser->execute([':id' => $userId]);
    $user = $getUser->fetch(PDO::FETCH_ASSOC);

    json_response(201, [
        'success' => true,
        'message' => 'Registration successful',
        'user' => $user,
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage(),
    ]);
}
?>