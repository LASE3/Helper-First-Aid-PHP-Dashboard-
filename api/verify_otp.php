<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Amman');

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
    json_response(405, [
        "success" => false,
        "message" => "Only POST method is allowed"
    ]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid JSON body"
    ]);
}

$phone = normalize_phone((string)($data['phone'] ?? ''));
$otp = trim((string)($data['otp'] ?? ''));

if (!preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid phone number",
        "debug_phone" => $phone
    ]);
}

if (!preg_match('/^[0-9]{4}$/', $otp)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid OTP format"
    ]);
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            phone,
            otp_code,
            otp_hash,
            expires_at,
            verified,
            used_at
        FROM app_password_reset_otps
        WHERE phone = :phone
          AND verified = 0
          AND used_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':phone' => $phone
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_response(401, [
            "success" => false,
            "message" => "Invalid or expired OTP"
        ]);
    }

    $now = time();
    $expiresAt = strtotime((string)$row['expires_at']);

    if ($expiresAt === false || $expiresAt < $now) {
        json_response(401, [
            "success" => false,
            "message" => "Invalid or expired OTP"
        ]);
    }

    $otpMatches = false;

    if (!empty($row['otp_code']) && hash_equals((string)$row['otp_code'], $otp)) {
        $otpMatches = true;
    }

    if (!$otpMatches && !empty($row['otp_hash'])) {
        $otpMatches = password_verify($otp, (string)$row['otp_hash']);
    }

    if (!$otpMatches) {
        json_response(401, [
            "success" => false,
            "message" => "Invalid or expired OTP"
        ]);
    }

    $update = $pdo->prepare("
        UPDATE app_password_reset_otps
        SET verified = 1,
            verified_at = NOW()
        WHERE id = :id
    ");

    $update->execute([
        ':id' => (int)$row['id']
    ]);

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
?>