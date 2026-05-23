<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';

require_perm('users.view');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

function app_user_has_column(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM app_users LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

$settingColumns = [
    'language',
    'country_code',
    'emergency_number',
    'ambulance_number',
    'fire_number',
];

$missingSettingColumns = [];
foreach ($settingColumns as $column) {
    if (!app_user_has_column($pdo, $column)) {
        $missingSettingColumns[] = $column;
    }
}
$hasUserSettingsColumns = count($missingSettingColumns) === 0;
$hasEmailColumn = app_user_has_column($pdo, 'email');

try {
    if (isset($_POST['update_user'])) {
        require_perm('users.edit');

        $id = (int)($_POST['id'] ?? 0);
        $deviceId = trim($_POST['device_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $age = trim($_POST['age'] ?? '');

        if ($id <= 0 || $deviceId === '') {
            $error = "Valid user ID and Device ID are required.";
        } else {
            $setParts = [
                'device_id = :device_id',
                'full_name = :full_name',
                'age = :age',
                'sex = :sex',
                'blood_type = :blood_type',
                'allergies = :allergies',
                'conditions = :conditions',
                'medications = :medications',
                'notes = :notes',
            ];

            $params = [
                ':id' => $id,
                ':device_id' => $deviceId,
                ':full_name' => $fullName === '' ? null : $fullName,
                ':age' => $age === '' ? null : (int)$age,
                ':sex' => trim($_POST['sex'] ?? ''),
                ':blood_type' => trim($_POST['blood_type'] ?? ''),
                ':allergies' => trim($_POST['allergies'] ?? ''),
                ':conditions' => trim($_POST['conditions'] ?? ''),
                ':medications' => trim($_POST['medications'] ?? ''),
                ':notes' => trim($_POST['notes'] ?? ''),
            ];

            if ($hasEmailColumn) {
                $setParts[] = 'email = :email';
                $params[':email'] = trim($_POST['email'] ?? '') !== '' ? trim($_POST['email']) : null;
            }

            if ($hasUserSettingsColumns) {
                $setParts[] = 'language = :language';
                $setParts[] = 'country_code = :country_code';
                $setParts[] = 'emergency_number = :emergency_number';
                $setParts[] = 'ambulance_number = :ambulance_number';
                $setParts[] = 'fire_number = :fire_number';

                $params[':language'] = trim($_POST['language'] ?? 'en');
                $params[':country_code'] = trim($_POST['country_code'] ?? '+962');
                $params[':emergency_number'] = trim($_POST['emergency_number'] ?? '911');
                $params[':ambulance_number'] = trim($_POST['ambulance_number'] ?? '193');
                $params[':fire_number'] = trim($_POST['fire_number'] ?? '199');
            }

            $sql = "UPDATE app_users SET " . implode(",\n                    ", $setParts) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            log_admin_action($pdo, 'edit_app_user', 'app_user', $id, 'Admin edited an app user and user settings');
            $message = "User updated successfully.";
        }
    }

    if (isset($_POST['delete_user'])) {
        require_perm('users.delete');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = "Invalid user ID.";
        } else {
            $userStmt = $pdo->prepare("SELECT device_id FROM app_users WHERE id = ?");
            $userStmt->execute([$id]);
            $deviceId = (string)$userStmt->fetchColumn();

            $contactDelete = $pdo->prepare("DELETE FROM user_emergency_contacts WHERE user_id = ?");
            $contactDelete->execute([$id]);

            if ($deviceId !== '') {
                $detach = $pdo->prepare("UPDATE incidents SET device_id = NULL WHERE device_id = ?");
                $detach->execute([$deviceId]);
            }

            $stmt = $pdo->prepare("DELETE FROM app_users WHERE id = ?");
            $stmt->execute([$id]);

            log_admin_action($pdo, 'delete_app_user', 'app_user', $id, 'Admin deleted an app user');
            $message = "User deleted successfully. Old incidents were kept but detached from this user.";
        }
    }
} catch (PDOException $e) {
    $error = "Database error. The user operation could not be completed.";
}

$stmt = $pdo->prepare(" 
    SELECT 
        app_users.*,
        COUNT(incidents.id) AS incident_count
    FROM app_users
    LEFT JOIN incidents 
        ON app_users.device_id = incidents.device_id
    GROUP BY app_users.id
    ORDER BY app_users.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>App Users</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico?v=10">
    <link rel="apple-touch-icon" href="../assets/favicon.png?v=10">

    <link rel="stylesheet" href="../assets/css/users.css?v=20260523">
    <script src="../assets/js/confirm-actions.js?v=20260520" defer></script>
</head>

<body>
    <div class="page-header">
        <h1>App Users</h1>
        <p>Manage mobile users, medical profile information, and user app settings in one place.</p>
    </div>

    <?php if (!$hasUserSettingsColumns): ?>
        <div class="error">
            Missing DB columns in app_users: <?= htmlspecialchars(implode(', ', $missingSettingColumns)) ?>.
            Run the SQL patch before saving user settings.
        </div>
    <?php endif; ?>

    <?php if ($message !== ""): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="users-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <?php if ($hasEmailColumn): ?><th>Email</th><?php endif; ?>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Country Code</th>
                        <th>Emergency</th>
                        <th>Device ID</th>
                        <th>Incidents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <?php $userId = (int)$user['id']; ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$userId) ?></td>
                                <td><?= htmlspecialchars((string)($user['full_name'] ?? '')) ?></td>
                                <?php if ($hasEmailColumn): ?><td><?= htmlspecialchars((string)($user['email'] ?? '')) ?></td><?php endif; ?>
                                <td><?= htmlspecialchars((string)($user['age'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['sex'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['country_code'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['emergency_number'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['device_id'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)$user['incident_count']) ?></td>
                                <td class="action-buttons">
                                    <a href="user_view.php?id=<?= urlencode((string)$userId) ?>" class="button muted">View</a>
                                    <button type="button" class="button" onclick="parent.openGlobalModal(document.getElementById('editUser<?= $userId ?>').innerHTML)">Edit</button>
                                    <form method="POST" class="inline-form js-confirm-delete">
                                        <input type="hidden" name="id" value="<?= $userId ?>">
                                        <button type="submit" name="delete_user" class="danger-button">Delete</button>
                                    </form>

                                    <div id="editUser<?= $userId ?>" style="display:none;">
                                        <div class="modal-header">
                                            <div>
                                                <h3>Edit User</h3>
                                                <p>Medical profile and app settings are saved inside app_users.</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="pages/users.php" class="js-confirm-save modal-form">
                                            <input type="hidden" name="id" value="<?= $userId ?>">
                                            <div class="form-grid modal-grid">
                                                <div><label>Device ID</label><input type="text" name="device_id" value="<?= htmlspecialchars((string)($user['device_id'] ?? '')) ?>" required></div>
                                                <div><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars((string)($user['full_name'] ?? '')) ?>"></div>
                                                <?php if ($hasEmailColumn): ?><div><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>"></div><?php endif; ?>
                                                <div><label>Age</label><input type="number" name="age" value="<?= htmlspecialchars((string)($user['age'] ?? '')) ?>"></div>
                                                <div><label>Gender</label><input type="text" name="sex" value="<?= htmlspecialchars((string)($user['sex'] ?? '')) ?>"></div>
                                                <div><label>Blood Type</label><input type="text" name="blood_type" value="<?= htmlspecialchars((string)($user['blood_type'] ?? '')) ?>"></div>

                                                <div>
                                                    <label>Language</label>
                                                    <select name="language" <?= $hasUserSettingsColumns ? '' : 'disabled' ?>>
                                                        <option value="en" <?= (($user['language'] ?? 'en') === 'en') ? 'selected' : '' ?>>English</option>
                                                        <option value="ar" <?= (($user['language'] ?? '') === 'ar') ? 'selected' : '' ?>>Arabic</option>
                                                    </select>
                                                </div>
                                                <div><label>Country Code</label><input type="text" name="country_code" value="<?= htmlspecialchars((string)($user['country_code'] ?? '+962')) ?>" <?= $hasUserSettingsColumns ? '' : 'disabled' ?>></div>
                                                <div><label>Emergency Number</label><input type="text" name="emergency_number" value="<?= htmlspecialchars((string)($user['emergency_number'] ?? '911')) ?>" <?= $hasUserSettingsColumns ? '' : 'disabled' ?>></div>
                                                <div><label>Ambulance Number</label><input type="text" name="ambulance_number" value="<?= htmlspecialchars((string)($user['ambulance_number'] ?? '193')) ?>" <?= $hasUserSettingsColumns ? '' : 'disabled' ?>></div>
                                                <div><label>Fire Number</label><input type="text" name="fire_number" value="<?= htmlspecialchars((string)($user['fire_number'] ?? '199')) ?>" <?= $hasUserSettingsColumns ? '' : 'disabled' ?>></div>

                                                <div><label>Allergies</label><textarea name="allergies"><?= htmlspecialchars((string)($user['allergies'] ?? '')) ?></textarea></div>
                                                <div><label>Conditions</label><textarea name="conditions"><?= htmlspecialchars((string)($user['conditions'] ?? '')) ?></textarea></div>
                                                <div><label>Medications</label><textarea name="medications"><?= htmlspecialchars((string)($user['medications'] ?? '')) ?></textarea></div>
                                                <div class="wide-field"><label>Notes</label><textarea name="notes"><?= htmlspecialchars((string)($user['notes'] ?? '')) ?></textarea></div>
                                            </div>
                                            <div class="modal-actions">
                                                <button type="submit" name="update_user" class="button">Save Edit</button>
                                                <button type="button" class="button muted" onclick="parent.closeGlobalModal()">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $hasEmailColumn ? 10 : 9 ?>" class="empty">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>