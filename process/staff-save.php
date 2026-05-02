<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/staff/list.php');
}

$staffId = trim((string) ($_POST['id'] ?? ''));
$permission = $staffId === '' ? 'staff.create' : 'staff.update';
require_permission($permission);

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'specialty' => trim((string) ($_POST['specialty'] ?? '')),
    'role_type' => trim((string) ($_POST['role_type'] ?? 'therapist')),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'bio' => trim((string) ($_POST['bio'] ?? '')),
    'capacity' => trim((string) ($_POST['capacity'] ?? '4 sessions/day')),
    'salary_structure' => trim((string) ($_POST['salary_structure'] ?? 'commission')),
    'commission_rate' => trim((string) ($_POST['commission_rate'] ?? '0')),
    'fixed_pay' => trim((string) ($_POST['fixed_pay'] ?? '0')),
    'color' => trim((string) ($_POST['color'] ?? 'cyan')),
];

$errors = Staff::validate($payload);

if ($errors !== []) {
    flash_set('staff_errors', $errors);
    remember_old_input($payload);
    $redirect = $staffId === '' ? '/staff/create.php' : '/staff/edit.php?id=' . urlencode($staffId);
    redirect_to($redirect);
}

$staff = Staff::save($payload, $staffId !== '' ? $staffId : null);
clear_old_input();
flash_set('staff_success', $staffId === '' ? 'Staff profile created successfully.' : 'Staff profile updated successfully.');

redirect_to('/staff/view.php?id=' . urlencode($staff['id']));
