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

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function val(array $row, string $key, string $default = '—'): string
{
    $value = $row[$key] ?? '';
    $value = is_scalar($value) ? trim((string)$value) : '';
    return $value !== '' ? $value : $default;
}

$incidentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incidentId <= 0) {
    http_response_code(400);
    die("Invalid incident ID.");
}

$stmt = $pdo->prepare("
    SELECT 
        incidents.*,
        categories.name_en AS category_name,
        categories.name_ar AS category_name_ar,
        app_users.id AS user_id,
        app_users.full_name,
        app_users.email,
        app_users.profile_image_path,
        app_users.sex,
        app_users.age,
        app_users.blood_type,
        app_users.allergies,
        app_users.conditions,
        app_users.medications,
        app_users.notes AS patient_notes,
        app_users.language AS user_language,
        app_users.country_code,
        app_users.emergency_number,
        app_users.ambulance_number,
        app_users.fire_number
    FROM incidents
    LEFT JOIN categories 
        ON incidents.category_code = categories.CODE
    LEFT JOIN app_users 
        ON incidents.device_id = app_users.device_id
    WHERE incidents.id = :id
    LIMIT 1
");

$stmt->execute([':id' => $incidentId]);
$incident = $stmt->fetch();

if (!$incident) {
    http_response_code(404);
    die("Incident not found.");
}

$imgStmt = $pdo->prepare("
    SELECT *
    FROM incident_images
    WHERE incident_id = :incident_id
    ORDER BY uploaded_at ASC
");
$imgStmt->execute([':incident_id' => $incidentId]);
$images = $imgStmt->fetchAll();

$contacts = [];
if (!empty($incident['user_id'])) {
    $contactStmt = $pdo->prepare("
        SELECT *
        FROM user_emergency_contacts
        WHERE user_id = :user_id
        ORDER BY id ASC
    ");
    $contactStmt->execute([':user_id' => (int)$incident['user_id']]);
    $contacts = $contactStmt->fetchAll();
}

$category = $incident['category_name'] ?? $incident['category_code'] ?? 'Unknown';
$urgency = strtolower((string)($incident['urgency_level'] ?? $incident['urgency'] ?? 'medium'));
$confidence = $incident['confidence'] !== null ? number_format(((float)$incident['confidence']) * 100, 0) . '%' : 'N/A';
$hasLocation = !empty($incident['lat']) && !empty($incident['lng']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Incident Details</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="stylesheet" href="../assets/css/incident_view.css?v=20260524">
</head>

<body>
    <div class="page-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">Incident Details</p>
                <h1><?= e($category) ?></h1>
                <p class="muted">Full incident information, patient profile, emergency contacts, location, and uploaded images.</p>
            </div>
            <div class="header-actions">
                <a href="export_incident_csv.php?id=<?= (int)$incident['id'] ?>"
                    class="btn btn-primary">
                    Export Full CSV
                </a>
                <a class="btn secondary" href="incidents.php">Back to Incidents</a>
                <?php if (!empty($incident['user_id']) && can('users.view')): ?>
                    <a class="btn" href="user_view.php?id=<?= urlencode((string)$incident['user_id']) ?>">View User</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><span>Urgency</span><strong class="urgency <?= e($urgency) ?>"><?= e(strtoupper($urgency)) ?></strong></div>
            <div class="stat-card"><span>Confidence</span><strong><?= e($confidence) ?></strong></div>
            <div class="stat-card"><span>Manual Override</span><strong><?= ((int)($incident['manual_override'] ?? 0) === 1) ? 'Yes' : 'No' ?></strong></div>
            <div class="stat-card"><span>Synced At</span><strong><?= e(val($incident, 'synced_at')) ?></strong></div>
        </div>

        <section class="card">
            <h2>Incident Information</h2>
            <div class="details-grid">
                <div class="detail-item"><span>ID</span><strong>#<?= e($incident['id']) ?></strong></div>
                <div class="detail-item"><span>Device ID</span><strong><?= e(val($incident, 'device_id')) ?></strong></div>
                <div class="detail-item"><span>Category</span><strong><?= e($category) ?></strong></div>
                <div class="detail-item"><span>Language</span><strong><?= e(val($incident, 'lang')) ?></strong></div>
                <div class="detail-item"><span>Occurred At</span><strong><?= e(val($incident, 'occurred_at')) ?></strong></div>
                <div class="detail-item"><span>Location Source</span><strong><?= e(val($incident, 'location_source')) ?></strong></div>
                <div class="detail-item wide"><span>Input Text</span><strong><?= nl2br(e(val($incident, 'input_text'))) ?></strong></div>
                <div class="detail-item wide"><span>Notes</span><strong><?= nl2br(e(val($incident, 'notes'))) ?></strong></div>
                <div class="detail-item wide">
                    <span>Location</span>
                    <strong>
                        <?php if ($hasLocation): ?>
                            <a target="_blank" href="https://www.google.com/maps?q=<?= e($incident['lat']) ?>,<?= e($incident['lng']) ?>">Open in Google Maps</a>
                            <br>Lat: <?= e($incident['lat']) ?>, Lng: <?= e($incident['lng']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </strong>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-title-row">
                <h2>Patient Profile</h2>
                <?php if (!empty($incident['user_id']) && can('users.view')): ?>
                    <a class="btn small" href="user_view.php?id=<?= urlencode((string)$incident['user_id']) ?>">View Full User Profile</a>
                <?php endif; ?>
            </div>
            <div class="details-grid">
                <div class="detail-item"><span>Full Name</span><strong><?= e(val($incident, 'full_name')) ?></strong></div>
                <div class="detail-item"><span>Email</span><strong><?= e(val($incident, 'email')) ?></strong></div>
                <div class="detail-item"><span>Age</span><strong><?= e(val($incident, 'age')) ?></strong></div>
                <div class="detail-item"><span>Sex</span><strong><?= e(val($incident, 'sex')) ?></strong></div>
                <div class="detail-item"><span>Blood Type</span><strong><?= e(val($incident, 'blood_type')) ?></strong></div>
                <div class="detail-item"><span>Country Code</span><strong><?= e(val($incident, 'country_code')) ?></strong></div>
                <div class="detail-item wide"><span>Allergies</span><strong><?= nl2br(e(val($incident, 'allergies'))) ?></strong></div>
                <div class="detail-item wide"><span>Conditions</span><strong><?= nl2br(e(val($incident, 'conditions'))) ?></strong></div>
                <div class="detail-item wide"><span>Medications</span><strong><?= nl2br(e(val($incident, 'medications'))) ?></strong></div>
                <div class="detail-item wide"><span>Patient Notes</span><strong><?= nl2br(e(val($incident, 'patient_notes'))) ?></strong></div>
            </div>
        </section>

        <section class="card">
            <div class="card-title-row">
                <h2>Emergency Contacts</h2>
                <span class="pill"><?= count($contacts) ?> contact<?= count($contacts) === 1 ? '' : 's' ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Relation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($contacts) > 0): ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?= e($contact['contact_name'] ?? '') ?></td>
                                    <td><?= e($contact['phone'] ?? '') ?></td>
                                    <td><?= e($contact['relation'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty">No emergency contacts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-title-row">
                <h2>Uploaded Images</h2>
                <span class="pill"><?= count($images) ?> image<?= count($images) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (count($images) > 0): ?>
                <div class="image-grid">
                    <?php foreach ($images as $image): ?>
                        <a href="../<?= e($image['image_path']) ?>" target="_blank">
                            <img src="../<?= e($image['image_path']) ?>" alt="Incident image">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty">No images uploaded.</p>
            <?php endif; ?>
        </section>
    </div>
</body>

</html>