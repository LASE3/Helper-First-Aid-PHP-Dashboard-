<?php
declare(strict_types=1);
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $data): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body'
    ]);
    exit;
}

if (!isset($data['incidents']) || !is_array($data['incidents'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'incidents array is required'
    ]);
    exit;
}

$incidents = $data['incidents'];

if (count($incidents) === 0) {
    respond(400, [
        'success' => false,
        'message' => 'incidents array cannot be empty.'
    ]);
}

$allowedUrgency = ['low', 'medium', 'high', 'extreme'];
$results = [];
$hasErrors = false;

$sql = "
    INSERT INTO incidents (
        local_id,
        device_id,
        occurred_at,
        synced_at,
        lang,
        input_text,
        category_code,
        urgency_level,
        confidence,
        manual_override,
        lat,
        lng,
        location_source,
        notes
    ) VALUES (
        :local_id,
        :device_id,
        :occurred_at,
        NOW(),
        :lang,
        :input_text,
        :category_code,
        :urgency_level,
        :confidence,
        :manual_override,
        :lat,
        :lng,
        :location_source,
        :notes
    )
       ON DUPLICATE KEY UPDATE
          id = LAST_INSERT_ID(id),
          synced_at = NOW(),
          input_text = VALUES(input_text),
          category_code = VALUES(category_code),
          urgency_level = VALUES(urgency_level),
          confidence = VALUES(confidence),
          manual_override = VALUES(manual_override),
          lat = VALUES(lat),
          lng = VALUES(lng),
          location_source = VALUES(location_source),
          notes = VALUES(notes)
";

$stmt = $pdo->prepare($sql);

foreach ($incidents as $index => $incident) {
    $errors = [];

    $localId = $incident['local_id'] ?? null;
    $deviceId = trim((string)($incident['device_id'] ?? ''));
    $occurredAt = trim((string)($incident['occurred_at'] ?? ''));
    $lang = trim((string)($incident['lang'] ?? ''));
    $inputText = trim((string)($incident['input_text'] ?? ''));
    $categoryCode = trim((string)($incident['category_code'] ?? ''));
    $urgencyLevel = trim((string)($incident['urgency_level'] ?? ''));
    $confidence = $incident['confidence'] ?? null;
    $manualOverride = $incident['manual_override'] ?? 0;
    $lat = $incident['lat'] ?? null;
    $lng = $incident['lng'] ?? null;
    $locationSource = trim((string)($incident['location_source'] ?? ''));
    $notes = trim((string)($incident['notes'] ?? ''));

    if ($deviceId === '') {
        $errors[] = 'device_id is required';
    }

    if ($localId === null || $localId === '' || !is_numeric($localId)) {
    $errors[] = 'local_id is required and must be numeric';
    }

    if ($occurredAt === '') {
        $errors[] = 'occurred_at is required';
    }

    if ($categoryCode === '') {
        $errors[] = 'category_code is required';
    }

    if ($lang === '') {
        $errors[] = 'lang is required';
    }

    if ($urgencyLevel !== '' && !in_array($urgencyLevel, $allowedUrgency, true)) {
        $errors[] = 'urgency_level must be one of: low, medium, high, extreme';
    }

    if ($confidence !== null && !is_numeric($confidence)) {
        $errors[] = 'confidence must be numeric';
    }

    if ($manualOverride !== 0 && $manualOverride !== 1 && $manualOverride !== '0' && $manualOverride !== '1') {
        $errors[] = 'manual_override must be 0 or 1';
    }

    if ($lat !== null && $lat !== '' && !is_numeric($lat)) {
        $errors[] = 'lat must be numeric';
    }

    if ($lng !== null && $lng !== '' && !is_numeric($lng)) {
        $errors[] = 'lng must be numeric';
    }

    if (!empty($errors)) {
        $hasErrors = true;
        $results[] = [
            'client_index' => $index,
            'local_id' => (int)$localId,
            'status' => 'synced',
            'server_id' => (int)$pdo->lastInsertId()
        ];
        continue;
    }

    try {
        $stmt->execute([
            ':local_id' => (int)$localId,
            ':device_id' => $deviceId,
            ':occurred_at' => $occurredAt,
            ':lang' => $lang,
            ':input_text' => $inputText,
            ':category_code' => $categoryCode,
            ':urgency_level' => $urgencyLevel !== '' ? $urgencyLevel : null,
            ':confidence' => $confidence !== null && $confidence !== '' ? $confidence : null,
            ':manual_override' => (int)$manualOverride,
            ':lat' => $lat !== null && $lat !== '' ? $lat : null,
            ':lng' => $lng !== null && $lng !== '' ? $lng : null,
            ':location_source' => $locationSource !== '' ? $locationSource : null,
            ':notes' => $notes !== '' ? $notes : null      
        ]);

        $results[] = [
            'client_index' => $index,
            'status' => 'inserted',
            'server_id' => (int)$pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        $hasErrors = true;
        $results[] = [
            'client_index' => $index,
            'status' => 'error',
            'errors' => ['Database insert failed']
        ];
    }
}

respond($hasErrors ? 207 : 200, [
    'success' => !$hasErrors,
    'message' => $hasErrors
        ? 'Some incidents were synced, some failed.'
        : 'All incidents synced successfully.',
    'results' => $results
]);

?>