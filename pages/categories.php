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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Categories</title>
    <link rel="stylesheet" href="../assets/css/categories.css">
</head>

<body>

    <div class="page-header">
        <h2>Categories</h2>
        <p>Manage emergency categories used by the Flutter app.</p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Add Category</h3>

        <form method="POST" class="category-form">
            <div class="form-grid">
                <input type="text" name="code" placeholder="Category Code" required>
                <input type="text" name="name_en" placeholder="English Name" required>
                <input type="text" name="name_ar" placeholder="Arabic Name">

                <select name="urgency_level">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>

            <button type="submit" name="add" class="btn-primary">Add Category</button>
        </form>
    </div>

    <div class="card">
        <h3>Categories List</h3>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>English Name</th>
                    <th>Arabic Name</th>
                    <th>Urgency Level</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($categories as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['id']) ?></td>
                        <td><?= htmlspecialchars((string)$row['CODE']) ?></td>
                        <td><?= htmlspecialchars((string)$row['name_en']) ?></td>
                        <td><?= htmlspecialchars((string)$row['name_ar']) ?></td>
                        <td>
                            <span class="urgency <?= htmlspecialchars((string)$row['urgency_level']) ?>">
                                <?= htmlspecialchars((string)$row['urgency_level']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>