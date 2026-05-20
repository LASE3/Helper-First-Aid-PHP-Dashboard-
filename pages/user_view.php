<?php
# added new page to view user details and incidents related to the user 10/5/2026
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

$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    die("Invalid user ID.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM app_users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$contactsStmt = $pdo->prepare("
    SELECT contact_name, phone, relation
    FROM user_emergency_contacts
    WHERE user_id = :user_id
    ORDER BY id ASC
");
$contactsStmt->execute([':user_id' => $userId]);
$contacts = $contactsStmt->fetchAll();

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .box {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fafafa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f4f4f4;
        }

        .label {
            font-weight: bold;
        }
    </style>
</head>

<body>

    <h2>User Details</h2>

    <div class="box">
        <h3>Profile Information</h3>

        <p><span class="label">Name:</span> <?= htmlspecialchars((string)($user['full_name'] ?? '')) ?></p>
        <p><span class="label">Age:</span> <?= htmlspecialchars((string)($user['age'] ?? '')) ?></p>
        <p><span class="label">Sex:</span> <?= htmlspecialchars((string)($user['sex'] ?? '')) ?></p>
        <p><span class="label">Device ID:</span> <?= htmlspecialchars((string)$user['device_id']) ?></p>
        <p><span class="label">Conditions:</span> <?= nl2br(htmlspecialchars((string)($user['conditions'] ?? ''))) ?></p>
        <p><span class="label">Medications:</span> <?= nl2br(htmlspecialchars((string)($user['medications'] ?? ''))) ?></p>
        <p><span class="label">Notes:</span> <?= nl2br(htmlspecialchars((string)($user['notes'] ?? ''))) ?></p>
        <p><span class="label">Created At:</span> <?= htmlspecialchars((string)$user['created_at']) ?></p>
        <p><span class="label">Updated At:</span> <?= htmlspecialchars((string)$user['updated_at']) ?></p>
    </div>

    <div class="box">
        <h3>Emergency Contacts</h3>

        <table>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Relation</th>
            </tr>

            <?php if (count($contacts) > 0): ?>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($contact['contact_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($contact['phone'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($contact['relation'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No emergency contacts found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="box">
        <h3>User Incidents</h3>

        <table>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Urgency</th>
                <th>Description</th>
                <th>Location</th>
                <th>Occurred At</th>
            </tr>

            <?php if (count($incidents) > 0): ?>
                <?php foreach ($incidents as $incident): ?>
                    <?php
                    $location = '';

                    if (!empty($incident['location'])) {
                        $location = (string)$incident['location'];
                    } elseif (!empty($incident['lat']) && !empty($incident['lng'])) {
                        $location = $incident['lat'] . ', ' . $incident['lng'];
                    }
                    ?>

                    <tr>
                        <td><?= htmlspecialchars((string)$incident['id']) ?></td>
                        <td><?= htmlspecialchars((string)($incident['category_name'] ?? $incident['category_code'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($incident['urgency'] ?? $incident['urgency_level'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($incident['description'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($location) ?></td>
                        <td><?= htmlspecialchars((string)($incident['occurred_at'] ?? $incident['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No incidents found for this user.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <a href="users.php">Back to Users</a>
    <br>
    <a href="dashboard.php">Back to Dashboard</a>

</body>

</html>