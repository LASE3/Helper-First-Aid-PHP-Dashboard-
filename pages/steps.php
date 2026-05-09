<?php
declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';

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
    $updateVersionStmt = $pdo->prepare("
        UPDATE content_meta 
        SET content_version = content_version + 1 
        WHERE id = 1
    ");
    $updateVersionStmt->execute();

    $message = "Content version updated successfully.";
}

/*
|--------------------------------------------------------------------------
| Add Step
|--------------------------------------------------------------------------
*/
if (isset($_POST['add'])) {
    $categoryID = trim($_POST['category_id'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $stepNumber = trim($_POST['step_number'] ?? '');

    if ($categoryID === '' || $desc === '' || $stepNumber === '') {
        $error = "Category, description, and step number are required.";
    }
    elseif (!ctype_digit($categoryID) || !ctype_digit($stepNumber)) {
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
                INSERT INTO steps (category_id, description, image, step_number)
                VALUES (:category_id, :description, :image, :step_number)
            ");
            
            $stmt->execute([
                ':category_id' => (int)$categoryID,
                ':description' => $desc,
                ':image' => $image_name,
                ':step_number' => (int)$stepNumber,
            ]);

            $newStepId = (int)$pdo->lastInsertId();    
                log_admin_action(
                    $pdo,
                    'create_step',
                    'step',
                    $newStepId,
                    'Admin added a new step'
            );

            $updateVersionStmt = $pdo->prepare("
                UPDATE content_meta 
                SET content_version = content_version + 1 
                WHERE id = 1
            ");
            $updateVersionStmt->execute();

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
    SELECT id, name_en 
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
        steps.*,
        categories.name_en AS category_name
    FROM steps
    LEFT JOIN categories 
        ON steps.category_id = categories.id
    ORDER BY steps.category_id ASC, steps.step_number ASC
");
$stmt->execute();
$steps = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Steps Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        h2, h3 {
            margin-bottom: 10px;
        }

        form {
            margin-bottom: 20px;
        }

        input, select, textarea, button {
            margin: 5px 0;
            padding: 8px;
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }

        .row > div {
            flex: 1;
            min-width: 180px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th, td {
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f4f4f4;
        }

        img {
            border-radius: 4px;
        }

        .message {
            color: green;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .error {
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .small-btn {
            width: auto;
            padding: 8px 14px;
            cursor: pointer;
        }

        .version-box {
            padding: 12px;
            background: #f7f7f7;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
    </style>
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
    <div class="row">
        <div>
            <label>Image</label>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
        </div>
    </div>

    <div>
        <label>Description</label>
        <textarea name="description" placeholder="Description"></textarea>
    </div>

    <button type="submit" name="add" class="small-btn">Add Step</button>
</form>

<hr>

<h3>Steps List</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Category</th>
        <th>Description</th>
        <th>Image</th>
        <th>Step Number</th>
    </tr>

    <?php if (count($steps) > 0): ?>
        <?php foreach ($steps as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['id']) ?></td>
                <td><?= htmlspecialchars((string)($row['category_name'] ?? 'Unknown')) ?></td>
                <td><?= htmlspecialchars((string)($row['description'] ?? '')) ?></td>
                <td>
                    <?php if (!empty($row['image'])): ?>
                        <img src="../uploads/steps/<?= htmlspecialchars((string)$row['image']) ?>" width="70" alt="step image">
                    <?php else: ?>
                        No Image
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($row['step_number'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7">No steps found.</td>
        </tr>
    <?php endif; ?>
</table>

<br>
<a href="dashboard.php">Back</a>

</body>
</html>