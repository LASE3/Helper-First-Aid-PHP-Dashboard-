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
<!-- edited by me 10/5/2026 to add the dashboard view and links to other pages based on permissions 
// added new page Users 
// added Admin profile with permissions list
// added new information to session on login to be used in dashboard and profile pages -->
<html lang='en'>
<html>

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <div style="padding:10px; background:#f4f4f4; margin-bottom:15px;">
        Signed in as:
        <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong>
        |
        Role:
        <strong><?= htmlspecialchars($_SESSION['admin_role'] ?? '') ?></strong>
        |
        <a href="admin_profile.php">My Profile</a>
        |
        <a href="settings.php">Settings</a>
        |
        <a href="logout.php" target="_top" style="color:black; text-decoration:none;">Logout</a>
    </div>

</head>

<body>

    <div style="display:flex; min-height:100vh; font-family:Arial, sans-serif;">

        <!-- LEFT MENU -->
        <div style="width:190px; background:#f4f4f4; color:black; padding:20px;">
            <h2>Dashboard</h2>
            <hr>

            <ul style="list-style:none; padding:0; margin:0;">
                <?php if (can('categories.view')): ?>
                    <li style="margin-bottom:12px;">
                        <a href="categories.php" target="contentFrame" style="color:black; text-decoration:none;">
                            Categories
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (can('steps.view')): ?>
                    <li style="margin-bottom:12px;">
                        <a href="steps.php" target="contentFrame" style="color:black; text-decoration:none;">
                            Steps
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (can('incidents.view')): ?>
                    <li style="margin-bottom:12px;">
                        <a href="incidents.php" target="contentFrame" style="color:black; text-decoration:none;">
                            Incidents
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (can('admins.view')): ?>
                    <li style="margin-bottom:12px;">
                        <a href="admins.php" target="contentFrame" style="color:black; text-decoration:none;">
                            Admins
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (can('users.view')): ?>
                    <li style="margin-bottom:12px;">
                        <a href="users.php" target="contentFrame" style="color:black; text-decoration:none;">
                            Users
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- RIGHT CONTENT AREA -->
        <div style="flex:1; background:#f4f4f4;">
            <iframe
                name="contentFrame"
                src="incidents.php"
                style="width:100%; height:100vh; border:none; background:white;">
            </iframe>
        </div>

    </div>

</body>

</html>