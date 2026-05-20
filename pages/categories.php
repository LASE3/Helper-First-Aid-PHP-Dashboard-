<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/content_version_helper.php';

require_perm('categories.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if (isset($_POST['add'])) {
    require_perm('categories.create');

    $code = trim($_POST['code'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $name_ar = trim($_POST['name_ar'] ?? '');
    $urgency_level = trim($_POST['urgency_level'] ?? 'medium');

    if ($code === '' || $name_en === '') {
        $error = "Code and English name are required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO categories (CODE, name_en, name_ar, urgency_level)
            VALUES (:code, :name_en, :name_ar, :urgency_level)
        ");

        $stmt->execute([
            ':code' => $code,
            ':name_en' => $name_en,
            ':name_ar' => $name_ar,
            ':urgency_level' => $urgency_level,
        ]);

        $success = "Category added successfully.";
        bump_content_version(
            $pdo,
            $_SESSION['admin_id'] ?? null,
            'Admin added a new category'
        );
    }
}

$stmt = $pdo->prepare("
    SELECT id, CODE, name_en, name_ar, urgency_level
    FROM categories
    ORDER BY id DESC
");

$stmt->execute();
$categories = $stmt->fetchAll();
?>
<html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories</title>
</head>

<body>
    <h2>Categories</h2>

    <?php if ($error !== ""): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="code" placeholder="Category Code" required><br>
        <input type="text" name="name_en" placeholder="English Name" required><br>
        <input type="text" name="name_ar" placeholder="Arabic Name"><br>

        <select name="urgency_level">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
        </select><br>

        <button name="add">Add Category</button>
    </form>

    <hr>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Code</th>
            <th>English Name</th>
            <th>Arabic Name</th>
            <th>Urgency Level</th>
        </tr>

        <?php foreach ($categories as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['id']) ?></td>
                <td><?= htmlspecialchars((string)$row['CODE']) ?></td>
                <td><?= htmlspecialchars((string)$row['name_en']) ?></td>
                <td><?= htmlspecialchars((string)$row['name_ar']) ?></td>
                <td><?= htmlspecialchars((string)$row['urgency_level']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <a href="dashboard.php">Back</a>

</body>

</html>