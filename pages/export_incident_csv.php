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

function csv_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function csv_pick_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

function csv_value(array $row, string $key): string
{
    $value = $row[$key] ?? '';
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    return (string)$value;
}

$incidentId = (int)($_GET['id'] ?? $_GET['incident_id'] ?? 0);
$localId = trim((string)($_GET['local_id'] ?? ''));

if ($incidentId <= 0 && $localId === '') {
    http_response_code(400);
    echo 'Invalid incident ID.';
    exit;
}

$incidentColumns = csv_table_columns($pdo, 'incidents');
$userColumns = csv_table_columns($pdo, 'app_users');

$incidentSelect = [];
foreach ($incidentColumns as $column) {
    $incidentSelect[] = "i.`$column` AS `incident_$column`";
}

$userSelect = [];
foreach ($userColumns as $column) {
    $userSelect[] = "u.`$column` AS `user_$column`";
}

$selectSql = implode(",\n        ", array_merge(
    $incidentSelect,
    $userSelect,
    [
        "c.name_en AS category_name_en",
        "c.name_ar AS category_name_ar"
    ]
));

$whereSql = $incidentId > 0 ? "i.id = :incident_id" : "i.local_id = :local_id";

$stmt = $pdo->prepare("
    SELECT
        $selectSql
    FROM incidents i
    LEFT JOIN categories c
        ON c.code = i.category_code
    LEFT JOIN app_users u
        ON u.device_id = i.device_id
    WHERE $whereSql
    LIMIT 1
");

if ($incidentId > 0) {
    $stmt->execute([':incident_id' => $incidentId]);
} else {
    $stmt->execute([':local_id' => $localId]);
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo 'Incident not found.';
    exit;
}

$realIncidentId = (int)($row['incident_id'] ?? $incidentId);
$userId = (int)($row['user_id'] ?? 0);

$contacts = [];
try {
    $contactColumns = csv_table_columns($pdo, 'user_emergency_contacts');

    $contactNameColumn = csv_pick_column($contactColumns, ['contact_name', 'name', 'full_name']);
    $contactPhoneColumn = csv_pick_column($contactColumns, ['phone', 'contact_phone', 'phone_number', 'mobile', 'number']);
    $contactRelationColumn = csv_pick_column($contactColumns, ['relation', 'relationship', 'type']);

    $contactSelectParts = [];
    $contactSelectParts[] = $contactNameColumn ? "`$contactNameColumn` AS contact_name" : "'' AS contact_name";
    $contactSelectParts[] = $contactPhoneColumn ? "`$contactPhoneColumn` AS phone" : "'' AS phone";
    $contactSelectParts[] = $contactRelationColumn ? "`$contactRelationColumn` AS relation" : "'' AS relation";

    if ($userId > 0) {
        $contactsStmt = $pdo->prepare("
            SELECT " . implode(', ', $contactSelectParts) . "
            FROM user_emergency_contacts
            WHERE user_id = :user_id
            ORDER BY id ASC
        ");
        $contactsStmt->execute([':user_id' => $userId]);
        $contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $contacts = [];
}

$images = [];
try {
    $imageColumns = csv_table_columns($pdo, 'incident_images');

    if ($realIncidentId > 0) {
        $imageSelectParts = [];
        foreach ($imageColumns as $column) {
            $imageSelectParts[] = "`$column`";
        }

        $imagesStmt = $pdo->prepare("
            SELECT " . implode(', ', $imageSelectParts) . "
            FROM incident_images
            WHERE incident_id = :incident_id
            ORDER BY id ASC
        ");
        $imagesStmt->execute([':incident_id' => $realIncidentId]);
        $images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $images = [];
}

$fileId = $realIncidentId > 0 ? $realIncidentId : time();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="incident_' . $fileId . '_full_information.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Section', 'Field', 'Value']);

fputcsv($output, ['Incident Information', 'Incident ID', csv_value($row, 'incident_id')]);
fputcsv($output, ['Incident Information', 'Local ID', csv_value($row, 'incident_local_id')]);
fputcsv($output, ['Incident Information', 'Device ID', csv_value($row, 'incident_device_id')]);
fputcsv($output, ['Incident Information', 'Category Code', csv_value($row, 'incident_category_code')]);
fputcsv($output, ['Incident Information', 'Category English', csv_value($row, 'category_name_en')]);
fputcsv($output, ['Incident Information', 'Category Arabic', csv_value($row, 'category_name_ar')]);
fputcsv($output, ['Incident Information', 'Urgency', csv_value($row, 'incident_urgency_level')]);
fputcsv($output, ['Incident Information', 'Confidence', csv_value($row, 'incident_confidence')]);
fputcsv($output, ['Incident Information', 'Manual Override', csv_value($row, 'incident_manual_override')]);
fputcsv($output, ['Incident Information', 'Language', csv_value($row, 'incident_lang')]);
fputcsv($output, ['Incident Information', 'Input Text', csv_value($row, 'incident_input_text')]);
fputcsv($output, ['Incident Information', 'Notes', csv_value($row, 'incident_notes')]);
fputcsv($output, ['Incident Information', 'Occurred At', csv_value($row, 'incident_occurred_at')]);
fputcsv($output, ['Incident Information', 'Synced At', csv_value($row, 'incident_synced_at')]);
fputcsv($output, ['Incident Information', 'Location Source', csv_value($row, 'incident_location_source')]);
fputcsv($output, ['Incident Information', 'Latitude', csv_value($row, 'incident_lat')]);
fputcsv($output, ['Incident Information', 'Longitude', csv_value($row, 'incident_lng')]);

fputcsv($output, []);
fputcsv($output, ['Patient Profile', 'User ID', csv_value($row, 'user_id')]);
fputcsv($output, ['Patient Profile', 'Full Name', csv_value($row, 'user_full_name')]);
fputcsv($output, ['Patient Profile', 'Email', csv_value($row, 'user_email')]);
fputcsv($output, ['Patient Profile', 'Phone', csv_value($row, 'user_phone')]);
fputcsv($output, ['Patient Profile', 'Age', csv_value($row, 'user_age')]);
fputcsv($output, ['Patient Profile', 'Sex', csv_value($row, 'user_sex')]);
fputcsv($output, ['Patient Profile', 'Blood Type', csv_value($row, 'user_blood_type')]);
fputcsv($output, ['Patient Profile', 'Allergies', csv_value($row, 'user_allergies')]);
fputcsv($output, ['Patient Profile', 'Conditions', csv_value($row, 'user_conditions')]);
fputcsv($output, ['Patient Profile', 'Medications', csv_value($row, 'user_medications')]);
fputcsv($output, ['Patient Profile', 'Patient Notes', csv_value($row, 'user_notes')]);
fputcsv($output, ['Patient Profile', 'Language', csv_value($row, 'user_language')]);
fputcsv($output, ['Patient Profile', 'Country Code', csv_value($row, 'user_country_code')]);
fputcsv($output, ['Patient Profile', 'Emergency Number', csv_value($row, 'user_emergency_number')]);
fputcsv($output, ['Patient Profile', 'Ambulance Number', csv_value($row, 'user_ambulance_number')]);
fputcsv($output, ['Patient Profile', 'Fire Number', csv_value($row, 'user_fire_number')]);

fputcsv($output, []);
fputcsv($output, ['Emergency Contacts', 'Name', 'Phone', 'Relation']);

if (count($contacts) > 0) {
    foreach ($contacts as $contact) {
        fputcsv($output, [
            'Emergency Contacts',
            csv_value($contact, 'contact_name'),
            csv_value($contact, 'phone'),
            csv_value($contact, 'relation'),
        ]);
    }
} else {
    fputcsv($output, ['Emergency Contacts', 'No emergency contacts found', '', '']);
}

fputcsv($output, []);
fputcsv($output, ['Uploaded Images', 'Field', 'Value']);

if (count($images) > 0) {
    foreach ($images as $index => $image) {
        foreach ($image as $key => $value) {
            fputcsv($output, ['Uploaded Images #' . ($index + 1), $key, csv_value($image, $key)]);
        }
    }
} else {
    fputcsv($output, ['Uploaded Images', 'No images uploaded', '']);
}

fclose($output);
exit;
