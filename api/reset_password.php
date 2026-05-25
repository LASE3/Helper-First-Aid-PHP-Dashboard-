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
$newPassword = (string)($data['new_password'] ?? '');

if (!preg_match('/^07[789][0-9]{7}$/', $phone)) {
    json_response(400, [
        "success" => false,
        "message" => "Invalid phone number"
    ]);
}

if (
    strlen($newPassword) < 8 ||
    !preg_match('/[A-Za-z]/', $newPassword) ||
    !preg_match('/[0-9]/', $newPassword) ||
    !preg_match('/[@$!%*#?&]/', $newPassword)
) {
    json_response(400, [
        "success" => false,
        "message" => "Weak password"
    ]);
}

try {
    $otpStmt = $pdo->prepare("
        SELECT id, user_id, expires_at
        FROM app_password_reset_otps
        WHERE phone = :phone
          AND verified = 1
          AND used_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");

    $otpStmt->execute([
        ':phone' => $phone
    ]);

    $otpRow = $otpStmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRow) {
        json_response(401, [
            "success" => false,
            "message" => "OTP is not verified or expired"
        ]);
    }

    $expiresAt = strtotime((string)$otpRow['expires_at']);

    if ($expiresAt === false || $expiresAt < time()) {
        json_response(401, [
            "success" => false,
            "message" => "OTP is not verified or expired"
        ]);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateUser = $pdo->prepare("
        UPDATE app_users
        SET password_hash = :password_hash,
            updated_at = NOW()
        WHERE id = :user_id
          AND phone = :phone
    ");

    $updateUser->execute([
        ':password_hash' => $passwordHash,
        ':user_id' => (int)$otpRow['user_id'],
        ':phone' => $phone
    ]);

    if ($updateUser->rowCount() < 1) {
        json_response(404, [
            "success" => false,
            "message" => "User not found"
        ]);
    }

    $markUsed = $pdo->prepare("
        UPDATE app_password_reset_otps
        SET used_at = NOW()
        WHERE id = :id
    ");

    $markUsed->execute([
        ':id' => (int)$otpRow['id']
    ]);

    json_response(200, [
        "success" => true,
        "message" => "Password reset successfully"
    ]);

} catch (Throwable $e) {
    json_response(500, [
        "success" => false,
        "message" => "Server error while resetting password",
        "error" => $e->getMessage()
    ]);
}
?>