<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

require_login();
require_permission('bookings.view');

$staffId = (string) ($_GET['staff_id'] ?? '');
$date = (string) ($_GET['date'] ?? date('Y-m-d'));

header('Content-Type: application/json; charset=utf-8');

if ($staffId === '') {
    echo json_encode(['error' => 'staff_id is required'], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(Scheduling::staffAvailability($staffId, $date), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
