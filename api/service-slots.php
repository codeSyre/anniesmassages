<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

require_login();
require_permission('bookings.view');

$serviceId = (string) ($_GET['service_id'] ?? '');
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
$staffId = (string) ($_GET['staff_id'] ?? '');

header('Content-Type: application/json; charset=utf-8');

if ($serviceId === '') {
    echo json_encode(['error' => 'service_id is required'], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(
    [
        'service_id' => $serviceId,
        'date' => $date,
        'staff_id' => $staffId,
        'slots' => Scheduling::availableSlots($serviceId, $date, $staffId !== '' ? $staffId : null),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
