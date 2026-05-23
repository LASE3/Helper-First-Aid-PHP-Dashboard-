<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';

$error = "";
$phone = $_SESSION['password_reset_phone'] ?? '';
$adminId = (int)($_SESSION['password_reset_admin_id'] ?? 0);

if ($phone === '' || $adminId <= 0) {
    header("Location: forgot_password.php");
    exit();
}

if (isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp'] ?? '');

    if ($otp === '') {
        $error = "OTP is required.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, otp_hash
            FROM admin_password_resets
            WHERE admin_user_id = ?
              AND phone = ?
              AND used_at IS NULL
              AND expires_at >= NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$adminId, $phone]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($otp, $row['otp_hash'])) {
            $error = "Invalid or expired OTP.";
        } else {
            $_SESSION['password_reset_verified_admin_id'] = $adminId;
            $_SESSION['password_reset_verified_until'] = time() + 600;
            $_SESSION['password_reset_row_id'] = (int)$row['id'];

            header("Location: reset_password.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo_circle.ico?v=2">
    <link rel="shortcut icon" href="../assets/image/logo_circle.ico?v=2" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <h1>Verify OTP</h1>
            <p>Enter the 6-digit code generated for your admin phone number.</p>
            <div class="circle"></div>
            <div class="circle two"></div>
        </div>

        <div class="right-panel">
            <div class="form-box">
                <h2>OTP Code</h2>
                <p class="sub">Phone: <?= htmlspecialchars($phone) ?></p>

                <?php if (!empty($error)): ?>
                    <div class="err"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-wrap">
                        <span>#</span>
                        <input type="text" name="otp" inputmode="numeric" maxlength="6" placeholder="6-digit OTP" required>
                    </div>
                    <button class="login-btn" type="submit" name="verify_otp" value="1">Verify OTP</button>
                    <a class="forgot" href="forgot_password.php">Send another OTP</a>
                    <a class="forgot" href="login.php">Back to Login</a>
                </form>
            </div>
        </div>
    </div>
</body>

</html>