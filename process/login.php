<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/index.php');
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = trim((string) ($_POST['password'] ?? ''));
$redirect = trim((string) ($_POST['redirect'] ?? ''));

$payload = [
    'email' => $email,
    'redirect' => $redirect,
];

$errors = [];

if ($email === '') {
    $errors['email'] = 'Email address is required.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
}

$expectedEmail = 'codesyre@gmail.com';
$expectedPassword = 'Password@123%';

if ($errors === [] && (strtolower($email) !== strtolower($expectedEmail) || $password !== $expectedPassword)) {
    $errors['email'] = 'Use the configured super admin credentials for now.';
}

if ($errors !== []) {
    flash_set('login_errors', $errors);
    remember_old_input($payload);
    redirect_to('/index.php' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : ''));
}

$roleId = (string) app_config('default_role', 'super_admin');
$role = Role::find($roleId);

$_SESSION['user'] = [
    'id' => '1',
    'name' => 'Codesyre Super Admin',
    'email' => $expectedEmail,
    'role' => $roleId,
    'role_label' => $role['name'] ?? 'Super Admin',
];

clear_old_input();
flash_set('auth_success', 'Signed in as Super Admin.');

$destination = '/dashboard.php';
if ($redirect !== '' && str_starts_with($redirect, '/')) {
    $destination = $redirect;
}

redirect_to($destination);
