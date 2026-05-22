<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond_json(405, ['success' => false, 'message' => 'Method not allowed']);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respond_json(400, ['success' => false, 'message' => 'Invalid JSON payload']);
    }

    $deviceId = trim((string)($data['device_id'] ?? ''));
    if ($deviceId === '') {
        respond_json(400, ['success' => false, 'message' => 'device_id is required']);
    }

    $contacts = $data['contacts'] ?? [];
    if (!is_array($contacts)) {
        $contacts = [];
    }

    $email = trim((string)($data['email'] ?? ''));
    $fullName = trim((string)($data['full_name'] ?? ''));
    $age = ($data['age'] ?? '') === '' ? null : (int)$data['age'];
    $sex = trim((string)($data['sex'] ?? ''));
    $bloodType = trim((string)($data['blood_type'] ?? ''));
    $allergies = trim((string)($data['allergies'] ?? ''));
    $conditions = trim((string)($data['conditions'] ?? ''));
    $medications = trim((string)($data['medications'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));

    $language = trim((string)($data['language'] ?? 'en'));
    $countryCode = trim((string)($data['country_code'] ?? '+962'));
    $emergencyNumber = trim((string)($data['emergency_number'] ?? '911'));
    $ambulanceNumber = trim((string)($data['ambulance_number'] ?? '193'));
    $fireNumber = trim((string)($data['fire_number'] ?? '199'));

    $hasEmailColumn = false;
    $emailCheck = $pdo->prepare("SHOW COLUMNS FROM app_users LIKE 'email'");
    $emailCheck->execute();
    $hasEmailColumn = (bool)$emailCheck->fetch();

    $pdo->beginTransaction();

    if ($hasEmailColumn) {
        $stmt = $pdo->prepare("
            INSERT INTO app_users
                (device_id, email, full_name, age, sex, blood_type, allergies, conditions, medications, notes,
                 language, country_code, emergency_number, ambulance_number, fire_number)
            VALUES
                (:device_id, :email, :full_name, :age, :sex, :blood_type, :allergies, :conditions, :medications, :notes,
                 :language, :country_code, :emergency_number, :ambulance_number, :fire_number)
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                full_name = VALUES(full_name),
                age = VALUES(age),
                sex = VALUES(sex),
                blood_type = VALUES(blood_type),
                allergies = VALUES(allergies),
                conditions = VALUES(conditions),
                medications = VALUES(medications),
                notes = VALUES(notes),
                language = VALUES(language),
                country_code = VALUES(country_code),
                emergency_number = VALUES(emergency_number),
                ambulance_number = VALUES(ambulance_number),
                fire_number = VALUES(fire_number),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':device_id' => $deviceId,
            ':email' => $email !== '' ? $email : null,
            ':full_name' => $fullName !== '' ? $fullName : null,
            ':age' => $age,
            ':sex' => $sex !== '' ? $sex : null,
            ':blood_type' => $bloodType !== '' ? $bloodType : null,
            ':allergies' => $allergies !== '' ? $allergies : null,
            ':conditions' => $conditions !== '' ? $conditions : null,
            ':medications' => $medications !== '' ? $medications : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':language' => $language !== '' ? $language : 'en',
            ':country_code' => $countryCode !== '' ? $countryCode : '+962',
            ':emergency_number' => $emergencyNumber !== '' ? $emergencyNumber : '911',
            ':ambulance_number' => $ambulanceNumber !== '' ? $ambulanceNumber : '193',
            ':fire_number' => $fireNumber !== '' ? $fireNumber : '199',
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app_users
                (device_id, full_name, age, sex, blood_type, allergies, conditions, medications, notes,
                 language, country_code, emergency_number, ambulance_number, fire_number)
            VALUES
                (:device_id, :full_name, :age, :sex, :blood_type, :allergies, :conditions, :medications, :notes,
                 :language, :country_code, :emergency_number, :ambulance_number, :fire_number)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                age = VALUES(age),
                sex = VALUES(sex),
                blood_type = VALUES(blood_type),
                allergies = VALUES(allergies),
                conditions = VALUES(conditions),
                medications = VALUES(medications),
                notes = VALUES(notes),
                language = VALUES(language),
                country_code = VALUES(country_code),
                emergency_number = VALUES(emergency_number),
                ambulance_number = VALUES(ambulance_number),
                fire_number = VALUES(fire_number),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':device_id' => $deviceId,
            ':full_name' => $fullName !== '' ? $fullName : null,
            ':age' => $age,
            ':sex' => $sex !== '' ? $sex : null,
            ':blood_type' => $bloodType !== '' ? $bloodType : null,
            ':allergies' => $allergies !== '' ? $allergies : null,
            ':conditions' => $conditions !== '' ? $conditions : null,
            ':medications' => $medications !== '' ? $medications : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':language' => $language !== '' ? $language : 'en',
            ':country_code' => $countryCode !== '' ? $countryCode : '+962',
            ':emergency_number' => $emergencyNumber !== '' ? $emergencyNumber : '911',
            ':ambulance_number' => $ambulanceNumber !== '' ? $ambulanceNumber : '193',
            ':fire_number' => $fireNumber !== '' ? $fireNumber : '199',
        ]);
    }

    $getUserId = $pdo->prepare("SELECT id FROM app_users WHERE device_id = :device_id LIMIT 1");
    $getUserId->execute([':device_id' => $deviceId]);
    $user = $getUserId->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('Failed to load user after sync.');
    }

    $userId = (int)$user['id'];

    $deleteContacts = $pdo->prepare("DELETE FROM user_emergency_contacts WHERE user_id = :user_id");
    $deleteContacts->execute([':user_id' => $userId]);

    $insertContact = $pdo->prepare("
        INSERT INTO user_emergency_contacts (user_id, contact_name, phone, relation)
        VALUES (:user_id, :contact_name, :phone, :relation)
    ");

    foreach ($contacts as $contact) {
        if (!is_array($contact)) {
            continue;
        }

        $contactName = trim((string)($contact['contact_name'] ?? $contact['name'] ?? ''));
        $phone = trim((string)($contact['phone'] ?? ''));
        $relation = trim((string)($contact['relation'] ?? ''));

        if ($contactName === '' && $phone === '') {
            continue;
        }

        $insertContact->execute([
            ':user_id' => $userId,
            ':contact_name' => $contactName !== '' ? $contactName : null,
            ':phone' => $phone !== '' ? $phone : null,
            ':relation' => $relation !== '' ? $relation : null,
        ]);
    }

    $pdo->commit();

    respond_json(200, [
        'success' => true,
        'message' => 'Profile synced successfully',
        'data' => [
            'user_id' => $userId,
            'device_id' => $deviceId,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond_json(500, [
        'success' => false,
        'message' => 'Server error',
    ]);
}
