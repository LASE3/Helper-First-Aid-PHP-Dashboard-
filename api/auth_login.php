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

    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(400, [
            'success' => false,
            'message' => 'Email and password are required',
        ]);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(400, [
            'success' => false,
            'message' => 'Invalid email format',
        ]);
    }

    if (!column_exists($pdo, 'app_users', 'email')) {
        json_response(500, [
            'success' => false,
            'message' => 'Database error: app_users.email column is missing',
        ]);
    }

    if (!column_exists($pdo, 'app_users', 'password_hash')) {
        json_response(500, [
            'success' => false,
            'message' => 'Database error: app_users.password_hash column is missing. Run the auth columns SQL migration.',
        ]);
    }

    $selectColumns = [
        'id',
        'device_id',
        'email',
        'password_hash',
        'profile_image_path',
        'full_name',
        'age',
        'sex',
        'blood_type',
        'allergies',
        'conditions',
        'medications',
        'notes',
        'questionnaire_json',
        'language',
        'country_code',
        'emergency_number',
        'ambulance_number',
        'fire_number',
        'created_at',
        'updated_at',
    ];

    if (column_exists($pdo, 'app_users', 'phone')) {
        $selectColumns[] = 'phone';
    } else {
        $selectColumns[] = "'' AS phone";
    }

    $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM app_users WHERE email = :email LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
        json_response(401, [
            'success' => false,
            'message' => 'Invalid email or password',
        ]);
    }

    unset($user['password_hash']);

    json_response(200, [
        'success' => true,
        'message' => 'Login successful',
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