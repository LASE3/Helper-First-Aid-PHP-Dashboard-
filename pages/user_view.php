<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';

require_perm('users.view');

$userId = (int)($_GET['id'] ?? 0);
$deviceIdParam = trim((string)($_GET['device_id'] ?? $_GET['device'] ?? ''));

if ($userId <= 0 && $deviceIdParam === '') {
    http_response_code(400);
    die("Invalid user ID.");
}

if ($userId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM app_users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT *
        FROM app_users
        WHERE device_id = :device_id
        LIMIT 1
    ");
    $stmt->execute([':device_id' => $deviceIdParam]);
}
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die("User not found.");
}

$userId = (int)$user['id'];

function value_or_dash(array $row, string $key): string
{
    $value = $row[$key] ?? '';
    $value = is_scalar($value) ? trim((string)$value) : '';
    return $value !== '' ? $value : '—';
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$contactsStmt = $pdo->prepare("
    SELECT contact_name, phone, relation
    FROM user_emergency_contacts
    WHERE user_id = :user_id
    ORDER BY id ASC
");
$contactsStmt->execute([':user_id' => $userId]);
$contacts = $contactsStmt->fetchAll();

$incidents = [];

if (can('incidents.view') && !empty($user['device_id'])) {
    $incidentsStmt = $pdo->prepare("
        SELECT 
            incidents.*,
            categories.name_en AS category_name
        FROM incidents
        LEFT JOIN categories 
            ON incidents.category_code = categories.CODE
        WHERE incidents.device_id = :device_id
        ORDER BY incidents.occurred_at DESC, incidents.created_at DESC
    ");
    $incidentsStmt->execute([
        ':device_id' => $user['device_id']
    ]);
    $incidents = $incidentsStmt->fetchAll();
}

$profileFields = [
    'full_name' => 'Full Name',
    'email' => 'Email',
    'phone' => 'Phone',
    'age' => 'Age',
    'sex' => 'Sex',
    'blood_type' => 'Blood Type',
    'allergies' => 'Allergies',
    'conditions' => 'Conditions',
    'medications' => 'Medications',
    'notes' => 'Notes',
];

$settingsFields = [
    'language' => 'Language',
    'country_code' => 'Country Code',
    'emergency_number' => 'Emergency Number',
    'ambulance_number' => 'Ambulance Number',
    'fire_number' => 'Fire Number',
];

$systemFields = [
    'id' => 'User ID',
    'device_id' => 'Device ID',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Details</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <link rel="stylesheet" href="../assets/css/user_view.css">
</head>

<body>
    <div class="page-shell">
        <div class="page-header">
            <div>
                <p class="eyebrow">App User</p>
                <h1><?= e(value_or_dash($user, 'full_name')) ?></h1>
                <p class="muted">Full profile, settings, emergency contacts, and synced incidents for this user.</p>
            </div>
            <div class="header-actions">
                <a class="btn secondary" href="users.php">Back to Users</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <div class="grid two">
            <section class="card">
                <h2>Profile Information</h2>
                <div class="details-grid">
                    <?php foreach ($profileFields as $key => $label): ?>
                        <div class="detail-item <?= in_array($key, ['allergies', 'conditions', 'medications', 'notes'], true) ? 'wide' : '' ?>">
                            <span><?= e($label) ?></span>
                            <strong><?= nl2br(e(value_or_dash($user, $key))) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <h2>User Settings</h2>
                <div class="details-grid">
                    <?php foreach ($settingsFields as $key => $label): ?>
                        <div class="detail-item">
                            <span><?= e($label) ?></span>
                            <strong><?= e(value_or_dash($user, $key)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2 class="section-gap">System Information</h2>
                <div class="details-grid">
                    <?php foreach ($systemFields as $key => $label): ?>
                        <div class="detail-item <?= $key === 'device_id' ? 'wide' : '' ?>">
                            <span><?= e($label) ?></span>
                            <strong><?= e(value_or_dash($user, $key)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

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
                <h2>User Incidents</h2>
                <?php if (can('incidents.view')): ?>
                    <span class="pill"><?= count($incidents) ?> incident<?= count($incidents) === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </div>

            <?php if (!can('incidents.view')): ?>
                <p class="empty">You do not have permission to view incidents.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Urgency</th>
                                <th>Text / Notes</th>
                                <th>Location</th>
                                <th>Occurred At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($incidents) > 0): ?>
                                <?php foreach ($incidents as $incident): ?>
                                    <?php
                                    $location = '—';
                                    if (!empty($incident['location'])) {
                                        $location = (string)$incident['location'];
                                    } elseif (!empty($incident['lat']) && !empty($incident['lng'])) {
                                        $location = $incident['lat'] . ', ' . $incident['lng'];
                                    }
                                    $text = $incident['description'] ?? $incident['input_text'] ?? $incident['notes'] ?? '—';
                                    ?>
                                    <tr>
                                        <td>#<?= e($incident['id'] ?? '') ?></td>
                                        <td><?= e($incident['category_name'] ?? $incident['category_code'] ?? '—') ?></td>
                                        <td><?= e($incident['urgency'] ?? $incident['urgency_level'] ?? '—') ?></td>
                                        <td><?= nl2br(e($text)) ?></td>
                                        <td><?= e($location) ?></td>
                                        <td><?= e($incident['occurred_at'] ?? $incident['created_at'] ?? '—') ?></td>
                                        <td><a class="btn small" href="incident_view.php?id=<?= urlencode((string)$incident['id']) ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty">No incidents found for this user.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>

</html>