<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/customers/list.php');
}

$formType = (string) ($_POST['form_type'] ?? 'profile');
$customerId = trim((string) ($_POST['id'] ?? ''));

if ($formType === 'ban') {
    require_permission('customers.update');

    if ($customerId === '') {
        flash_set('customer_errors', ['customer' => 'Customer not found.']);
        redirect_to('/customers/list.php');
    }

    $result = Customer::ban($customerId);

    if (!($result['success'] ?? false)) {
        flash_set('customer_errors', ['customer' => (string) ($result['error'] ?? 'Customer could not be banned.')]);
        redirect_to('/customers/view.php?id=' . urlencode($customerId));
    }

    flash_set('customer_success', (string) ($result['name'] ?? 'Customer') . ' has been banned successfully.');
    redirect_to('/customers/view.php?id=' . urlencode($customerId));
}

if ($formType === 'notes') {
    require_permission('customers.update');

    if ($customerId === '') {
        redirect_to('/customers/list.php');
    }

    $customer = Customer::saveNotes($customerId, [
        'preference' => trim((string) ($_POST['preference'] ?? '')),
        'admin_notes' => trim((string) ($_POST['admin_notes'] ?? '')),
        'tags' => trim((string) ($_POST['tags'] ?? '')),
    ]);

    if (!is_array($customer) || trim((string) ($customer['id'] ?? '')) === '') {
        flash_set('customer_errors', ['customer' => 'We could not update this customer in the database.']);
        redirect_to('/customers/notes.php?id=' . urlencode($customerId));
    }

    flash_set('customer_success', 'Customer notes updated successfully.');
    redirect_to('/customers/notes.php?id=' . urlencode($customerId));
}

$permission = $customerId === '' ? 'customers.create' : 'customers.update';
require_permission($permission);

$payload = [
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
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

if (!is_array($customer) || trim((string) ($customer['id'] ?? '')) === '') {
    flash_set('customer_errors', ['customer' => 'We could not save this customer to the database.']);
    remember_old_input($payload);

    $redirect = $customerId === '' ? '/customers/create.php' : '/customers/edit.php?id=' . urlencode($customerId);
    redirect_to($redirect);
}

clear_old_input();
flash_set('customer_success', $customerId === '' ? 'Customer created successfully.' : 'Customer updated successfully.');

redirect_to('/customers/view.php?id=' . urlencode($customer['id']));
