<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

function json_response(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function normalize_phone(string $raw): string {
    $phone = trim($raw);
    $phone = str_replace([' ', '-', '(', ')'], '', $phone);
    if (str_starts_with($phone, '+962')) $phone = '0' . substr($phone, 4);
    elseif (str_starts_with($phone, '00962')) $phone = '0' . substr($phone, 5);
    elseif (str_starts_with($phone, '962')) $phone = '0' . substr($phone, 3);
    return $phone;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, ['success' => false, 'message' => 'Only POST method is allowed']);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) json_response(400, ['success' => false, 'message' => 'Invalid JSON body']);

$phone = normalize_phone((string)($data['phone'] ?? ''));
$newPassword = (string)($data['new_password'] ?? $data['password'] ?? '');

if (!preg_match('/^07[789][0-9]{7}$/', $phone) || strlen($newPassword) < 8) {
    json_response(400, ['success' => false, 'message' => 'Invalid phone or password']);
}

try {
    $otp = $pdo->prepare('SELECT id, user_id FROM app_password_reset_otps WHERE phone = :phone AND verified = 1 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
    $otp->execute([':phone' => $phone]);
    $row = $otp->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_response(403, ['success' => false, 'message' => 'OTP is not verified or expired']);

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE app_users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')
        ->execute([':hash' => $hash, ':id' => (int)$row['user_id']]);
    $pdo->prepare('DELETE FROM app_password_reset_otps WHERE phone = :phone')->execute([':phone' => $phone]);

    json_response(200, ['success' => true, 'message' => 'Password reset successfully']);
} catch (Throwable $e) {
    json_response(500, ['success' => false, 'message' => 'Server error while resetting password', 'error' => $e->getMessage()]);
}
