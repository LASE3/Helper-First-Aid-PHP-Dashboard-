<?php 
# added new API endpoint to sync user profile from the app 10/5/2026
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }
$data = json_decode(file_get_contents('php://input'), true);

 if(!is_array($data)){
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload'
    ]);
    exit;
}
$deviceId =trim((string)($data['device_id'] ?? ''));

if($deviceId === ''){
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'device_id is required'
    ]);
    exit;
}
$fullName = trim((string)($data['full_name'] ?? ''));
// $email = trim((string)($data['email'] ?? ''));
$sex = trim((string)($data['sex'] ?? ''));
$bloodType = trim((string)($data['blood_type'] ?? ''));
$allergies = trim((string)($data['allergies'] ?? ''));
$age = (int)($data['age'] ?? 0);
$conditions = trim((string)($data['conditions'] ?? ''));
$medications = trim((string)($data['medications'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$contacts = $data['contacts'] ?? [];

if(!is_array($contacts)){
    $contacts = [];
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("
    INSERT INTO app_users 
        (device_id, full_name, sex, age, blood_type, allergies, conditions, medications, notes)
    VALUES 
        (:device_id, :full_name, :sex, :age, :blood_type, :allergies, :conditions, :medications, :notes)
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        age = VALUES(age),
        sex = VALUES(sex),
        blood_type = VALUES(blood_type),
        allergies = VALUES(allergies),
        conditions = VALUES(conditions),
        medications = VALUES(medications),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
");    
$stmt->execute([
    ':device_id' => $deviceId,
    ':full_name' => $fullName,
    ':sex' => $sex,
    ':age' => $age,
    ':blood_type' => $bloodType,
    ':allergies' => $allergies,
    ':conditions' => $conditions,
    ':medications' => $medications,
    ':notes' => $notes
]);

$getUserId = $pdo->prepare("SELECT id FROM app_users WHERE device_id = :device_id LIMIT 1");
$getUserId->execute([
    ':device_id' => $deviceId
]);
$user = $getUserId->fetch();

if(!$user){
 throw new RuntimeException("Failed to load user after sync.");
}

$userId = (int)$user['id'];

$deleteContacts = $pdo->prepare("DELETE FROM user_emergency_contacts WHERE user_id = :user_id");
$deleteContacts->execute([
    ':user_id' => $userId
]);
$insertContact = $pdo->prepare("
    INSERT INTO user_emergency_contacts (user_id, contact_name, phone, relation)
    VALUES (:user_id, :contact_name, :phone, :relation)
");
foreach ($contacts as $contact) {
    if (!is_array($contact)) {
        continue;
    }
        $contactName = trim((string)($contact['contact_name'] ?? ''));
        $phone = trim((string)($contact['phone'] ?? ''));
        $relation = trim((string)($contact['relation'] ?? ''));

        if($contactName === '' && $phone === ''){
            continue;
        }

        $insertContact->execute([
            ':user_id' => $userId,
            ':contact_name' => $contactName,
            ':phone' => $phone,
            ':relation' => $relation
        ]);
}
$pdo->commit();

echo json_encode([
    'success' => true,
    'message' => 'Profile synced successfully',
    'data' => [
        'user_id' => $userId,
        'device_id' => $deviceId
    ]
]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
?>