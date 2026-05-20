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

$incidentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incidentId <= 0) {
    die("Invalid incident ID.");
}

$stmt = $pdo->prepare("
    SELECT 
        incidents.*,
        categories.name_en AS category_name,
        app_users.id AS user_id,
        app_users.full_name,
        app_users.sex,
        app_users.age,
        app_users.blood_type,
        app_users.allergies,
        app_users.conditions,
        app_users.medications,
        app_users.notes AS patient_notes
    FROM incidents
    LEFT JOIN categories 
        ON incidents.category_code = categories.CODE
    LEFT JOIN app_users 
        ON incidents.device_id = app_users.device_id
    WHERE incidents.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $incidentId
]);

$incident = $stmt->fetch();

if (!$incident) {
    die("Incident not found.");
}

$imgStmt = $pdo->prepare("
    SELECT *
    FROM incident_images
    WHERE incident_id = :incident_id
    ORDER BY uploaded_at ASC
");

$imgStmt->execute([
    ':incident_id' => $incidentId
]);

$images = $imgStmt->fetchAll();

$contacts = [];

if (!empty($incident['user_id'])) {
    $contactStmt = $pdo->prepare("
        SELECT *
        FROM user_emergency_contacts
        WHERE user_id = :user_id
        ORDER BY id ASC
    ");

    $contactStmt->execute([
        ':user_id' => $incident['user_id']
    ]);

    $contacts = $contactStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident Details</title>
    <link rel="stylesheet" href="assets/css/incident_view.css">
</head>
<body>

<h2>Incident Details</h2>

<div class="card">
    <h3>Incident Information</h3>
    <table>
        <tr>
            <th>ID</th>
            <td><?= htmlspecialchars((string)$incident['id']) ?></td>
        </tr>
        <tr>
            <th>Device ID</th>
            <td><?= htmlspecialchars((string)$incident['device_id']) ?></td>
        </tr>
        <tr>
            <th>Category</th>
            <td><?= htmlspecialchars((string)($incident['category_name'] ?? $incident['category_code'] ?? 'Unknown')) ?></td>
        </tr>
        <tr>
            <th>Urgency</th>
            <td><?= htmlspecialchars((string)($incident['urgency_level'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Confidence</th>
            <td>
                <?= $incident['confidence'] !== null 
                    ? htmlspecialchars(number_format((float)$incident['confidence'], 2)) 
                    : 'N/A' ?>
            </td>
        </tr>
        <tr>
            <th>Manual Override</th>
            <td><?= ((int)($incident['manual_override'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
        </tr>
        <tr>
            <th>Language</th>
            <td><?= htmlspecialchars((string)($incident['lang'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Input Text</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['input_text'] ?? ''))) ?></td>
        </tr>
        <tr>
            <th>Notes</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['notes'] ?? ''))) ?></td>
        </tr>
        <tr>
            <th>Occurred At</th>
            <td><?= htmlspecialchars((string)($incident['occurred_at'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Synced At</th>
            <td><?= htmlspecialchars((string)($incident['synced_at'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Location</th>
            <td>
                <?php if (!empty($incident['lat']) && !empty($incident['lng'])): ?>
                    <a target="_blank" href="https://www.google.com/maps?q=<?= htmlspecialchars((string)$incident['lat']) ?>,<?= htmlspecialchars((string)$incident['lng']) ?>">
                        Open in Google Maps
                    </a>
                    <br>
                    Lat: <?= htmlspecialchars((string)$incident['lat']) ?>,
                    Lng: <?= htmlspecialchars((string)$incident['lng']) ?>
                <?php else: ?>
                    No location
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<div class="card">
    <h3>Patient Profile</h3>
    <table>
        <tr>
            <th>Full Name</th>
            <td><?= htmlspecialchars((string)($incident['full_name'] ?? 'Unknown')) ?></td>
        </tr>
        <tr>
            <th>Age</th>
            <td><?= htmlspecialchars((string)($incident['age'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Sex</th>
            <td><?= htmlspecialchars((string)($incident['sex'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Blood Type</th>
            <td><?= htmlspecialchars((string)($incident['blood_type'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Allergies</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['allergies'] ?? ''))) ?></td>
        </tr>
        <tr>
            <th>Conditions</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['conditions'] ?? ''))) ?></td>
        </tr>
        <tr>
            <th>Medications</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['medications'] ?? ''))) ?></td>
        </tr>
        <tr>
            <th>Patient Notes</th>
            <td><?= nl2br(htmlspecialchars((string)($incident['patient_notes'] ?? ''))) ?></td>
        </tr>
    </table>
</div>

<div class="card">
    <h3>Emergency Contacts</h3>

    <?php if (count($contacts) > 0): ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Relation</th>
            </tr>

            <?php foreach ($contacts as $contact): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$contact['contact_name']) ?></td>
                    <td><?= htmlspecialchars((string)$contact['phone']) ?></td>
                    <td><?= htmlspecialchars((string)$contact['relation']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No emergency contacts found.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Uploaded Images</h3>

    <?php if (count($images) > 0): ?>
        <?php foreach ($images as $image): ?>
            <a href="../<?= htmlspecialchars((string)$image['image_path']) ?>" target="_blank">
                <img src="../<?= htmlspecialchars((string)$image['image_path']) ?>" alt="incident image">
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No images uploaded.</p>
    <?php endif; ?>
</div>

<a href="incidents.php">Back to Incidents</a>

</body>
</html>