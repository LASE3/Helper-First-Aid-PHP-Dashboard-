<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/database.php';

$error = "";

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, is_active, role FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Email or Password are incorrect";
    } elseif ((int)$user['is_active'] !== 1) {
        $error = "The account is not activated";
    } elseif (!password_verify($password, $user['password_hash'])) {
        $error = "Email or Password are incorrect";
    } else {
        // added new information 10/5/2026
        $_SESSION['admin'] = true;
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];

        if (($user['role'] ?? '') === 'super_admin') {
            $all = $pdo->query("SELECT perm_key FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
            $_SESSION['perms'] = $all;
        } else {
            $permStmt = $pdo->prepare(
                "
                SELECT p.perm_key
                FROM admin_user_permissions up
                JOIN permissions p ON p.id = up.permission_id
               WHERE up.admin_user_id = ?"
            );
            $permStmt->execute([(int)$user['id']]);
            $_SESSION['perms'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $upd = $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
        $upd->execute([(int)$user['id']]);

        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="login-wrapper">
       <div class="login-wrapper">

    <div class="left-panel">
        <h1>Admin Login</h1>
        <p>The main Dashboard for Helper:First Aid</p>
    <!--    <button class="read-more" type="button">Read More</button> -->

        <div class="circle"></div>
        <div class="circle two"></div>
    </div>

    <div class="right-panel">
        <div class="form-box">
            <h2>Hello Again!</h2>
            <p class="sub">Welcome Back</p>

            <?php if(!empty($error)): ?>
                <div class="err"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-wrap">
                    <span>✉</span>
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                <div class="input-wrap">
                    <span>🔒</span>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button class="login-btn" type="submit" name="login" value="1">Login</button>

                <a class="forgot" href="#">Forgot Password?</a>
            </form>
        </div>
    </div>
</body>
</html>