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
| Filters
|--------------------------------------------------------------------------
*/
$filterCategory = trim($_GET['category'] ?? '');
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
        categories.name_en AS category_name,
        app_users.full_name AS patient_name
    FROM incidents
    LEFT JOIN categories 
        ON incidents.category_code = categories.CODE
    LEFT JOIN app_users
        ON incidents.device_id = app_users.device_id
    WHERE 1=1
";

$params = [];

if ($filterCategory !== '') {
    $sql .= " AND incidents.category_code = :category";
    $params[':category'] = $filterCategory;
}

if ($filterUrgency !== '') {
    $sql .= " AND incidents.urgency_level = :urgency";
    $params[':urgency'] = $filterUrgency;
}

if ($filterStartDate !== '') {
    $sql .= " AND DATE(incidents.occurred_at) >= :start_date";
    $params[':start_date'] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $sql .= " AND DATE(incidents.occurred_at) <= :end_date";
    $params[':end_date'] = $filterEndDate;
}

$sql .= " ORDER BY incidents.occurred_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

$incidentImages =[];

if(count($incidents) > 0){
    $incidentIds = array_column($incidents, 'id');
    $placeholders = implode(',', array_fill(0, count($incidentIds), '?'));

    $imgStmt = $pdo->prepare("
        SELECT incident_id, image_path 
        FROM incident_images 
        WHERE incident_id IN ($placeholders)
    ");

    $imgStmt->execute($incidentIds);
    $images = $imgStmt->fetchAll();

    foreach($images as $img){
        $incidentImages[(int)$img['incident_id']][] = $img['image_path'];
    }
}
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

<a href="export_incidents.php" class="small-btn">Export CSV</a>

<h3>Incidents List</h3>
<table>
<tr>
    <th>ID</th>
    <th>Device ID</th>
    <th>Patient</th>
    <th>Category</th>
    <th>Urgency</th>
    <th>Confidence</th>
    <th>Action</th>
    <th>Input Text</th>
    <th>Location</th>
    <th>Images</th>
    <th>Date</th>
    <th>Manual Override</th>
    <th>Language</th>
    
</tr>

    <?php if (count($incidents) > 0): ?>
        <?php foreach ($incidents as $row): ?>
           <tr>
    <td><?= htmlspecialchars((string)$row['id']) ?></td>
    <td><?= htmlspecialchars((string)($row['device_id'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($row['patient_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($row['category_name'] ?? $row['category_code'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($row['urgency_level'] ?? '')) ?></td>
    <td>
       <?= $row['confidence'] !== null 
             ? htmlspecialchars(number_format((float)$row['confidence'], 2))
             : 'N/A' ?>
    </td>
    <td><?= htmlspecialchars((string)($row['input_text'] ?? '')) ?></td>
    <td>
        <?php if (!empty($row['lat']) && !empty($row['lng'])): ?>
            <a target="_blank" href="https://www.google.com/maps?q=<?= htmlspecialchars((string)$row['lat']) ?>,<?= htmlspecialchars((string)$row['lng']) ?>">
                Open Map
            </a>
        <?php else: ?>
            No Location
        <?php endif; ?>
    </td>
    <td>
        <?php
        $rowImages = $incidentImages[(int)$row['id']] ?? [];
        ?>

        <?php if (count($rowImages) > 0): ?>
            <?php foreach ($rowImages as $imagePath): ?>
                <img src="../<?= htmlspecialchars((string)$imagePath) ?>" width="70" alt="incident image">
            <?php endforeach; ?>
        <?php else: ?>
            No Image
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars((string)($row['occurred_at'] ?? '')) ?></td>
    <td><?= ((int)($row['manual_override'] ?? 0) === 1) ?></td>
    <td><?= htmlspecialchars((string)($row['lang'] ?? '')) ?></td>
    <td>
    <a href="incident_view.php?id=<?= urlencode((string)$row['id']) ?>">
        View
    </a>
</td>
</tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="13">No incidents found.</td>
        </tr>
    <?php endif; ?>
</table>

<br>
<a href="dashboard.php">Back</a>

    </body>
</html>