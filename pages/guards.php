<?php
declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

function perms(): array {
    return $_SESSION['perms'] ?? [];
}

function can(string $perm): bool {
    return in_array($perm, perms(), true);
}

function require_perm(string $perm): void {
    require_login();
    if (!can($perm)) {
        http_response_code(403);
        die("Forbidden");
    }
}
?>