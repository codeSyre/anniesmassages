<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Service.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/services/list.php');
}

$serviceId = trim((string) ($_POST['id'] ?? ''));
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
clear_old_input();
flash_set('service_success', $serviceId === '' ? 'Service created successfully.' : 'Service updated successfully.');

redirect_to('/services/view.php?id=' . urlencode($service['id']));
