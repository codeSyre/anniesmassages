<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/roles/list.php');
}

$action = trim((string) ($_POST['action'] ?? 'save_role'));

if ($action === 'assign_user') {
    require_permission('roles.update');

    $userId = trim((string) ($_POST['user_id'] ?? ''));
    $roleId = trim((string) ($_POST['role_id'] ?? ''));
    $user = Role::assignUserRole($userId, $roleId);

    if ($user === null) {
        flash_set('role_errors', ['assignment' => 'Choose a valid admin user and role, and make sure the database connection is available.']);
    } else {
        flash_set('role_success', 'Access assignment updated for ' . $user['name'] . '.');
    }

    redirect_to('/roles/list.php');
}

if ($action === 'delete_role') {
    require_permission('roles.update');

    $roleId = trim((string) ($_POST['id'] ?? ''));
    $result = Role::deleteRole($roleId);

    if (!($result['success'] ?? false)) {
        flash_set('role_errors', ['role' => (string) ($result['error'] ?? 'Role could not be deleted.')]);
        $redirect = $roleId !== '' ? '/roles/view.php?id=' . urlencode($roleId) : '/roles/list.php';
        redirect_to($redirect);
    }

    flash_set('role_success', 'Role deleted successfully: ' . (string) ($result['name'] ?? 'Role') . '.');
    redirect_to('/roles/list.php');
}

$roleId = trim((string) ($_POST['id'] ?? ''));
$permission = $roleId === '' ? 'roles.create' : 'roles.update';
require_permission($permission);

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'permissions' => array_values(array_filter(array_map(static fn (mixed $permissionId): string => trim((string) $permissionId), (array) ($_POST['permissions'] ?? [])))),
];

$errors = Role::validateRole($payload, $roleId !== '' ? $roleId : null);

if ($errors !== []) {
    flash_set('role_errors', $errors);
    remember_old_input($payload);
    $redirect = $roleId === '' ? '/roles/create.php' : '/roles/edit.php?id=' . urlencode($roleId);
    redirect_to($redirect);
}

$role = Role::saveRole($payload, $roleId !== '' ? $roleId : null);

if ($role === null) {
    flash_set('role_errors', ['role' => 'Role could not be saved to the database. Check the database connection and try again.']);
    remember_old_input($payload);
    $redirect = $roleId === '' ? '/roles/create.php' : '/roles/edit.php?id=' . urlencode($roleId);
    redirect_to($redirect);
}

clear_old_input();
flash_set('role_success', $roleId === '' ? 'Role created successfully.' : 'Role updated successfully.');

redirect_to('/roles/view.php?id=' . urlencode($role['id']));
