<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payment.php';

require_login();
require_permission('payments.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/payments/ledger.php');
}

$payload = [
    'booking_id' => trim((string) ($_POST['booking_id'] ?? '')),
    'payment_date' => trim((string) ($_POST['payment_date'] ?? date('Y-m-d'))),
    'method' => trim((string) ($_POST['method'] ?? 'cash')),
    'amount' => trim((string) ($_POST['amount'] ?? '')),
    'note' => trim((string) ($_POST['note'] ?? '')),
    'recorded_by' => trim((string) ($_POST['recorded_by'] ?? 'Admin panel')),
];

$errors = Payment::validate($payload);

if ($errors !== []) {
    flash_set('payment_errors', $errors);
    remember_old_input($payload);
    $redirect = '/payments/create.php';

    if ($payload['booking_id'] !== '') {
        $redirect .= '?booking_id=' . urlencode($payload['booking_id']);
    }

    redirect_to($redirect);
}

$payment = Payment::save($payload);

if (!is_array($payment) || trim((string) ($payment['id'] ?? '')) === '') {
    flash_set('payment_errors', ['payment' => 'We could not save this payment to the database.']);
    remember_old_input($payload);
    $redirect = '/payments/create.php';

    if ($payload['booking_id'] !== '') {
        $redirect .= '?booking_id=' . urlencode($payload['booking_id']);
    }

    redirect_to($redirect);
}

clear_old_input();
flash_set('payment_success', 'Payment recorded successfully.');

redirect_to('/payments/view.php?booking_id=' . urlencode($payment['booking_id']));
