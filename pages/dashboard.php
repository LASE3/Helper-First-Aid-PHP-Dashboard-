<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('dashboard.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>

    <div class="layout">

        <aside class="sidebar">
            <div class="brand">
                <button class="menu-btn">☰</button>
                <span>FirstAid Admin</span>
            </div>

            <nav class="nav-menu">
                <?php if (can('categories.view')): ?>
                    <a href="categories.php" target="contentFrame">Categories</a>
                <?php endif; ?>

                <?php if (can('steps.view')): ?>
                    <a href="steps.php" target="contentFrame">Steps</a>
                <?php endif; ?>

                <?php if (can('incidents.view')): ?>
                    <a href="incidents.php" target="contentFrame">Incidents</a>
                <?php endif; ?>

                <?php if (can('admins.view')): ?>
                    <a href="admins.php" target="contentFrame">Admins</a>
                <?php endif; ?>

                <?php if (can('users.view')): ?>
                    <a href="users.php" target="contentFrame">Users</a>
                <?php endif; ?>
            </nav>
        </aside>

        <main class="main">

            <header class="topbar">
                <div>
                    <p>Good Morning,</p>
                    <h2><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></h2>
                </div>

                <div class="top-actions">
                    <a href="admin_profile.php" target="contentFrame">Profile</a>
                    <a href="settings.php" target="contentFrame">Settings</a>
                    <a href="logout.php" target="_top" class="logout">Logout</a>
                </div>
            </header>

            <section class="content">
                <iframe name="contentFrame" src="incidents.php"></iframe>
            </section>

        </main>

    </div>

</body>

</html>