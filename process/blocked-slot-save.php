<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

require_login();
require_permission('bookings.update');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/scheduling/blocked.php');
}

$payload = [
    'date' => trim((string) ($_POST['date'] ?? '')),
    'start_time' => trim((string) ($_POST['start_time'] ?? '')),
    'end_time' => trim((string) ($_POST['end_time'] ?? '')),
    'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
    'reason' => trim((string) ($_POST['reason'] ?? '')),
];

$errors = Scheduling::validateBlockedSlot($payload);

if ($errors !== []) {
    flash_set('scheduling_errors', $errors);
    remember_old_input($payload);
    redirect_to('/scheduling/blocked.php');
}

Scheduling::saveBlockedSlot($payload);
clear_old_input();
flash_set('scheduling_success', 'Blocked slot added successfully.');

redirect_to('/scheduling/blocked.php');
