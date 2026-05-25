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

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'admin';

function safe_frame_page(string $page): string
{
    $page = trim(urldecode($page));

    if ($page === '') {
        return 'incidents.php';
    }

    $parts = parse_url($page);
    $path = basename((string)($parts['path'] ?? ''));
    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';

    if ($path === 'incident_view.php' && !str_contains($query, 'id=')) {
        return 'incidents.php';
    }

    if ($path === 'user_view.php' && !str_contains($query, 'id=') && !str_contains($query, 'device_id=') && !str_contains($query, 'device=')) {
        return 'users.php';
    }

    if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $path)) {
        return 'incidents.php';
    }

    return $path . $query;
}

$requestedPage = (string)($_GET['page'] ?? '');
if ($requestedPage !== '') {
    $initialFramePage = safe_frame_page($requestedPage);
} else {
    $initialFramePage = safe_frame_page((string)($_COOKIE['firstaid_last_page'] ?? 'incidents.php'));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>FirstAid Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- version added to prevent browser cache from showing old CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=20260523">
    <script>
        // Prevent the dashboard from being opened inside its own iframe.
        // This fixes the duplicated sidebar/topbar bug after clicking Dashboard from detail pages.
        if (window.top !== window.self) {
            window.top.location.href = window.location.href;
        }
    </script>
</head>

<body>
    <div class="admin-layout">

        <aside class="sidebar">
            <div class="brand-row">
                <button type="button" class="menu-button" aria-label="Open menu">☰</button>
                <span class="brand-name">FirstAid Admin</span>
            </div>

            <nav class="sidebar-menu">
                <?php if (can('categories.view')): ?>
                    <a href="categories.php" target="contentFrame" class="nav-link">
                        <span class="menu-icon">📑</span>
                        <span class="menu-text">Categories</span>
                    </a>
                <?php endif; ?>

                <?php if (can('steps.view')): ?>
                    <a href="steps.php" target="contentFrame" class="nav-link">
                        <span class="menu-icon">🧾</span>
                        <span class="menu-text">Steps</span>
                    </a>
                <?php endif; ?>

                <?php if (can('incidents.view')): ?>
                    <a href="incidents.php" target="contentFrame" class="nav-link active">
                        <span class="menu-icon">📊</span>
                        <span class="menu-text">Incidents</span>
                    </a>
                <?php endif; ?>

                <?php if (can('admins.view')): ?>
                    <a href="admins.php" target="contentFrame" class="nav-link">
                        <span class="menu-icon">👤</span>
                        <span class="menu-text">Admins</span>
                    </a>
                <?php endif; ?>

                <?php if (can('users.view')): ?>
                    <a href="users.php" target="contentFrame" class="nav-link">
                        <span class="menu-icon">👥</span>
                        <span class="menu-text">Users</span>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>
        <?php
        $hour = (int)date('H');

        if ($hour >= 5 && $hour < 12) {
            $greeting = "Good Morning";
        } elseif ($hour >= 12 && $hour < 17) {
            $greeting = "Good Afternoon";
        } elseif ($hour >= 17 && $hour < 21) {
            $greeting = "Good Evening";
        } else {
            $greeting = "Good Night";
        }
        ?>

        <main class="main-panel">
            <header class="topbar">
                <div class="welcome-box">
                    <p><?= $greeting ?>,</p>
                    <h1><?= htmlspecialchars((string)$adminName) ?></h1>
                    <span><?= htmlspecialchars((string)$adminRole) ?></span>
                </div>

                <div class="topbar-actions">
                    <a href="admin_profile.php" target="contentFrame" class="top-link">Profile</a>
                    <a href="logout.php" target="_top" class="logout-link">Logout</a>
                </div>
            </header>

            <section class="dashboard-content">
                <iframe
                    id="contentFrame"
                    name="contentFrame"
                    src="<?= htmlspecialchars($initialFramePage, ENT_QUOTES, 'UTF-8') ?>"
                    title="Dashboard content">
                </iframe>
            </section>
        </main>

    </div>

    <div class="session-modal" id="sessionModal" aria-hidden="true">
        <div class="session-modal-box">
            <h2>Are you still there?</h2>
            <p>Your session will end soon because you were inactive.</p>
            <div class="session-actions">
                <button type="button" id="stayLoggedInBtn">Yes, keep me logged in</button>
                <a href="logout.php" target="_top">Logout</a>
            </div>
        </div>
    </div>
    <script src="../assets/js/dashboard.js?v=20260524"></script>
    <script src="../assets/js/session-timeout.js?v=20260520"></script>
    <div id="globalModalOverlay" class="global-modal-overlay">
        <div class="global-modal-box">

            <button class="global-modal-close"
                onclick="closeGlobalModal()">
                ×
            </button>

            <div id="globalModalContent"></div>

        </div>
    </div>
</body>

</html>