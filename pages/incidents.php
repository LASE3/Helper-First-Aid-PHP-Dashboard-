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

try {
    if (isset($_POST['update_incident'])) {
        require_perm('incidents.edit');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $error = "Invalid incident ID.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE incidents
                SET category_code = :category_code,
                    urgency_level = :urgency_level,
                    confidence = :confidence,
                    manual_override = :manual_override,
                    lang = :lang,
                    input_text = :input_text,
                    lat = :lat,
                    lng = :lng,
                    occurred_at = :occurred_at
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $id,
                ':category_code' => trim($_POST['category_code'] ?? ''),
                ':urgency_level' => trim($_POST['urgency_level'] ?? ''),
                ':confidence' => ($_POST['confidence'] ?? '') === '' ? null : (float)$_POST['confidence'],
                ':manual_override' => isset($_POST['manual_override']) ? 1 : 0,
                ':lang' => trim($_POST['lang'] ?? ''),
                ':input_text' => trim($_POST['input_text'] ?? ''),
                ':lat' => ($_POST['lat'] ?? '') === '' ? null : (float)$_POST['lat'],
                ':lng' => ($_POST['lng'] ?? '') === '' ? null : (float)$_POST['lng'],
                ':occurred_at' => trim($_POST['occurred_at'] ?? date('Y-m-d H:i:s')),
            ]);

            $message = "Incident updated successfully.";
        }
    }

    if (isset($_POST['delete_incident'])) {
        require_perm('incidents.delete');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $error = "Invalid incident ID.";
        } else {
            $imgDelete = $pdo->prepare("DELETE FROM incident_images WHERE incident_id = ?");
            $imgDelete->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
            $stmt->execute([$id]);

            $message = "Incident deleted successfully.";
        }
    }
} catch (PDOException $e) {
    $error = "Database error. The incident operation could not be completed.";
}


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

$editIncident = null;
if (isset($_GET['edit_incident']) && can('incidents.edit')) {
    $editStmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
    $editStmt->execute([(int)$_GET['edit_incident']]);
    $editIncident = $editStmt->fetch();
}


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

$incidentImages = [];

