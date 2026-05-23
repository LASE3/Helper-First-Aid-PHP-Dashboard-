<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';

$error = "";
$message = "";

function normalize_phone(string $phone): string
{
    return preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
}

function is_local_request(): bool
{
    $addr = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($addr, ['127.0.0.1', '::1'], true) || str_starts_with($addr, '192.168.') || str_starts_with($addr, '10.');
}

function store_password_reset_otp(PDO $pdo, int $adminId, string $phone, string $otp): void
{
    $pdo->prepare("
        UPDATE admin_password_resets
        SET used_at = NOW()
        WHERE admin_user_id = ? AND used_at IS NULL
    ")->execute([$adminId]);

    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO admin_password_resets (admin_user_id, phone, otp_hash, expires_at, created_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
    ");
    $stmt->execute([$adminId, $phone, $otpHash]);
}

if (isset($_POST['send_otp'])) {
    $phone = normalize_phone($_POST['phone'] ?? '');

    if ($phone === '') {
        $error = "Phone number is required.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, full_name, phone, is_active
            FROM admin_users
            WHERE phone = ?
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $admin = $stmt->fetch();

        if (!$admin || (int)$admin['is_active'] !== 1) {
            $error = "No active admin account was found with this phone number.";
        } else {
            $otp = (string)random_int(100000, 999999);
            store_password_reset_otp($pdo, (int)$admin['id'], $phone, $otp);

            $_SESSION['password_reset_phone'] = $phone;
            $_SESSION['password_reset_admin_id'] = (int)$admin['id'];

            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0775, true);
            }
            file_put_contents(
                $logDir . '/password_reset_otp.log',
                '[' . date('Y-m-d H:i:s') . "] phone={$phone} otp={$otp}\n",
                FILE_APPEND
            );

            error_log("ADMIN PASSWORD RESET OTP for phone {$phone}: {$otp}");

            $message = "OTP has been generated. In production, connect an SMS gateway to send it to the phone.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <h1>Reset Password</h1>
            <p>Enter your admin phone number to receive a one-time verification code.</p>
            <div class="circle"></div>
            <div class="circle two"></div>
        </div>

        <div class="right-panel">
            <div class="form-box">
                <h2>Forgot?</h2>
                <p class="sub">Reset your admin password</p>

                <?php if (!empty($error)): ?>
                    <div class="err"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="ok"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-wrap">
                        <span>☎</span>
                        <input type="tel" name="phone" placeholder="Phone Number" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <button class="login-btn" type="submit" name="send_otp" value="1">Send OTP</button>
                    <?php if (!empty($message)): ?>
                        <a class="forgot" href="verify_otp.php">I have the OTP</a>
                    <?php endif; ?>
                    <a class="forgot" href="login.php">Back to Login</a>
                </form>
            </div>
        </div>
    </div>
</body>

</html>