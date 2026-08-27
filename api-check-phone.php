<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';

header('Content-Type: application/json');

$phone = $_REQUEST['phone'] ?? '';
$excludeId = isset($_REQUEST['exclude_id']) && is_numeric($_REQUEST['exclude_id']) ? intval($_REQUEST['exclude_id']) : null;

if (empty($phone)) {
    echo json_encode(['exists' => false]);
    exit;
}

$found = findClientByPhone($pdo, $phone, $excludeId);

if ($found) {
    echo json_encode([
        'exists' => true,
        'client' => [
            'id' => $found['id'],
            'name' => $found['name'],
            'phone' => formatPhone($found['phone'])
        ]
    ]);
} else {
    echo json_encode(['exists' => false]);
}
exit;
