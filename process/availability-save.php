<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

require_login();
require_permission('bookings.update');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/scheduling/calendar.php');
}

$formType = (string) ($_POST['form_type'] ?? 'availability_profile');

if ($formType === 'slot_settings') {
    $payload = [
        'day_start' => trim((string) ($_POST['day_start'] ?? '')),
        'day_end' => trim((string) ($_POST['day_end'] ?? '')),
        'slot_interval' => trim((string) ($_POST['slot_interval'] ?? '')),
        'default_duration' => trim((string) ($_POST['default_duration'] ?? '')),
        'buffer_minutes' => trim((string) ($_POST['buffer_minutes'] ?? '')),
        'same_day_lead_minutes' => trim((string) ($_POST['same_day_lead_minutes'] ?? '')),
        'max_parallel_rooms' => trim((string) ($_POST['max_parallel_rooms'] ?? '')),
    ];

    $errors = Scheduling::validateSlotSettings($payload);

    if ($errors !== []) {
        flash_set('scheduling_errors', $errors);
        remember_old_input($payload);
        redirect_to('/scheduling/slots.php');
    }

    Scheduling::saveSlotSettings($payload);
    clear_old_input();
    flash_set('scheduling_success', 'Time slot settings updated successfully.');
    redirect_to('/scheduling/slots.php');
}

$payload = [
    'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
    'mode' => trim((string) ($_POST['mode'] ?? 'available')),
    'weekdays' => array_map('strval', (array) ($_POST['weekdays'] ?? [])),
    'start_time' => trim((string) ($_POST['start_time'] ?? '')),
    'end_time' => trim((string) ($_POST['end_time'] ?? '')),
];

$errors = Scheduling::validateAvailabilityPayload($payload);

if ($errors !== []) {
    flash_set('scheduling_errors', $errors);
    remember_old_input($payload);
    redirect_to('/scheduling/availability.php?staff_id=' . urlencode($payload['staff_id']));
}

Scheduling::saveAvailability($payload);
clear_old_input();
flash_set('scheduling_success', 'Availability profile updated successfully.');

redirect_to('/scheduling/availability.php?staff_id=' . urlencode($payload['staff_id']));
