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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=incidents_export.csv');

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
    'Occurred At',
    'Synced At',
    'Input Text',
    'Notes'
]);

$stmt = $pdo->query("
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
    ORDER BY incidents.occurred_at DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['device_id'],
        $row['full_name'],
        $row['category_code'],
        $row['category_name'],
        $row['urgency_level'],
        $row['confidence'],
        $row['manual_override'],
        $row['lang'],
        $row['lat'],
        $row['lng'],
        $row['location_source'],
        $row['occurred_at'],
        $row['synced_at'],
        $row['input_text'],
        $row['notes']
    ]);
}

fclose($output);
exit;

?>