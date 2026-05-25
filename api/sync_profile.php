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

    $contacts = $data['contacts']
        ?? $data['emergency_contacts']
        ?? $data['emergencyContacts']
        ?? $data['emergency_contact']
        ?? [];

    if (!is_array($contacts)) {
        $contacts = [];
    }

    $email = trim((string)($data['email'] ?? ''));
    $fullName = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? $data['phone_number'] ?? $data['mobile'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $questionnaireJson = $data['questionnaire_json'] ?? $data['questionnaire'] ?? $data['survey'] ?? null;
    if (is_array($questionnaireJson)) {
        $questionnaireJson = json_encode($questionnaireJson, JSON_UNESCAPED_UNICODE);
    } elseif ($questionnaireJson !== null) {
        $questionnaireJson = trim((string)$questionnaireJson);
    }
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

    $hasPhoneColumn = false;
    $phoneCheck = $pdo->prepare("SHOW COLUMNS FROM app_users LIKE 'phone'");
    $phoneCheck->execute();
    $hasPhoneColumn = (bool)$phoneCheck->fetch();

    $hasPasswordColumn = false;
    $passwordCheck = $pdo->prepare("SHOW COLUMNS FROM app_users LIKE 'password_hash'");
    $passwordCheck->execute();
    $hasPasswordColumn = (bool)$passwordCheck->fetch();

    $hasQuestionnaireColumn = false;
    $questionnaireCheck = $pdo->prepare("SHOW COLUMNS FROM app_users LIKE 'questionnaire_json'");
    $questionnaireCheck->execute();
    $hasQuestionnaireColumn = (bool)$questionnaireCheck->fetch();

    $extraInsertColumns = [];
    $extraInsertValues = [];
    $extraUpdateParts = [];
    $extraParams = [];

    if ($hasPhoneColumn) {
        $extraInsertColumns[] = 'phone';
        $extraInsertValues[] = ':phone';
        $extraUpdateParts[] = 'phone = VALUES(phone)';
        $extraParams[':phone'] = $phone !== '' ? $phone : null;
    }

    if ($hasPasswordColumn && $password !== '') {
        $extraInsertColumns[] = 'password_hash';
        $extraInsertValues[] = ':password_hash';
        $extraUpdateParts[] = 'password_hash = VALUES(password_hash)';
        $extraParams[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($hasQuestionnaireColumn) {
        $extraInsertColumns[] = 'questionnaire_json';
        $extraInsertValues[] = ':questionnaire_json';
        $extraUpdateParts[] = 'questionnaire_json = VALUES(questionnaire_json)';
        $extraParams[':questionnaire_json'] = ($questionnaireJson !== null && $questionnaireJson !== '') ? (string)$questionnaireJson : null;
    }

    $extraColumnsSql = $extraInsertColumns ? ', ' . implode(', ', $extraInsertColumns) : '';
    $extraValuesSql = $extraInsertValues ? ', ' . implode(', ', $extraInsertValues) : '';
    $extraUpdateSql = $extraUpdateParts ? ",\n                " . implode(",\n                ", $extraUpdateParts) : '';

    $pdo->beginTransaction();

    if ($hasEmailColumn) {
        $stmt = $pdo->prepare("
            INSERT INTO app_users
                (device_id, email, full_name, age, sex, blood_type, allergies, conditions, medications, notes,
                 language, country_code, emergency_number, ambulance_number, fire_number{$extraColumnsSql})
            VALUES
                (:device_id, :email, :full_name, :age, :sex, :blood_type, :allergies, :conditions, :medications, :notes,
                 :language, :country_code, :emergency_number, :ambulance_number, :fire_number{$extraValuesSql})
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
                fire_number = VALUES(fire_number){$extraUpdateSql},
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
        ] + $extraParams);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app_users
                (device_id, full_name, age, sex, blood_type, allergies, conditions, medications, notes,
                 language, country_code, emergency_number, ambulance_number, fire_number{$extraColumnsSql})
            VALUES
                (:device_id, :full_name, :age, :sex, :blood_type, :allergies, :conditions, :medications, :notes,
                 :language, :country_code, :emergency_number, :ambulance_number, :fire_number{$extraValuesSql})
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
                fire_number = VALUES(fire_number){$extraUpdateSql},
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
        ] + $extraParams);
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

        $contactName = trim((string)(
            $contact['contact_name']
            ?? $contact['contactName']
            ?? $contact['name']
            ?? $contact['full_name']
            ?? ''
        ));

        $phone = trim((string)(
            $contact['phone']
            ?? $contact['phone_number']
            ?? $contact['phoneNumber']
            ?? $contact['mobile']
            ?? $contact['number']
            ?? ''
        ));

        $relation = trim((string)(
            $contact['relation']
            ?? $contact['relationship']
            ?? $contact['type']
            ?? ''
        ));

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
            'contacts_received' => count($contacts),
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
