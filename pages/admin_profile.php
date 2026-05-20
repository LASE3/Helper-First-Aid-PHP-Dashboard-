<?php
# added new page to view admin profile and permissions 10/5/2026
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

$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($adminId <= 0) {
    die("Admin session is invalid. Please login again.");
}
$stmt = $pdo->prepare(
    "
    SELECT id, full_name, email, role, is_active, created_at, updated_at
    FROM admin_users
    WHERE id = :id
    LIMIT 1"
);

$stmt->execute(['id' => $adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    die("Admin user not found. Please login again.");
}

$permStmt = $pdo->prepare("
    SELECT 
        permissions.perm_key,
        permissions.perm_name
    FROM admin_user_permissions
    INNER JOIN permissions 
        ON admin_user_permissions.permission_id = permissions.id
    WHERE admin_user_permissions.admin_user_id = :admin_id
    ORDER BY permissions.perm_key ASC
");

$permStmt->execute([
    ':admin_id' => $adminId
]);

$permissions = $permStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../assets/css/admin_profile.css">
</head>

<body>
    <h2>My Admin Profile</h2>
    <div class="box">
        <p><span class="label">Full Name:</span> <?= htmlspecialchars((string)$admin['full_name']) ?></p>
        <p><span class="label">Email:</span> <?= htmlspecialchars((string)$admin['email']) ?></p>
        <p><span class="label">Role:</span> <?= htmlspecialchars((string)$admin['role']) ?></p>
        <p><span class="label">Active:</span> <?= (int)$admin['is_active'] === 1 ? 'Active' : 'Inactive' ?></p>
        <p><span class="label">Created At:</span> <?= htmlspecialchars((string)$admin['created_at']) ?></p>
    </div>
    <div class="box">
        <h3>My Permissions</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Permission Key</th>
                        <th>Permission Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($permissions) > 0): ?>
                        <?php foreach ($permissions as $permission): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$permission['perm_key']) ?></td>
                                <td><?= htmlspecialchars((string)$permission['perm_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You do not have any permissions assigned.</p>
        <?php endif; ?>
        </div>

</body>

</html>