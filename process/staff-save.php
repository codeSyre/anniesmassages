<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/staff/list.php');
}

$staffId = trim((string) ($_POST['id'] ?? ''));
$action = trim((string) ($_POST['action'] ?? 'save_staff'));

if ($action === 'suspend_staff' || $action === 'delete_staff') {
    require_permission('staff.update');

    if ($staffId === '') {
        flash_set('staff_errors', ['staff' => 'Staff member not found.']);
        redirect_to('/staff/list.php');
    }

    $result = $action === 'suspend_staff'
        ? Staff::suspend($staffId)
        : Staff::delete($staffId);

    if (!($result['success'] ?? false)) {
        flash_set('staff_errors', ['staff' => (string) ($result['error'] ?? 'Action could not be completed.')]);
        redirect_to('/staff/view.php?id=' . urlencode($staffId));
    }

    flash_set(
        'staff_success',
        $action === 'suspend_staff'
            ? ((string) ($result['name'] ?? 'Staff member') . ' has been suspended successfully.')
            : ((string) ($result['name'] ?? 'Staff member') . ' has been deleted successfully.')
    );

    redirect_to('/staff/list.php');
}

$permission = $staffId === '' ? 'staff.create' : 'staff.update';
require_permission($permission);

$picturePath = trim((string) ($_POST['profile_picture_path'] ?? ''));
$upload = $_FILES['profile_picture'] ?? null;
if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed, true)) {
        $uploadDir = __DIR__ . '/../uploads/staff/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = uniqid('staff_', true) . '.' . $ext;
        if (move_uploaded_file((string) $upload['tmp_name'], $uploadDir . $filename)) {
            $picturePath = '/uploads/staff/' . $filename;
        }
    }
}

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
    'profile_picture_path' => $picturePath,
    'bio' => trim((string) ($_POST['bio'] ?? '')),
    'capacity' => trim((string) ($_POST['capacity'] ?? '4 sessions/day')),
    'salary_structure' => trim((string) ($_POST['salary_structure'] ?? 'commission')),
    'commission_rate' => trim((string) ($_POST['commission_rate'] ?? '0')),
    'fixed_pay' => trim((string) ($_POST['fixed_pay'] ?? '0')),
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
flash_set(
    'staff_success',
    $staffId === ''
        ? 'Staff profile created successfully. Default login password: ' . Staff::defaultLoginPassword() . '.'
        : 'Staff profile updated successfully.'
);

redirect_to('/staff/view.php?id=' . urlencode($staff['id']));
