<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';

$error = "";
$success = "";

$adminId = (int)($_SESSION['password_reset_verified_admin_id'] ?? 0);
$verifiedUntil = (int)($_SESSION['password_reset_verified_until'] ?? 0);
$resetRowId = (int)($_SESSION['password_reset_row_id'] ?? 0);

if ($adminId <= 0 || $verifiedUntil < time() || $resetRowId <= 0) {
    header("Location: forgot_password.php");
    exit();
}

if (isset($_POST['change_password'])) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Password confirmation does not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $adminId]);

            $pdo->prepare("
                UPDATE admin_password_resets
                SET used_at = NOW()
                WHERE id = ? AND admin_user_id = ?
            ")->execute([$resetRowId, $adminId]);

            $pdo->commit();

            log_admin_action(
                $pdo,
                'reset_admin_password',
                'admin_user',
                $adminId,
                'Admin password was reset through OTP verification.'
            );

            unset(
                $_SESSION['password_reset_phone'],
                $_SESSION['password_reset_admin_id'],
                $_SESSION['password_reset_verified_admin_id'],
                $_SESSION['password_reset_verified_until'],
                $_SESSION['password_reset_row_id']
            );

            $success = "Password changed successfully. You can log in now.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to change password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <h1>New Password</h1>
            <p>Create a new password for your admin dashboard account.</p>
            <div class="circle"></div>
            <div class="circle two"></div>
        </div>

        <div class="right-panel">
            <div class="form-box">
                <h2>Change Password</h2>
                <p class="sub">Enter and confirm your new password</p>

                <?php if (!empty($error)): ?>
                    <div class="err"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="ok"><?= htmlspecialchars($success) ?></div>
                    <a class="forgot" href="login.php">Go to Login</a>
                <?php else: ?>
                    <form method="POST">
                        <div class="input-wrap">
                            <span>🔒</span>
                            <input type="password" name="password" placeholder="New Password" required>
                        </div>
                        <div class="input-wrap">
                            <span>🔒</span>
                            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                        </div>
                        <button class="login-btn" type="submit" name="change_password" value="1">Change Password</button>
                        <a class="forgot" href="login.php">Back to Login</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>