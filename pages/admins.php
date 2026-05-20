<?php
declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('admins.view');

if (session_status()=== PHP_SESSION_NONE){
  session_start();
}

if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit();
}

$error = "";
$success = "";

try {
  $pdo->beginTransaction();

  // insert admin + permissions

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  $error = "Something went wrong";
}

if (isset($_POST['create_admin'])) {
  require_perm('admins.create');

  $full_name = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($full_name === '' || $email === '' || $password === '') {
    $error = "Each field is required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Email are incorrect";
  } elseif (strlen($password) < 6) {
    $error = "The password must be more than 6 letters long";
  } else {
    $check = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
      $error = "The Email Already Exists";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      $pdo->beginTransaction();

      $ins = $pdo->prepare("
                INSERT INTO admin_users (full_name, email, password_hash, role, is_active, created_at)
                VALUES (?, ?, ?, 'admin', ?, NOW())
            ");
      $ins->execute([$full_name, $email, $hash, $is_active]);
      $newId = (int)$pdo->lastInsertId();

      $permIds = $_POST['permissions'] ?? [];
      $up = $pdo->prepare("INSERT INTO admin_user_permissions (admin_user_id, permission_id) VALUES (?, ?)");
      foreach ($permIds as $pid) {
        $up->execute([$newId, (int)$pid]);
      }

      $pdo->commit();
      $success = "The Admin has been made successfully";
    }
  }
}

if (isset($_POST['update_perms'])) {
  require_perm('system.manage_permissions');

  $admin_id = (int)($_POST['admin_id'] ?? 0);
  $permIds = $_POST['permissions'] ?? [];

  if ($admin_id === (int)($_SESSION['admin_id'] ?? 0)) {
    $error = "Don't Change anything From Here!";
  } else {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM admin_user_permissions WHERE admin_user_id = ?")->execute([$admin_id]);

    $up = $pdo->prepare("INSERT INTO admin_user_permissions (admin_user_id, permission_id) VALUES (?, ?)");
    foreach ($permIds as $pid) {
      $up->execute([$admin_id, (int)$pid]);
    }
    $pdo->commit();
    $success = "Permissions have been updated";
  }
}

$stmt = $pdo->prepare("
  SELECT id, full_name, email, role, is_active 
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
      <a href="../logout.php">Logout</a>
    </div>

    <?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="ok"><?= htmlspecialchars($success) ?></p><?php endif; ?>
  </div>

  <?php if (can('admins.create')): ?>
    <div class="box">
      <h3>Create Admin</h3>
      <form method="POST">
        <input name="full_name" placeholder="Full name" required>
        <input name="email" type="email" placeholder="Email" required>
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
        <div class="small"><?= htmlspecialchars($a['email']) ?> | role: <?= htmlspecialchars($a['role'] ?? '') ?> | active: <?= (int)$a['is_active'] ?></div>

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
          <p class="small">The super_admin privileges cannot be modified, or you do not have the privilege to modify privileges.</p></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

</body>

</html>