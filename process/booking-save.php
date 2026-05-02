<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
$bookingId = trim((string) ($_POST['id'] ?? ''));
$permission = $bookingId === '' ? 'bookings.create' : 'bookings.update';

require_permission($permission);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/bookings/list.php');
}

$payload = [
    'customer_id' => trim((string) ($_POST['customer_id'] ?? '')),
    'service_id' => trim((string) ($_POST['service_id'] ?? '')),
    'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
    'date' => trim((string) ($_POST['date'] ?? '')),
    'start_time' => trim((string) ($_POST['start_time'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? 'pending')),
    'payment_status' => trim((string) ($_POST['payment_status'] ?? 'unpaid')),
    'amount_paid' => trim((string) ($_POST['amount_paid'] ?? '0')),
    'channel' => trim((string) ($_POST['channel'] ?? 'front desk')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

$errors = Booking::validate($payload, $bookingId !== '' ? $bookingId : null);

if ($errors !== []) {
    flash_set('booking_errors', $errors);
    remember_old_input($payload);

    $redirect = $bookingId === '' ? '/bookings/create.php' : '/bookings/edit.php?id=' . urlencode($bookingId);
    redirect_to($redirect);
}

$booking = Booking::save($payload, $bookingId !== '' ? $bookingId : null);

flash_set('booking_success', $bookingId === '' ? 'Booking created successfully.' : 'Booking updated successfully.');
clear_old_input();

redirect_to('/bookings/view.php?id=' . urlencode($booking['id']));
