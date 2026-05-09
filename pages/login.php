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

    $stmt = $pdo->prepare("SELECT id, email, password_hash, is_active, role FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // echo "<pre>";
    // var_dump($email);
    // var_dump($user);
    // die("</pre>");


    if (!$user) {
        $error = "Email or Password are incorrect";
    } elseif ((int)$user['is_active'] !== 1) {
        $error = "The account is not activated";
    } elseif (!password_verify($password, $user['password_hash'])) {
        $error = "Email or Password are incorrect";
    } else {

        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin'] = $user['email'];
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
<html>

<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f4f4
        }

        .card {
            width: 360px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0
        }

        button {
            width: 100%;
            padding: 10px;
            background: blue;
            color: white;
            border: none;
            cursor: pointer
        }

        .err {
            color: red
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Admin Login</h2>
        <?php if (!empty($error)): ?>
            <p class="err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login" value="1">Login</button>

        </form>
    </div>
</body>

</html>