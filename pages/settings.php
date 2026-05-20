<?php

declare(strict_types=1);

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';

require_perm('settings.view');
require_perm('settings.edit');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Ensure settings row exists
|--------------------------------------------------------------------------
*/
$checkStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM settings");
$checkStmt->execute();
$check = $checkStmt->fetch();

if ((int)$check['total'] === 0) {
    $insertStmt = $pdo->prepare("
        INSERT INTO settings 
        (language, emergency_number, ambulance_number, fire_number, country_code)
        VALUES 
        ('en', '911', '911', '911', '+962')
    ");
    $insertStmt->execute();
}

/*
|--------------------------------------------------------------------------
| Update Settings
|--------------------------------------------------------------------------
*/
if (isset($_POST['save'])) {
    require_perm('settings.edit');

    $language = trim($_POST['language'] ?? '');
    $emergencyNumber = trim($_POST['emergency_number'] ?? '');
    $ambulanceNumber = trim($_POST['ambulance_number'] ?? '');
    $fireNumber = trim($_POST['fire_number'] ?? '');
    $countryCode = trim($_POST['country_code'] ?? '');

    if ($language === '' || $emergencyNumber === '' || $ambulanceNumber === '' || $fireNumber === '' || $countryCode === '') {
        $error = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE settings
            SET 
                language = :language,
                emergency_number = :emergency_number,
                ambulance_number = :ambulance_number,
                fire_number = :fire_number,
                country_code = :country_code,
                updated_at = NOW()
            WHERE id = 1
        ");

        $stmt->execute([
            ':language' => $language,
            ':emergency_number' => $emergencyNumber,
            ':ambulance_number' => $ambulanceNumber,
            ':fire_number' => $fireNumber,
            ':country_code' => $countryCode
        ]);

        log_admin_action(
            $pdo,
            'update_settings',
            'settings',
            1,
            'Admin updated application settings'
        );

        $message = "Settings updated successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| Load Settings
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM settings
    WHERE id = 1
");
$stmt->execute();
$settings = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Settings Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        form {
            max-width: 500px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 12px;
        }

        input,
        select,
        button {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            margin-top: 18px;
            cursor: pointer;
        }

        .message {
            color: green;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .box {
            padding: 15px;
            border: 1px solid #ddd;
            background: #f8f8f8;
            max-width: 520px;
        }
    </style>
</head>

<body>

    <h2>Application Settings</h2>

    <?php if ($message !== ""): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="box">
        <form method="POST">
            <label>Default Language</label>
            <select name="language" required>
                <option value="en" <?= (($settings['language'] ?? '') === 'en') ? 'selected' : '' ?>>English</option>
                <option value="ar" <?= (($settings['language'] ?? '') === 'ar') ? 'selected' : '' ?>>Arabic</option>
            </select>

            <label>Emergency Number</label>
            <input
                type="text"
                name="emergency_number"
                value="<?= htmlspecialchars((string)($settings['emergency_number'] ?? '')) ?>"
                required>

            <label>Ambulance Number</label>
            <input
                type="text"
                name="ambulance_number"
                value="<?= htmlspecialchars((string)($settings['ambulance_number'] ?? '')) ?>"
                required>

            <label>Fire Number</label>
            <input
                type="text"
                name="fire_number"
                value="<?= htmlspecialchars((string)($settings['fire_number'] ?? '')) ?>"
                required>

            <label>Country Code</label>
            <input
                type="text"
                name="country_code"
                value="<?= htmlspecialchars((string)($settings['country_code'] ?? '')) ?>"
                required>

            <button type="submit" name="save">Save Settings</button>
        </form>
    </div>

    <br>
    <a href="dashboard.php">Back</a>

</body>

</html>