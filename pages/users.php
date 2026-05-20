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

try {
    if (isset($_POST['update_user'])) {
        // If you do not add users.edit permission yet, admin can still edit when users.view exists.
        if (function_exists('can') && !can('users.edit') && !can('users.view')) {
            require_perm('users.edit');
        }

        $id = (int)($_POST['id'] ?? 0);
        $deviceId = trim($_POST['device_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $age = trim($_POST['age'] ?? '');

        if ($id <= 0 || $deviceId === '') {
            $error = "Valid user ID and Device ID are required.";
        } else {
            $stmt = $pdo->prepare(" 
                UPDATE app_users
                SET device_id = :device_id,
                    full_name = :full_name,
                    age = :age,
                    sex = :sex,
                    blood_type = :blood_type,
                    allergies = :allergies,
                    conditions = :conditions,
                    medications = :medications,
                    notes = :notes
                WHERE id = :id
            ");

            $stmt->execute([
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
            ]);

            log_admin_action($pdo, 'edit_app_user', 'app_user', $id, 'Admin edited an app user');
            $message = "User updated successfully.";
        }
    }

    if (isset($_POST['delete_user'])) {
        if (function_exists('can') && !can('users.delete') && !can('users.view')) {
            require_perm('users.delete');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = "Invalid user ID.";
        } else {
            $userStmt = $pdo->prepare("SELECT device_id FROM app_users WHERE id = ?");
            $userStmt->execute([$id]);
            $deviceId = (string)$userStmt->fetchColumn();

            $contactDelete = $pdo->prepare("DELETE FROM user_emergency_contacts WHERE user_id = ?");
            $contactDelete->execute([$id]);

            // Keep incidents for reporting, but detach patient link instead of deleting incident history.
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
    <link rel="stylesheet" href="../assets/css/users.css?v=20260520b">
    <script src="../assets/js/confirm-actions.js?v=20260520" defer></script>
</head>

<body>
    <div class="page-header">
        <h1>App Users</h1>
        <p>Manage mobile application users and their medical profile information.</p>
    </div>

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
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Blood</th>
                        <th>Device ID</th>
                        <th>Incidents</th>
                        <th>Last Updated</th>
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
                                <td><?= htmlspecialchars((string)($user['age'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['sex'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['blood_type'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($user['device_id'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)$user['incident_count']) ?></td>
                                <td><?= htmlspecialchars((string)($user['updated_at'] ?? '')) ?></td>
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
                                                <p>Update the mobile user medical profile.</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="pages/users.php" class="js-confirm-save modal-form">
                                            <input type="hidden" name="id" value="<?= $userId ?>">
                                            <div class="form-grid modal-grid">
                                                <div><label>Device ID</label><input type="text" name="device_id" value="<?= htmlspecialchars((string)($user['device_id'] ?? '')) ?>" required></div>
                                                <div><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars((string)($user['full_name'] ?? '')) ?>"></div>
                                                <div><label>Age</label><input type="number" name="age" value="<?= htmlspecialchars((string)($user['age'] ?? '')) ?>"></div>
                                                <div><label>Gender</label><input type="text" name="sex" value="<?= htmlspecialchars((string)($user['sex'] ?? '')) ?>"></div>
                                                <div><label>Blood Type</label><input type="text" name="blood_type" value="<?= htmlspecialchars((string)($user['blood_type'] ?? '')) ?>"></div>
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
                            <td colspan="9" class="empty">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>