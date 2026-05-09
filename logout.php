<?php
declare(strict_types=1);

session_start();
session_destroy();
header("Location: pages/login.php");
exit();

// declare(strict_types=1);
// require_once __DIR__ . '/../config/database.php';

// session_destroy();
// header("Location: login.php");
// exit;
?>