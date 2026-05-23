<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';
require_once __DIR__ . '/content_version_helper.php';

require_perm('steps.view');

$message = "";
$error = "";

$checkMetaStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM content_meta");
$checkMetaStmt->execute();
$checkMeta = $checkMetaStmt->fetch();

if ((int)$checkMeta['total'] === 0) {
    $insertMetaStmt = $pdo->prepare("INSERT INTO content_meta (content_version) VALUES (1)");
    $insertMetaStmt->execute();
}

try {
    if (isset($_POST['update_version'])) {
        bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Manual content version update');
        $message = "Content version updated successfully.";
    }

    if (isset($_POST['add'])) {
        require_perm('steps.create');

        $categoryID = trim($_POST['category_id'] ?? '');
        $bodyEn = trim($_POST['body_en'] ?? '');
        $bodyAr = trim($_POST['body_ar'] ?? '');
        $stepNumber = trim($_POST['step_number'] ?? '');

        if ($categoryID === '' || $bodyEn === '' || $bodyAr === '' || $stepNumber === '') {
            $error = "Category, step number, English body, and Arabic body are required.";
        } elseif (!ctype_digit($categoryID) || !ctype_digit($stepNumber)) {
            $error = "Category and step number must be valid numbers.";
        } else {
            $image_name = "";

            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $uploadDir = __DIR__ . '/../uploads/steps/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed, true)) {
                    $image_name = uniqid('step_', true) . "." . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image_name);
                } else {
                    $error = "Invalid image type. Allowed: jpg, jpeg, png, gif, webp.";
                }
            }

            if ($error === "") {
                $catCodeStmt = $pdo->prepare("SELECT CODE FROM categories WHERE id = ?");
                $catCodeStmt->execute([(int)$categoryID]);
                $categoryCode = (string)$catCodeStmt->fetchColumn();

                $stmt = $pdo->prepare("
                    INSERT INTO guidance_steps
                    (category_id, category_code, step_no, title_en, title_ar, body_en, body_ar, warning_en, warning_ar, image_path, is_active)
                    VALUES
                    (:category_id, :category_code, :step_no, :title_en, :title_ar, :body_en, :body_ar, :warning_en, :warning_ar, :image_path, :is_active)
                ");

                $stmt->execute([
                    ':category_id' => (int)$categoryID,
                    ':category_code' => $categoryCode,
                    ':step_no' => (int)$stepNumber,
                    ':title_en' => trim($_POST['title_en'] ?? ''),
                    ':title_ar' => trim($_POST['title_ar'] ?? ''),
                    ':body_en' => $bodyEn,
                    ':body_ar' => $bodyAr,
                    ':warning_en' => trim($_POST['warning_en'] ?? ''),
                    ':warning_ar' => trim($_POST['warning_ar'] ?? ''),
                    ':image_path' => $image_name,
                    ':is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);

                $newStepId = (int)$pdo->lastInsertId();
                log_admin_action($pdo, 'create_step', 'step', $newStepId, 'Admin added a new step');
                bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin added a new guidance step');

                $message = "Step added successfully, and content version updated.";
            }
        }
    }

    if (isset($_POST['update_step'])) {
        require_perm('steps.edit');

        $id = (int)($_POST['id'] ?? 0);
        $categoryID = (int)($_POST['category_id'] ?? 0);
        $stepNumber = (int)($_POST['step_number'] ?? 0);

        if ($id <= 0 || $categoryID <= 0 || $stepNumber <= 0) {
            $error = "Step ID, category, and step number are required.";
        } else {
            $catCodeStmt = $pdo->prepare("SELECT CODE FROM categories WHERE id = ?");
            $catCodeStmt->execute([$categoryID]);
            $categoryCode = (string)$catCodeStmt->fetchColumn();

            $imageSql = "";
            $params = [
                ':id' => $id,
                ':category_id' => $categoryID,
                ':category_code' => $categoryCode,
                ':step_no' => $stepNumber,
                ':title_en' => trim($_POST['title_en'] ?? ''),
                ':title_ar' => trim($_POST['title_ar'] ?? ''),
                ':body_en' => trim($_POST['body_en'] ?? ''),
                ':body_ar' => trim($_POST['body_ar'] ?? ''),
                ':warning_en' => trim($_POST['warning_en'] ?? ''),
                ':warning_ar' => trim($_POST['warning_ar'] ?? ''),
                ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $uploadDir = __DIR__ . '/../uploads/steps/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($ext, $allowed, true)) {
                    $image_name = uniqid('step_', true) . "." . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image_name);
                    $imageSql = ", image_path = :image_path";
                    $params[':image_path'] = $image_name;
                } else {
                    $error = "Invalid image type. Allowed: jpg, jpeg, png, gif, webp.";
                }
            }

            if ($error === "") {
                $stmt = $pdo->prepare("
                    UPDATE guidance_steps
                    SET category_id = :category_id,
                        category_code = :category_code,
                        step_no = :step_no,
                        title_en = :title_en,
                        title_ar = :title_ar,
                        body_en = :body_en,
                        body_ar = :body_ar,
                        warning_en = :warning_en,
                        warning_ar = :warning_ar,
                        is_active = :is_active
                        $imageSql
                    WHERE id = :id
                ");

                $stmt->execute($params);
                log_admin_action($pdo, 'edit_step', 'step', $id, 'Admin edited a step');
                bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin edited a guidance step');
                $message = "Step updated successfully.";
            }
        }
    }

    if (isset($_POST['delete_step'])) {
        require_perm('steps.delete');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $error = "Invalid step ID.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM guidance_steps WHERE id = ?");
            $stmt->execute([$id]);

            log_admin_action($pdo, 'delete_step', 'step', $id, 'Admin deleted a step');
            bump_content_version($pdo, $_SESSION['admin_id'] ?? null, 'Admin deleted a guidance step');
            $message = "Step deleted successfully.";
        }
    }
} catch (PDOException $e) {
    $error = "Database error. The operation could not be completed.";
}

