<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';
require_once __DIR__ . '/content_version_helper.php';

require_perm('steps.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Ensure content_meta row exists
|--------------------------------------------------------------------------
*/
$checkMetaStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM content_meta");
$checkMetaStmt->execute();
$checkMeta = $checkMetaStmt->fetch();

if ((int)$checkMeta['total'] === 0) {
    $insertMetaStmt = $pdo->prepare("INSERT INTO content_meta (content_version) VALUES (1)");
    $insertMetaStmt->execute();
}

/*
|--------------------------------------------------------------------------
| Manual version update button
|--------------------------------------------------------------------------
*/
if (isset($_POST['update_version'])) {
    bump_content_version(
        $pdo,
        $_SESSION['admin_id'] ?? null,
        'Manual content version update'
    );

    $message = "Content version updated successfully.";
}
/*
|--------------------------------------------------------------------------
| Add Step
|--------------------------------------------------------------------------
*/
if (isset($_POST['add'])) {
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
            $stmt = $pdo->prepare("
                INSERT INTO guidance_steps 
                (
                    category_id,
                    category_code,
                    step_no,
                    title_en,
                    title_ar,
                    body_en,
                    body_ar,
                    warning_en,
                    warning_ar,
                    image_path,
                    is_active
                )
                VALUES
                (
                    :category_id,
                    :category_code,
                    :step_no,
                    :title_en,
                    :title_ar,
                    :body_en,
                    :body_ar,
                    :warning_en,
                    :warning_ar,
                    :image_path,
                    :is_active
                )
            ");

            $catCodeStmt = $pdo->prepare("
                 SELECT code 
                 FROM categories 
                 WHERE id = ?
             ");

            $catCodeStmt->execute([(int)$categoryID]);

            $categoryCode = $catCodeStmt->fetchColumn();

            $stmt->execute([
                ':category_id' => (int)$categoryID,
                ':category_code' => $categoryCode,
                ':step_no' => (int)$stepNumber,
                ':title_en' => trim($_POST['title_en'] ?? ''),
                ':title_ar' => trim($_POST['title_ar'] ?? ''),
                ':body_en' => trim($_POST['body_en'] ?? ''),
                ':body_ar' => trim($_POST['body_ar'] ?? ''),
                ':warning_en' => trim($_POST['warning_en'] ?? ''),
                ':warning_ar' => trim($_POST['warning_ar'] ?? ''),
                ':image_path' => $image_name,
                ':is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);

            $newStepId = (int)$pdo->lastInsertId();
            log_admin_action(
                $pdo,
                'create_step',
                'step',
                $newStepId,
                'Admin added a new step'
            );

            bump_content_version(
                $pdo,
                $_SESSION['admin_id'] ?? null,
                'Admin added a new guidance step'
            );

            $message = "Step added successfully, and content version updated.";
        }
    }
}
/*
|--------------------------------------------------------------------------
| Load current content version
|--------------------------------------------------------------------------
*/
$metaStmt = $pdo->prepare("
    SELECT content_version, updated_at 
    FROM content_meta 
    WHERE id = 1
");
$metaStmt->execute();
$contentMeta = $metaStmt->fetch();

/*
|--------------------------------------------------------------------------
| Load categories
|--------------------------------------------------------------------------
*/
$catStmt = $pdo->prepare("
    SELECT id, code, name_en 
    FROM categories 
    ORDER BY name_en ASC
");
$catStmt->execute();
$categories = $catStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Load steps
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        guidance_steps.*,
        categories.name_en AS category_name
    FROM guidance_steps
    LEFT JOIN categories 
        ON guidance_steps.category_id = categories.id
    ORDER BY guidance_steps.category_id ASC, guidance_steps.step_no ASC
");
$stmt->execute();
$steps = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Steps Dashboard</title>
    <link rel="stylesheet" href="../assets/css/steps.css">
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

    <h3>Manual Content Version Update</h3>
    <form method="POST">
        <button type="submit" name="update_version" class="small-btn">Update Content Version</button>
    </form>

    <hr>

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
            <div>
                <label>Step Number</label>
                <input type="number" name="step_number" placeholder="Step Number" required>
            </div>
        </div>
        <div>
            <label>Title (EN)</label>
            <input type="text" name="title_en" placeholder="Title (EN)" required>
        </div>
        <div>
            <label>Title (AR)</label>
            <input type="text" name="title_ar" placeholder="Title (AR)" required>
        </div>
        <div>
            <label>Body (EN)</label>
            <textarea name="body_en" placeholder="Body (EN)"></textarea>
        </div>
        <div>
            <label>Body (AR)</label>
            <textarea name="body_ar" placeholder="Body (AR)"></textarea>
        </div>
        <div>
            <label>Warning (EN)</label>
            <textarea name="warning_en" placeholder="Warning (EN)"></textarea>
        </div>
        <div>
            <label>Warning (AR)</label>
            <textarea name="warning_ar" placeholder="Warning (AR)"></textarea>
        </div>
        <div class="row">
            <div>
                <label>Image</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
            </div>
        </div>
        <!-- <div></div>  -->
        <label>
            <input type="checkbox" name="is_active" checked>
            Active
        </label>
        <button type="submit" name="add" class="small-btn">Add Step</button>
    </form>

    <hr>

    <h3>Steps List</h3>
    <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Title (EN)</th>
                <th>Title (AR)</th>
                <th>Image</th>
                <th>Step Number</th>
            </tr>

            <?php if (count($steps) > 0): ?>
                <?php foreach ($steps as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['id']) ?></td>
                        <td><?= htmlspecialchars((string)($row['category_name'] ?? 'Unknown')) ?></td>
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No steps found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <br>
    <a href="dashboard.php">Back</a>

</body>

</html>