<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/customers/list.php');
}

$formType = (string) ($_POST['form_type'] ?? 'profile');
$customerId = trim((string) ($_POST['id'] ?? ''));

if ($formType === 'notes') {
    require_permission('customers.update');

    if ($customerId === '') {
        redirect_to('/customers/list.php');
    }

    Customer::saveNotes($customerId, [
        'preference' => trim((string) ($_POST['preference'] ?? '')),
        'admin_notes' => trim((string) ($_POST['admin_notes'] ?? '')),
        'tags' => trim((string) ($_POST['tags'] ?? '')),
    ]);

    flash_set('customer_success', 'Customer notes updated successfully.');
    redirect_to('/customers/notes.php?id=' . urlencode($customerId));
}

$permission = $customerId === '' ? 'customers.create' : 'customers.update';
require_permission($permission);

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'source' => trim((string) ($_POST['source'] ?? 'front desk')),
    'location' => trim((string) ($_POST['location'] ?? 'Harare')),
    'preference' => trim((string) ($_POST['preference'] ?? '')),
    'admin_notes' => trim((string) ($_POST['admin_notes'] ?? '')),
    'tags' => trim((string) ($_POST['tags'] ?? '')),
];

$errors = Customer::validate($payload);

if ($errors !== []) {
    flash_set('customer_errors', $errors);
    remember_old_input($payload);

    $redirect = $customerId === '' ? '/customers/create.php' : '/customers/edit.php?id=' . urlencode($customerId);
    redirect_to($redirect);
}

$customer = Customer::save($payload, $customerId !== '' ? $customerId : null);
clear_old_input();
flash_set('customer_success', $customerId === '' ? 'Customer created successfully.' : 'Customer updated successfully.');

redirect_to('/customers/view.php?id=' . urlencode($customer['id']));