$metaStmt = $pdo->prepare("SELECT content_version, updated_at FROM content_meta WHERE id = 1");
$metaStmt->execute();
$contentMeta = $metaStmt->fetch();

$catStmt = $pdo->prepare("SELECT id, CODE, name_en FROM categories ORDER BY name_en ASC");
$catStmt->execute();
$categories = $catStmt->fetchAll();

$editStep = null;
if (isset($_GET['edit_step']) && can('steps.edit')) {
    $editStmt = $pdo->prepare("SELECT * FROM guidance_steps WHERE id = ?");
    $editStmt->execute([(int)$_GET['edit_step']]);
    $editStep = $editStmt->fetch();
}

$stmt = $pdo->prepare("
    SELECT guidance_steps.*, categories.name_en AS category_name
    FROM guidance_steps
    LEFT JOIN categories ON guidance_steps.category_id = categories.id
    ORDER BY guidance_steps.category_id ASC, guidance_steps.step_no ASC
");
$stmt->execute();
$steps = $stmt->fetchAll();

$stepsByCategory = [];
foreach ($steps as $stepRow) {
    $key = (string)($stepRow['category_name'] ?? 'Unknown');
    if (!isset($stepsByCategory[$key])) {
        $stepsByCategory[$key] = [];
    }
    $stepsByCategory[$key][] = $stepRow;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Steps Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <link rel="stylesheet" href="../assets/css/steps.css?v=20260520">
    <script src="../assets/js/confirm-actions.js?v=20260520" defer></script>
</head>

<body>

    <h2>Steps Dashboard</h2>

    <?php if ($message !== ""): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="version-box">
        <strong>Current Content Version:</strong>
        <?= htmlspecialchars((string)($contentMeta['content_version'] ?? '1')) ?>
        <br>
        <strong>Last Updated:</strong>
        <?= htmlspecialchars((string)($contentMeta['updated_at'] ?? '')) ?>
    </div>

    <form method="POST">
        <button type="submit" name="update_version" class="small-btn">Update Content Version</button>
    </form>

    <h3>Add Step</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div>
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars((string)$c['id']) ?>">
                            <?= htmlspecialchars((string)$c['name_en']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <br>
            <div>
                <label>Step Number</label>
                <input type="number" name="step_number" placeholder="Step Number" required>
            </div>
        </div>
        <label>Title (EN)</label>
        <input type="text" name="title_en" placeholder="Title (EN)" required>
        <br>
        <br>
        <label>Title (AR)</label>
        <input type="text" name="title_ar" placeholder="Title (AR)" required>
        <br>
        <br>
        <label>Body (EN)</label>
        <textarea name="body_en" placeholder="Body (EN)" required></textarea>
        <br>
        <br>
        <label>Body (AR)</label>
        <textarea name="body_ar" placeholder="Body (AR)" required></textarea>
        <br>
        <br>
        <label>Warning (EN)</label>
        <textarea name="warning_en" placeholder="Warning (EN)"></textarea>
        <br>
        <br>
        <label>Warning (AR)</label>
        <textarea name="warning_ar" placeholder="Warning (AR)"></textarea>
        <br>
        <br>
        <label>Image</label>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
        <br>
        <label>
            <input type="checkbox" name="is_active" checked>
            Active
        </label>

        <button type="submit" name="add" class="small-btn">Add Step</button>
    </form>

    <h3>Steps List</h3>

    <?php if (count($stepsByCategory) > 0): ?>
        <?php foreach ($stepsByCategory as $categoryTitle => $categorySteps): ?>
            <div class="category-step-card">
                <div class="category-step-header">
                    <div>
                        <h3><?= htmlspecialchars($categoryTitle) ?></h3>
                        <p><?= htmlspecialchars((string)count($categorySteps)) ?> step(s) in this category</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Title (EN)</th>
                            <th>Title (AR)</th>
                            <th>Image</th>
                            <th>Step Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                        <?php foreach ($categorySteps as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['id']) ?></td>
                                <td><?= htmlspecialchars((string)($row['title_en'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['title_ar'] ?? '')) ?></td>
                                <td>
                                    <?php if (!empty($row['image_path'])): ?>
                                        <img src="../uploads/steps/<?= htmlspecialchars((string)$row['image_path']) ?>" width="70" alt="step image">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string)($row['step_no'] ?? '')) ?></td>
                                <td><?= (int)($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
                                <td class="action-buttons">
                                    <?php if (can('steps.edit')): ?>
                                        <button
                                            type="button"
                                            class="btn-secondary"
                                            onclick="parent.openGlobalModal(document.getElementById('editStep<?= (int)$row['id'] ?>').innerHTML)">
                                            Edit
                                        </button>
                                    <?php endif; ?>

                                    <?php if (can('steps.delete')): ?>
                                        <form method="POST" class="inline-form js-confirm-delete">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
                                            <button type="submit" name="delete_step" class="btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (can('steps.edit')): ?>
                                        <div id="editStep<?= (int)$row['id'] ?>" style="display:none;">
                                            <div class="modal-header">
                                                <div>
                                                    <h3>Edit Step</h3>
                                                    <p>Update this guidance step. The Flutter content version will be updated after saving.</p>
                                                </div>
                                            </div>

                                            <form method="POST" action="pages/steps.php" enctype="multipart/form-data" class="js-confirm-save modal-form">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">

                                                <div class="row">
                                                    <div>
                                                        <label>Category</label>
                                                        <select name="category_id" required>
                                                            <?php foreach ($categories as $c): ?>
                                                                <option value="<?= htmlspecialchars((string)$c['id']) ?>"
                                                                    <?= (int)$row['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars((string)$c['name_en']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label>Step Number</label>
                                                        <input type="number" name="step_number" value="<?= htmlspecialchars((string)$row['step_no']) ?>" required>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div>
                                                        <label>Title (EN)</label>
                                                        <input type="text" name="title_en" value="<?= htmlspecialchars((string)$row['title_en']) ?>" required>
                                                    </div>

                                                    <div>
                                                        <label>Title (AR)</label>
                                                        <input type="text" name="title_ar" value="<?= htmlspecialchars((string)$row['title_ar']) ?>" required>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div>
                                                        <label>Body (EN)</label>
                                                        <textarea name="body_en" required><?= htmlspecialchars((string)$row['body_en']) ?></textarea>
                                                    </div>

                                                    <div>
                                                        <label>Body (AR)</label>
                                                        <textarea name="body_ar" required><?= htmlspecialchars((string)$row['body_ar']) ?></textarea>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div>
                                                        <label>Warning (EN)</label>
                                                        <textarea name="warning_en"><?= htmlspecialchars((string)$row['warning_en']) ?></textarea>
                                                    </div>

                                                    <div>
                                                        <label>Warning (AR)</label>
                                                        <textarea name="warning_ar"><?= htmlspecialchars((string)$row['warning_ar']) ?></textarea>
                                                    </div>
                                                </div>

                                                <label>Replace Image</label>
                                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">

                                                <label class="checkbox-label">
                                                    <input type="checkbox" name="is_active" <?= (int)$row['is_active'] === 1 ? 'checked' : '' ?>>
                                                    Active
                                                </label>

                                                <div class="modal-actions">
                                                    <button type="submit" name="update_step" class="btn-primary">Save Edit</button>
                                                    <button type="button" class="btn-secondary" onclick="parent.closeGlobalModal()">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="category-step-card empty-card">No steps found.</div>
    <?php endif; ?>

</body>

</html>