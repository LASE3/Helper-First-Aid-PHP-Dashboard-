<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('admins.view');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit();
}

$error = "";
$success = "";

function admin_phone_column_exists(PDO $pdo): bool
{
  $stmt = $pdo->prepare("SHOW COLUMNS FROM admin_users LIKE 'phone'");
  $stmt->execute();
  return (bool)$stmt->fetch();
}

$hasPhoneColumn = admin_phone_column_exists($pdo);

if (isset($_POST['create_admin'])) {
  require_perm('admins.create');

  $full_name = trim($_POST['full_name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $phone = trim($_POST['phone'] ?? '');
  $password = $_POST['password'] ?? '';
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($full_name === '' || $email === '' || $password === '') {
    $error = "Full name, email, and password are required.";
  } elseif ($hasPhoneColumn && $phone === '') {
    $error = "Phone number is required for password reset.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Email is incorrect.";
  } elseif (strlen($password) < 6) {
    $error = "The password must be at least 6 characters long.";
  } else {
    $checkSql = $hasPhoneColumn
      ? "SELECT id FROM admin_users WHERE email = ? OR phone = ? LIMIT 1"
      : "SELECT id FROM admin_users WHERE email = ? LIMIT 1";
    $check = $pdo->prepare($checkSql);
    $check->execute($hasPhoneColumn ? [$email, $phone] : [$email]);

    if ($check->fetch()) {
      $error = "The email or phone number already exists.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      try {
        $pdo->beginTransaction();

        if ($hasPhoneColumn) {
          $ins = $pdo->prepare("
            INSERT INTO admin_users (full_name, email, phone, password_hash, role, is_active, created_at)
            VALUES (?, ?, ?, ?, 'admin', ?, NOW())
            ");
          $ins->execute([$full_name, $email, $phone, $hash, $is_active]);
        } else {
          $ins = $pdo->prepare("
            INSERT INTO admin_users (full_name, email, password_hash, role, is_active, created_at)
            VALUES (?, ?, ?, 'admin', ?, NOW())
          ");
          $ins->execute([$full_name, $email, $hash, $is_active]);
        }

        $newId = (int)$pdo->lastInsertId();

        $permIds = $_POST['permissions'] ?? [];
        $up = $pdo->prepare("INSERT INTO admin_user_permissions (admin_user_id, permission_id) VALUES (?, ?)");
        foreach ($permIds as $pid) {
          $up->execute([$newId, (int)$pid]);
        }

        $pdo->commit();
        $success = "The admin account has been created successfully.";
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $error = "Failed to create admin account.";
      }
    }
  }
}

if (isset($_POST['update_perms'])) {
  require_perm('system.manage_permissions');

  $admin_id = (int)($_POST['admin_id'] ?? 0);
  $permIds = $_POST['permissions'] ?? [];

  if ($admin_id === (int)($_SESSION['admin_id'] ?? 0)) {
    $error = "Don't change your own permissions from here.";
  } else {
    try {
      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM admin_user_permissions WHERE admin_user_id = ?")->execute([$admin_id]);

      $up = $pdo->prepare("INSERT INTO admin_user_permissions (admin_user_id, permission_id) VALUES (?, ?)");
      foreach ($permIds as $pid) {
        $up->execute([$admin_id, (int)$pid]);
      }
      $pdo->commit();
      $success = "Permissions have been updated.";
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error = "Failed to update permissions.";
    }
  }
}

$selectPhone = $hasPhoneColumn ? ", phone" : "";
$stmt = $pdo->prepare("
  SELECT id, full_name, email {$selectPhone}, role, is_active 
  FROM admin_users 
  ORDER BY id DESC
");

$stmt->execute();

$admins = $stmt->fetchAll();

$stmtPerms = $pdo->prepare("
  SELECT id, perm_key, perm_name 
  FROM permissions 
  ORDER BY perm_key
");

$stmtPerms->execute();

$allPerms = $stmtPerms->fetchAll();

function user_perm_ids(PDO $pdo, int $adminId): array
{
  $st = $pdo->prepare("SELECT permission_id FROM admin_user_permissions WHERE admin_user_id = ?");
  $st->execute([$adminId]);
  return $st->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Admins</title>
  <link rel="stylesheet" href="../assets/css/admins.css">
</head>

<body>

  <div class="box">
    <div class="row space-between">
      <h2>Admins & Permissions</h2>
      <a href="logout.php">Logout</a>
    </div>

    <?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="ok"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <?php if (!$hasPhoneColumn): ?>
      <p class="err">Database patch missing: admin_users.phone does not exist. Run the password reset SQL patch before using phone-based reset.</p>
    <?php endif; ?>
  </div>

  <?php if (can('admins.create')): ?>
    <div class="box">
      <h3>Create Admin</h3>
      <form method="POST">
        <input name="full_name" placeholder="Full name" required>
        <input name="email" type="email" placeholder="Email" required>
        <input name="phone" type="tel" placeholder="Phone number" <?= $hasPhoneColumn ? 'required' : '' ?>>
        <input name="password" type="password" placeholder="Password (min 6)" required>

        <label><input type="checkbox" name="is_active" checked> Active</label>

        <p class="small">Select permissions for this admin:</p>
        <div class="perms">
          <?php foreach ($allPerms as $p): ?>
            <label>
              <input type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>">
              <?= htmlspecialchars($p['perm_key']) ?> — <?= htmlspecialchars($p['perm_name']) ?>
            </label>
          <?php endforeach; ?>
        </div>

        <button class="btn" name="create_admin">Create</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="box">
    <h3>Existing Admins</h3>

    <?php foreach ($admins as $a): ?>
      <div class="box admin-card">
        <b><?= htmlspecialchars($a['full_name'] ?? '') ?></b>
        <div class="small">
          <?= htmlspecialchars($a['email']) ?>
          <?php if ($hasPhoneColumn): ?>
            | phone: <?= htmlspecialchars($a['phone'] ?? '') ?>
          <?php endif; ?>
          | role: <?= htmlspecialchars($a['role'] ?? '') ?>
          | active: <?= (int)$a['is_active'] ?>
        </div>

        <?php if (can('system.manage_permissions') && ($a['role'] ?? '') !== 'super_admin'): ?>
          <?php $current = user_perm_ids($pdo, (int)$a['id']); ?>
          <form method="POST">
            <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
            <div class="perms">
              <?php foreach ($allPerms as $p): ?>
                <?php $checked = in_array((int)$p['id'], array_map('intval', $current), true); ?>
                <label>
                  <input type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                  <?= htmlspecialchars($p['perm_key']) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <button class="btn" name="update_perms">Update permissions</button>
          </form>
        <?php else: ?>
          <p class="small">The super_admin privileges cannot be modified, or you do not have the privilege to modify privileges.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

</body>

</html>