<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Service.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/services/list.php');
}

$serviceId = trim((string) ($_POST['id'] ?? ''));
$action = trim((string) ($_POST['action'] ?? 'save_service'));

if ($action === 'delete_service') {
    require_permission('services.delete');

    if ($serviceId === '') {
        flash_set('service_errors', ['service' => 'Service not found.']);
        redirect_to('/services/list.php');
    }

    $result = Service::delete($serviceId);

    if (!($result['success'] ?? false)) {
        flash_set('service_errors', ['service' => (string) ($result['error'] ?? 'Service could not be deleted.')]);
        redirect_to('/services/view.php?id=' . urlencode($serviceId));
    }

    flash_set('service_success', (string) ($result['name'] ?? 'Service') . ' was deleted successfully.');
    redirect_to('/services/list.php');
}

$permission = $serviceId === '' ? 'services.create' : 'services.update';
require_permission($permission);

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? 'Massage')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'price' => trim((string) ($_POST['price'] ?? '')),
    'duration' => trim((string) ($_POST['duration'] ?? '')),
    'buffer' => trim((string) ($_POST['buffer'] ?? '15')),
    'room' => trim((string) ($_POST['room'] ?? 'Studio')),
    'addons' => trim((string) ($_POST['addons'] ?? '')),
    'active' => isset($_POST['active']) ? '1' : '0',
];

$errors = Service::validate($payload);

if ($errors !== []) {
    flash_set('service_errors', $errors);
    remember_old_input($payload);
    $redirect = $serviceId === '' ? '/services/create.php' : '/services/edit.php?id=' . urlencode($serviceId);
    redirect_to($redirect);
}

$service = Service::save($payload, $serviceId !== '' ? $serviceId : null);

if (!is_array($service) || trim((string) ($service['id'] ?? '')) === '') {
    flash_set('service_errors', ['service' => 'We could not save this service to the database.']);
    remember_old_input($payload);
    $redirect = $serviceId === '' ? '/services/create.php' : '/services/edit.php?id=' . urlencode($serviceId);
    redirect_to($redirect);
}

clear_old_input();
flash_set('service_success', $serviceId === '' ? 'Service created successfully.' : 'Service updated successfully.');

redirect_to('/services/view.php?id=' . urlencode($service['id']));
