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
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
    'name' => trim((string) ($_POST['name'] ?? '')),
    'specialty' => trim((string) ($_POST['specialty'] ?? '')),
    'role_type' => trim((string) ($_POST['role_type'] ?? 'therapist')),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'address_line_1' => trim((string) ($_POST['address_line_1'] ?? '')),
    'address_line_2' => trim((string) ($_POST['address_line_2'] ?? '')),
    'city_town' => trim((string) ($_POST['city_town'] ?? '')),
    'country' => trim((string) ($_POST['country'] ?? '')),
    'profile_picture_path' => trim((string) ($_POST['profile_picture_path'] ?? '')),
    'bio' => trim((string) ($_POST['bio'] ?? '')),
    'capacity' => trim((string) ($_POST['capacity'] ?? '4 sessions/day')),
    'salary_structure' => trim((string) ($_POST['salary_structure'] ?? 'commission')),
    'commission_rate' => trim((string) ($_POST['commission_rate'] ?? '0')),
    'fixed_pay' => trim((string) ($_POST['fixed_pay'] ?? '0')),
    'color' => trim((string) ($_POST['color'] ?? 'cyan')),
];

$errors = Staff::validate($payload, $staffId !== '' ? $staffId : null);

if ($errors !== []) {
    flash_set('staff_errors', $errors);
    remember_old_input($payload);
    $redirect = $staffId === '' ? '/staff/create.php' : '/staff/edit.php?id=' . urlencode($staffId);
    redirect_to($redirect);
}

$staff = Staff::save($payload, $staffId !== '' ? $staffId : null);

if (!is_array($staff) || trim((string) ($staff['id'] ?? '')) === '') {
    flash_set('staff_errors', ['staff' => 'We could not save this staff profile to the database.']);
    remember_old_input($payload);
    $redirect = $staffId === '' ? '/staff/create.php' : '/staff/edit.php?id=' . urlencode($staffId);
    redirect_to($redirect);
}

clear_old_input();
flash_set('staff_success', $staffId === '' ? 'Staff profile created successfully.' : 'Staff profile updated successfully.');

redirect_to('/staff/view.php?id=' . urlencode($staff['id']));