if (count($incidents) > 0) {
    $incidentIds = array_column($incidents, 'id');
    $placeholders = implode(',', array_fill(0, count($incidentIds), '?'));

    $imgStmt = $pdo->prepare("
        SELECT incident_id, image_path 
        FROM incident_images 
        WHERE incident_id IN ($placeholders)
    ");

    $imgStmt->execute($incidentIds);
    $images = $imgStmt->fetchAll();

    foreach ($images as $img) {
        $incidentImages[(int)$img['incident_id']][] = $img['image_path'];
    }
}
/*
|--------------------------------------------------------------------------
| Charts Data
|--------------------------------------------------------------------------
*/

$categoryChartStmt = $pdo->query("
    SELECT 
        COALESCE(categories.name_en, incidents.category_code) AS category_name,
        COUNT(*) AS total
    FROM incidents
    LEFT JOIN categories 
        ON incidents.category_code = categories.CODE
    GROUP BY incidents.category_code, categories.name_en
    ORDER BY total DESC
");

$categoryChartData = $categoryChartStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [];
$categoryValues = [];

foreach ($categoryChartData as $item) {
    $categoryLabels[] = $item['category_name'];
    $categoryValues[] = (int)$item['total'];
}

$urgencyChartStmt = $pdo->query("
    SELECT 
        urgency_level,
        COUNT(*) AS total
    FROM incidents
    GROUP BY urgency_level
    ORDER BY total DESC
");

$urgencyChartData = $urgencyChartStmt->fetchAll(PDO::FETCH_ASSOC);

$urgencyLabels = [];
$urgencyValues = [];

foreach ($urgencyChartData as $item) {
    $urgencyLabels[] = $item['urgency_level'] ?: 'Unknown';
    $urgencyValues[] = (int)$item['total'];
}

$totalIncidents = count($incidents);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Incidents</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/incidents.css?v=20260523">
    <script src="../assets/js/confirm-actions.js?v=20260520" defer></script>
</head>

<body>

    <div class="dashboard-header">
        <h2>Incidents Dashboard</h2>
        <p>Monitor emergency incidents, urgency levels, locations, and uploaded reports.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <h4>Total Incidents</h4>
            <p><?= htmlspecialchars((string)$totalIncidents) ?></p>
        </div>

        <div class="stat-card">
            <h4>Categories</h4>
            <p><?= htmlspecialchars((string)count($categoryLabels)) ?></p>
        </div>

        <div class="stat-card">
            <h4>Urgency Types</h4>
            <p><?= htmlspecialchars((string)count($urgencyLabels)) ?></p>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-box">
            <h3>Incidents per Category</h3>
            <canvas id="categoryChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Incidents per Urgency</h3>
            <canvas id="urgencyChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Filter Incidents</h3>

        <form method="GET" class="filter-form">
            <div class="form-grid">
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
                        <option value="extreme" <?= $filterUrgency === 'extreme' ? 'selected' : '' ?>>Extreme</option>
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

            <div class="filter-actions">
                <button type="submit" class="btn-primary">Apply Filters</button>
                <a href="incidents.php" class="clear-link">Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="export-row">
        <a href="export_incidents.php?category=<?= urlencode($filterCategory) ?>&urgency=<?= urlencode($filterUrgency) ?>&start_date=<?= urlencode($filterStartDate) ?>&end_date=<?= urlencode($filterEndDate) ?>"
            class="btn-primary">
            Export CSV
        </a>
    </div>

    <h2 class="section-title">Incidents List</h2>

    <p>
        Showing <?= htmlspecialchars((string)count($incidents)) ?> incident(s).
    </p>

    <div class="incidents-table-card">
        <table>
            <tr>
                <th>ID</th>
                <th>Device ID</th>
                <th>Patient</th>
                <th>Category</th>
                <th>Urgency</th>
                <th>Confidence</th>
                <th>Manual Override</th>
                <th>Language</th>
                <th>Input Text</th>
                <th>Location</th>
                <th>Images</th>
                <th>Date</th>
                <th>Sync</th>
                <th>Actions</th>
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
                        <td><?= ((int)($row['manual_override'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                        <td><?= htmlspecialchars((string)($row['lang'] ?? '')) ?></td>
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
                        <td>
                            <?php if (!empty($row['synced_at'])): ?>

                                <span style="color:green;">
                                    Synced
                                </span>

                            <?php else: ?>

                                <span style="color:red;">
                                    Pending
                                </span>

                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <a href="incident_view.php?id=<?= urlencode((string)$row['id']) ?>" class="btn-secondary">
                                View
                            </a>

                            <?php if (can('incidents.edit')): ?>
                                <button type="button" class="btn-secondary" onclick="parent.openGlobalModal(document.getElementById('editIncident<?= (int)$row['id'] ?>').innerHTML)">
                                    Edit
                                </button>
                            <?php endif; ?>

                            <?php if (can('incidents.delete')): ?>
                                <form method="POST" class="inline-form js-confirm-delete">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
                                    <button type="submit" name="delete_incident" class="btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>

                            <?php if (can('incidents.edit')): ?>
                                <div id="editIncident<?= (int)$row['id'] ?>" style="display:none;">
                                    <div class="modal-header">
                                        <div>
                                            <h3>Edit Incident #<?= htmlspecialchars((string)$row['id']) ?></h3>
                                            <p>Update incident data. This window floats above the full dashboard.</p>
                                        </div>
                                    </div>
                                    <form method="POST" action="pages/incidents.php" class="js-confirm-save modal-form">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
                                        <div class="form-grid modal-grid">
                                            <div>
                                                <label>Category</label>
                                                <select name="category_code" required>
                                                    <?php foreach ($categories as $c): ?>
                                                        <option value="<?= htmlspecialchars((string)$c['CODE']) ?>" <?= (string)$row['category_code'] === (string)$c['CODE'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars((string)$c['name_en']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Urgency</label>
                                                <select name="urgency_level">
                                                    <?php foreach (['low', 'medium', 'high', 'extreme'] as $level): ?>
                                                        <option value="<?= $level ?>" <?= (string)$row['urgency_level'] === $level ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div><label>Confidence</label><input type="number" step="0.01" name="confidence" value="<?= htmlspecialchars((string)($row['confidence'] ?? '')) ?>"></div>
                                            <div><label>Language</label><input type="text" name="lang" value="<?= htmlspecialchars((string)($row['lang'] ?? '')) ?>"></div>
                                            <div><label>Latitude</label><input type="text" name="lat" value="<?= htmlspecialchars((string)($row['lat'] ?? '')) ?>"></div>
                                            <div><label>Longitude</label><input type="text" name="lng" value="<?= htmlspecialchars((string)($row['lng'] ?? '')) ?>"></div>
                                            <div><label>Occurred At</label><input type="text" name="occurred_at" value="<?= htmlspecialchars((string)($row['occurred_at'] ?? '')) ?>"></div>
                                            <div class="checkbox-cell"><label><input type="checkbox" name="manual_override" <?= (int)($row['manual_override'] ?? 0) === 1 ? 'checked' : '' ?>> Manual Override</label></div>
                                            <div class="wide-field"><label>Input Text</label><textarea name="input_text"><?= htmlspecialchars((string)($row['input_text'] ?? '')) ?></textarea></div>
                                        </div>
                                        <div class="modal-actions">
                                            <button type="submit" name="update_incident" class="btn-primary">Save Edit</button>
                                            <button type="button" class="btn-secondary" onclick="parent.closeGlobalModal()">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="14">No incidents found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    <br>

    <script>
        window.categoryLabels = <?= json_encode($categoryLabels) ?>;
        window.categoryValues = <?= json_encode($categoryValues) ?>;
        window.urgencyLabels = <?= json_encode($urgencyLabels) ?>;
        window.urgencyValues = <?= json_encode($urgencyValues) ?>;
    </script>

    <script src="../assets/js/incidents.js"></script>
</body>

</html>