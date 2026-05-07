<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

require_login();
require_permission('bookings.view');

$events = array_map(static function (array $booking): array {
    return [
        'id' => $booking['id'],
        'reference' => $booking['reference'],
        'date' => $booking['date'],
        'start' => $booking['time'],
        'end' => $booking['end_time'],
        'status' => $booking['status'],
        'customer' => $booking['customer']['name'],
        'service' => $booking['service']['name'],
        'staff' => $booking['staff']['name'],
    ];
}, Booking::all());

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['events' => $events], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
