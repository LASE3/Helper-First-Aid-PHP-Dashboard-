<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/content_version_helper.php';

require_perm('categories.view');

$error = "";
$success = "";

try {
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

            bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin added a new category');
            $success = "Category added successfully.";
        }
    }

    if (isset($_POST['update_category'])) {
        require_perm('categories.edit');

        $id = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $name_ar = trim($_POST['name_ar'] ?? '');
        $urgency_level = trim($_POST['urgency_level'] ?? 'medium');

        if ($id <= 0 || $code === '' || $name_en === '') {
            $error = "Valid ID, code, and English name are required.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE categories
                SET CODE = :code,
                    name_en = :name_en,
                    name_ar = :name_ar,
                    urgency_level = :urgency_level
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $id,
                ':code' => $code,
                ':name_en' => $name_en,
                ':name_ar' => $name_ar,
                ':urgency_level' => $urgency_level,
            ]);

            $syncSteps = $pdo->prepare("
                UPDATE guidance_steps
                SET category_code = :code
                WHERE category_id = :id
            ");
            $syncSteps->execute([':code' => $code, ':id' => $id]);

            bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin edited a category');
            $success = "Category updated successfully.";
        }
    }

    if (isset($_POST['delete_category'])) {
        require_perm('categories.delete');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $error = "Invalid category ID.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin deleted a category');
            $success = "Category deleted successfully.";
        }
    }
} catch (PDOException $e) {
    $error = "Database error. This category may be used by steps or incidents, so delete/edit was blocked.";
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
    <link rel="stylesheet" href="../assets/css/categories.css?v=20260520">
    <script src="../assets/js/confirm-actions.js?v=20260520" defer></script>
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
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($categories as $row): ?>
                    <?php $rowId = (int)$row['id']; ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$rowId) ?></td>

                        <td>
                            <?php if (can('categories.edit')): ?>
                                <input form="edit-category-<?= $rowId ?>" type="text" name="code" value="<?= htmlspecialchars((string)$row['CODE']) ?>" required>
                            <?php else: ?>
                                <?= htmlspecialchars((string)$row['CODE']) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (can('categories.edit')): ?>
                                <input form="edit-category-<?= $rowId ?>" type="text" name="name_en" value="<?= htmlspecialchars((string)$row['name_en']) ?>" required>
                            <?php else: ?>
                                <?= htmlspecialchars((string)$row['name_en']) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (can('categories.edit')): ?>
                                <input form="edit-category-<?= $rowId ?>" type="text" name="name_ar" value="<?= htmlspecialchars((string)$row['name_ar']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars((string)$row['name_ar']) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (can('categories.edit')): ?>
                                <select form="edit-category-<?= $rowId ?>" name="urgency_level">
                                    <?php foreach (['low', 'medium', 'high', 'critical'] as $level): ?>
                                        <option value="<?= $level ?>" <?= (string)$row['urgency_level'] === $level ? 'selected' : '' ?>>
                                            <?= ucfirst($level) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span class="urgency <?= htmlspecialchars((string)$row['urgency_level']) ?>">
                                    <?= htmlspecialchars((string)$row['urgency_level']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="action-buttons">
                            <?php if (can('categories.edit')): ?>
                                <form id="edit-category-<?= $rowId ?>" method="POST" class="inline-form js-confirm-save">
                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                    <button type="submit" name="update_category" class="btn-secondary">Save</button>
                                </form>
                            <?php endif; ?>

                            <?php if (can('categories.delete')): ?>
                                <form method="POST" class="inline-form js-confirm-delete">
                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                    <button type="submit" name="delete_category" class="btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>