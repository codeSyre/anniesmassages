<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/bookings/list.php');
}

$action = trim((string) ($_POST['action'] ?? 'save_booking'));
$bookingId = trim((string) ($_POST['id'] ?? ''));

if ($action === 'cancel_booking') {
    require_permission('bookings.update');

    if ($bookingId === '') {
        flash_set('booking_errors', ['booking' => 'Booking not found.']);
        redirect_to('/bookings/list.php');
    }

    $result = Booking::cancel($bookingId);

    if (!($result['success'] ?? false)) {
        flash_set('booking_errors', ['booking' => (string) ($result['error'] ?? 'Booking could not be cancelled.')]);
        redirect_to('/bookings/view.php?id=' . urlencode($bookingId));
    }

    flash_set('booking_success', (string) ($result['reference'] ?? 'Booking') . ' was cancelled successfully.');
    redirect_to('/bookings/view.php?id=' . urlencode($bookingId));
}

$permission = $bookingId === '' ? 'bookings.create' : 'bookings.update';
require_permission($permission);

$payload = [
    'customer_id' => trim((string) ($_POST['customer_id'] ?? '')),
    'service_id' => trim((string) ($_POST['service_id'] ?? '')),
    'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
    'date' => trim((string) ($_POST['date'] ?? '')),
    'start_time' => trim((string) ($_POST['start_time'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? 'pending')),
    'payment_status' => trim((string) ($_POST['payment_status'] ?? 'unpaid')),
    'amount_paid' => trim((string) ($_POST['amount_paid'] ?? '0')),
    'channel' => trim((string) ($_POST['channel'] ?? '')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

$errors = Booking::validate($payload, $bookingId !== '' ? $bookingId : null);

if ($errors !== []) {
    flash_set('booking_errors', $errors);
    remember_old_input($payload);

    $redirect = $bookingId === '' ? '/bookings/create.php' : '/bookings/edit.php?id=' . urlencode($bookingId);
    redirect_to($redirect);
}

$previousBooking = $bookingId !== '' ? Booking::find($bookingId) : null;
$booking = Booking::save($payload, $bookingId !== '' ? $bookingId : null);

if (!is_array($booking) || trim((string) ($booking['id'] ?? '')) === '') {
    flash_set('booking_errors', ['booking' => 'We could not save this booking to the database.']);
    remember_old_input($payload);

    $redirect = $bookingId === '' ? '/bookings/create.php' : '/bookings/edit.php?id=' . urlencode($bookingId);
    redirect_to($redirect);
}

if ($bookingId === '' && (float) ($payload['amount_paid'] ?? 0) > 0.0) {
    Payment::save([
        'booking_id'  => $booking['id'],
        'payment_date' => $booking['date'],
        'method'      => $payload['channel'] !== '' ? $payload['channel'] : 'cash',
        'amount'      => (float) $payload['amount_paid'],
        'note'        => 'Recorded at booking intake.',
        'recorded_by' => (string) ($currentUser['name'] ?? 'Admin panel'),
    ]);
}

flash_set('booking_success', $bookingId === '' ? 'Booking created successfully.' : 'Booking updated successfully.');
clear_old_input();

redirect_to('/bookings/view.php?id=' . urlencode($booking['id']));
