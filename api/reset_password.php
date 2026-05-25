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
$newPassword = (string)($data['new_password'] ?? '');

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
    $stmt = $pdo->prepare("
        SELECT o.id, o.user_id
        FROM app_password_reset_otps o
        WHERE o.phone = :phone
          AND o.verified = 1
          AND o.expires_at >= NOW()
        ORDER BY o.id DESC
        LIMIT 1
    ");
    $stmt->execute([':phone' => $phone]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        json_response(403, [
            "success" => false,
            "message" => "OTP not verified or expired"
        ]);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $update = $pdo->prepare("
        UPDATE app_users
        SET password_hash = :password_hash,
            updated_at = NOW()
        WHERE id = :user_id
          AND phone = :phone
    ");

    $update->execute([
        ':password_hash' => $passwordHash,
        ':user_id' => (int)$otp['user_id'],
        ':phone' => $phone,
    ]);

    if ($update->rowCount() < 1) {
        json_response(404, [
            "success" => false,
            "message" => "User not found"
        ]);
    }

    $pdo->prepare("
        DELETE FROM app_password_reset_otps
        WHERE phone = :phone
    ")->execute([':phone' => $phone]);

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
