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

if ($errors === []) {
    $databaseUser = authenticate_database_user($email, $password);

    if (is_array($databaseUser)) {
        $roleId = Role::roleIdForUser((string) $databaseUser['id'], (string) app_config('default_role', 'super_admin'));
        $role = Role::find($roleId);

        $_SESSION['user'] = [
            'id' => (string) $databaseUser['id'],
            'name' => trim((string) $databaseUser['first_name'] . ' ' . (string) $databaseUser['last_name']),
            'email' => (string) $databaseUser['email'],
            'title' => (string) ($databaseUser['title'] ?? ''),
            'role' => $roleId,
            'role_label' => $role['name'] ?? 'Super Admin',
        ];

        clear_old_input();
        flash_set('auth_success', 'Signed in successfully.');

        $destination = '/dashboard.php';
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            $destination = $redirect;
        }

        redirect_to($destination);
    }
}

if ($errors === [] && (strtolower($email) !== strtolower($expectedEmail) || $password !== $expectedPassword)) {
    $errors['email'] = 'Use the configured credentials for this account.';
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

function authenticate_database_user(string $email, string $password): ?array
{
    if (!function_exists('db_connection')) {
        return null;
    }

    $connection = db_connection();

    if (!$connection instanceof mysqli) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT id, first_name, last_name, email, password_hash, title, status
         FROM users
         WHERE LOWER(email) = LOWER(?)
         LIMIT 1'
    );

    if (!$statement instanceof mysqli_stmt) {
        return null;
    }

    $statement->bind_param('s', $email);

    if (!$statement->execute()) {
        $statement->close();

        return null;
    }

    $result = $statement->get_result();
    $user = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $statement->close();

    if (!is_array($user)) {
        return null;
    }

    if ((string) ($user['status'] ?? 'active') !== 'active') {
        return null;
    }

    $hash = (string) ($user['password_hash'] ?? '');

    if ($hash === '' || !password_verify($password, $hash)) {
        return null;
    }

    $updateStatement = $connection->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');

    if ($updateStatement instanceof mysqli_stmt) {
        $userId = (string) $user['id'];
        $updateStatement->bind_param('s', $userId);
        $updateStatement->execute();
        $updateStatement->close();
    }

    return $user;
}
