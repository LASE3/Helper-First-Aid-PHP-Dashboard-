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

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid JSON body"
    ]);
}

$phone = normalize_phone((string)($data['phone'] ?? ''));

if ($phone === '' || !preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid Jordanian phone number",
        "debug_phone" => $phone
    ]);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, phone
        FROM app_users
        WHERE phone = :phone
        LIMIT 1
    ");

    $stmt->execute([
        ':phone' => $phone
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_response(404, [
            "success" => false,
            "message" => "No account found with this phone number",
            "debug_phone" => $phone
        ]);
    }

    $otp = (string)random_int(1000, 9999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 minutes'));

    $pdo->prepare("
        DELETE FROM app_password_reset_otps
        WHERE phone = :phone OR expires_at < NOW()
    ")->execute([
        ':phone' => $phone
    ]);

    $insert = $pdo->prepare("
        INSERT INTO app_password_reset_otps
            (user_id, phone, otp_code, otp_hash, expires_at, verified, created_at)
        VALUES
            (:user_id, :phone, :otp_code, :otp_hash, :expires_at, 0, NOW())
    ");

    $insert->execute([
        ':user_id' => (int)$user['id'],
        ':phone' => $phone,
        ':otp_code' => $otp,
        ':otp_hash' => $otpHash,
        ':expires_at' => $expiresAt,
    ]);

    json_response(200, [
        "success" => true,
        "message" => "OTP saved successfully"
    ]);
} catch (Throwable $e) {
    json_response(500, [
        "success" => false,
        "message" => "Server error while creating OTP",
        "error" => $e->getMessage()
    ]);
}
?>