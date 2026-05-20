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

$filterCategory = trim($_GET['category'] ?? '');
$filterUrgency = trim($_GET['urgency'] ?? '');
$filterStartDate = trim($_GET['start_date'] ?? '');
$filterEndDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT
        incidents.id,
        incidents.device_id,
        app_users.full_name,
        incidents.category_code,
        categories.name_en AS category_name,
        incidents.urgency_level,
        incidents.confidence,
        incidents.manual_override,
        incidents.lang,
        incidents.lat,
        incidents.lng,
        incidents.location_source,
        incidents.occurred_at,
        incidents.synced_at,
        incidents.input_text,
        incidents.notes
    FROM incidents
    LEFT JOIN app_users
        ON incidents.device_id = app_users.device_id
    LEFT JOIN categories
        ON incidents.category_code = categories.CODE
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

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="incidents_export.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Device ID',
    'Patient Name',
    'Category Code',
    'Category Name',
    'Urgency',
    'Confidence',
    'Manual Override',
    'Language',
    'Latitude',
    'Longitude',
    'Location Source',
    'Google Maps Link',
    'Occurred At',
    'Synced At',
    'Input Text',
    'Notes'
]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $mapLink = '';

    if (!empty($row['lat']) && !empty($row['lng'])) {
        $mapLink = 'https://www.google.com/maps?q=' . $row['lat'] . ',' . $row['lng'];
    }

    fputcsv($output, [
        $row['id'] ?? '',
        $row['device_id'] ?? '',
        $row['full_name'] ?? '',
        $row['category_code'] ?? '',
        $row['category_name'] ?? '',
        $row['urgency_level'] ?? '',
        $row['confidence'] ?? '',
        ((int)($row['manual_override'] ?? 0) === 1) ? 'Yes' : 'No',
        $row['lang'] ?? '',
        $row['lat'] ?? '',
        $row['lng'] ?? '',
        $row['location_source'] ?? '',
        $mapLink,
        $row['occurred_at'] ?? '',
        $row['synced_at'] ?? '',
        $row['input_text'] ?? '',
        $row['notes'] ?? ''
    ]);
}

fclose($output);
exit;
