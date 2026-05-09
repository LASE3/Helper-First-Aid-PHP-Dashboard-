<?php
declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('incidents.view');

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
| Add Incident
|--------------------------------------------------------------------------
*/
if (isset($_POST['add'])) {
    $categoryCode = trim($_POST['category_code'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? 'new');
    $urgency = trim($_POST['urgency'] ?? '');

    if ($categoryCode === '' || $desc === '') {
        $error = "Category and description are required.";
    } else {
        $image_name = "";

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadDir = __DIR__ . '/../uploads/incidents/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $image_name = uniqid('incident_', true) . "." . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image_name);
            } else {
                $error = "Invalid image type. Allowed: jpg, jpeg, png, gif, webp.";
            }
        }

        if ($error === "") {
                $stmt = $pdo->prepare("
                    INSERT INTO incidents 
                    (category_code, description, location, image, status, urgency)
                    VALUES 
                    (:category_code, :description, :location, :image, :status, :urgency)
                ");

                $stmt->execute([
                    ':category_code' => $categoryCode,
                    ':description' => $desc,
                    ':location' => $location,
                    ':image' => $image_name,
                    ':status' => $status,
                    ':urgency' => $urgency,
                ]);

            $message = "Incident added successfully.";
        }
    }
}
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterStartDate = trim($_GET['start_date'] ?? '');
$filterUrgency = trim($_GET['urgency'] ?? '');
$filterEndDate = trim($_GET['end_date'] ?? '');

/*
|--------------------------------------------------------------------------
| Load categories
|--------------------------------------------------------------------------
*/
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name_en ASC");
$categories = $catStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Load incidents with filters
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        incidents.*,
        categories.name_en AS category_name
    FROM incidents
    LEFT JOIN categories 
        ON incidents.category_code = categories.CODE
    WHERE 1=1
";

$params = [];

if ($filterCategory !== '') {
    $sql .= " AND incidents.category_code = :category";
    $params[':category'] = $filterCategory;
}

if ($filterStatus !== '') {
    $sql .= " AND incidents.status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterUrgency !== '') {
    $sql .= " AND incidents.urgency = :urgency";
    $params[':urgency'] = $filterUrgency;
}

if ($filterStartDate !== '') {
    $sql .= " AND DATE(incidents.created_at) >= :start_date";
    $params[':start_date'] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $sql .= " AND DATE(incidents.created_at) <= :end_date";
    $params[':end_date'] = $filterEndDate;
}

$sql .= " ORDER BY incidents.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incidents</title>
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

        .filter-row,
        .add-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }

        .filter-row > div,
        .add-form-row > div {
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
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .small-btn {
            width: auto;
            padding: 8px 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2>Incidents Dashboard</h2>

<?php if ($message !== ""): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h3>Filter Incidents</h3>
<form method="GET">
    <div class="filter-row">
        <div>
            <label>Category</label>
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars((string)$c['CODE']) ?>"
                        <?= $filterCategory === (string)$c['CODE'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$c['name_en']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="new" <?= $filterStatus === 'new' ? 'selected' : '' ?>>New</option>
                <option value="reviewed" <?= $filterStatus === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>
        <div>
            <label>Urgency</label>
            <select name="urgency">
                <option value="">All Urgency</option>
                <option value="low" <?= $filterUrgency === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $filterUrgency === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $filterUrgency === 'high' ? 'selected' : '' ?>>High</option>
            </select>
        </div>
        <div>
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($filterStartDate) ?>">
        </div>

        <div>
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($filterEndDate) ?>">
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="small-btn">Apply Filters</button>
        <a href="incidents.php">Clear Filters</a>
    </div>
</form>

<hr>

<h3>Add Incident</h3>
<form method="POST" enctype="multipart/form-data">
    <div class="add-form-row">
        <div>
            <label>Category</label>
            <select name="category_code" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars((string)$c['CODE']) ?>">
                        <?= htmlspecialchars((string)$c['name_en']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="new">New</option>
                <option value="reviewed">Reviewed</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
        <div>
            <label>Urgency</label>
            <select name="urgency">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            </select>                
        </div> 
        <div>
            <label>Location</label>
            <input type="text" name="location" placeholder="Location">
        </div>

        <div>
            <label>Image</label>
            <input type="file" name="image">
        </div>
    </div>

    <div>
        <label>Description</label>
        <textarea name="description" placeholder="Description" required></textarea>
    </div>

    <button type="submit" name="add" class="small-btn">Add Incident</button>
</form>

<hr>

<h3>Incidents List</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Category</th>
        <th>Description</th>
        <th>Location</th>
        <th>Image</th>
        <th>Status</th>
        <th>Urgency</th>
        <th>Date</th>
    </tr>

    <?php if (count($incidents) > 0): ?>
        <?php foreach ($incidents as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['id']) ?></td>
                <td><?= htmlspecialchars((string)($row['category_name'] ?? 'Unknown')) ?></td>
                <td><?= htmlspecialchars((string)$row['description']) ?></td>
                <td><?= htmlspecialchars((string)$row['location']) ?></td>
                <td>
                    <?php if (!empty($row['image'])): ?>
                        <img src="../uploads/incidents/<?= htmlspecialchars((string)$row['image']) ?>" width="70" alt="incident image">
                    <?php else: ?>
                        No Image
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$row['status']) ?></td>
                <td><?= htmlspecialchars((string) $row['urgency']) ?></td>
                <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7">No incidents found.</td>
        </tr>
    <?php endif; ?>
</table>

<br>
<a href="dashboard.php">Back</a>

</body>
</html>