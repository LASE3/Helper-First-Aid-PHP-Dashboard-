<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';

function json_response(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        "success" => false,
        "message" => "Only POST method is allowed"
    ]);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid JSON body"
    ]);
}

$phone = trim((string)($data['phone'] ?? ''));
$otp = trim((string)($data['otp'] ?? ''));

$phone = str_replace([' ', '-', '(', ')'], '', $phone);
if (str_starts_with($phone, '+962')) {
    $phone = '0' . substr($phone, 4);
} elseif (str_starts_with($phone, '00962')) {
    $phone = '0' . substr($phone, 5);
} elseif (str_starts_with($phone, '962')) {
    $phone = '0' . substr($phone, 3);
}

if ($phone === '' || !preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid phone number"
    ]);
}

if ($otp === '' || !preg_match('/^[0-9]{4}$/', $otp)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid OTP"
    ]);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, otp_hash, expires_at, attempts
        FROM app_password_reset_otps
        WHERE phone = :phone
          AND verified = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':phone' => $phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_response(404, [
            "success" => false,
            "message" => "No OTP request found"
        ]);
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        json_response(410, [
            "success" => false,
            "message" => "OTP expired"
        ]);
    }

    if ((int)$row['attempts'] >= 5) {
        json_response(429, [
            "success" => false,
            "message" => "Too many wrong attempts"
        ]);
    }

    if (!password_verify($otp, (string)$row['otp_hash'])) {
        $pdo->prepare("
            UPDATE app_password_reset_otps
            SET attempts = attempts + 1
            WHERE id = :id
        ")->execute([':id' => (int)$row['id']]);

        json_response(401, [
            "success" => false,
            "message" => "Wrong OTP"
        ]);
    }

    $pdo->prepare("
        UPDATE app_password_reset_otps
        SET verified = 1, verified_at = NOW()
        WHERE id = :id
    ")->execute([':id' => (int)$row['id']]);

    json_response(200, [
        "success" => true,
        "message" => "OTP verified successfully"
    ]);

} catch (Throwable $e) {
    json_response(500, [
        "success" => false,
        "message" => "Server error while verifying OTP",
        "error" => $e->getMessage()
    ]);
}
