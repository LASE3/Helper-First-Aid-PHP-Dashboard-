<?php
#added new page to view all app users and their details 10/5/2026
declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('users.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
$stmt = $pdo->prepare("
    SELECT 
        app_users.*,
        COUNT(incidents.id) AS incident_count
    FROM app_users
    LEFT JOIN incidents 
        ON app_users.device_id = incidents.device_id
    GROUP BY app_users.id
    ORDER BY app_users.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>App Users</title>
    <link rel="stylesheet" href="../assets/css/users.css">
</head>

<body>
    <h1>App Users</h1>
    <div class="users-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Device ID</th>
                        <th>Incidents</th>
                        <th>Last Updated</th>
                        <th>View Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['age']) ?></td>
                                <td><?= htmlspecialchars($user['sex']) ?></td>
                                <td><?= htmlspecialchars($user['device_id']) ?></td>
                                <td><?= htmlspecialchars($user['incident_count']) ?></td>
                                <td><?= htmlspecialchars($user['updated_at']) ?></td>
                                <td><a href="user_view.php?id=<?= $user['id'] ?>" class="button">View Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <a href="dashboard.php">Back to Dashboard</a>
</body>

</html>