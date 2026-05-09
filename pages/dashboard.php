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

require_perm('dashboard.view');
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>

<body>
    <h1>Welcome to Dashboard</h1>

<ul>
  <?php if (can('categories.view')): ?><li><a href="categories.php">Manage Categories</a></li><?php endif; ?>
  <?php if (can('steps.view')): ?><li><a href="steps.php">Manage Steps</a></li><?php endif; ?>
  <?php if (can('incidents.view')): ?><li><a href="incidents.php">Manage Incidents</a></li><?php endif; ?>
  <?php if (can('admins.view')): ?><li><a href="admins.php">Manage Admins</a></li><?php endif; ?>
  <li><a href="../logout.php">Logout</a></li>
</ul>

</body>

</html>