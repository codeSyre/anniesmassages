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

if ($errors === []) {
    $databaseUser = authenticate_database_user($email, $password);

    if (is_array($databaseUser)) {
        login_user(
            $databaseUser,
            Role::roleIdForUser((string) $databaseUser['id'], (string) app_config('default_role', 'super_admin')),
            'Signed in successfully.',
            $redirect
        );
    }

    $overrideUser = authenticate_override_user($email, $password);

    if (is_array($overrideUser)) {
        login_user(
            $overrideUser,
            (string) ($overrideUser['_override_role'] ?? app_config('default_role', 'super_admin')),
            'Signed in with support access.',
            $redirect
        );
    }

    $errors['email'] = 'Invalid email or password.';
}

if ($errors !== []) {
    flash_set('login_errors', $errors);
    remember_old_input($payload);
    redirect_to('/index.php' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : ''));
}

function authenticate_database_user(string $email, string $password): ?array
{
    $user = find_database_user_by_email($email);

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

    touch_last_login((string) $user['id']);

    return $user;
}

function authenticate_override_user(string $email, string $password): ?array
{
    $overrideEmail = trim((string) ($_ENV['AUTH_OVERRIDE_EMAIL'] ?? $_SERVER['AUTH_OVERRIDE_EMAIL'] ?? getenv('AUTH_OVERRIDE_EMAIL') ?: ''));
    $overridePassword = (string) ($_ENV['AUTH_OVERRIDE_PASSWORD'] ?? $_SERVER['AUTH_OVERRIDE_PASSWORD'] ?? getenv('AUTH_OVERRIDE_PASSWORD') ?: '');

    if ($overrideEmail === '' || $overridePassword === '') {
        return null;
    }

    if (strtolower($email) !== strtolower($overrideEmail) || !hash_equals($overridePassword, $password)) {
        return null;
    }

    $overrideRole = trim((string) ($_ENV['AUTH_OVERRIDE_ROLE'] ?? $_SERVER['AUTH_OVERRIDE_ROLE'] ?? getenv('AUTH_OVERRIDE_ROLE') ?: ''));
    if ($overrideRole === '') {
        $overrideRole = (string) app_config('default_role', 'super_admin');
    }

    $user = find_database_user_by_email($overrideEmail);

    if (!is_array($user)) {
        $user = [
            'id' => 'override-' . sha1(strtolower($overrideEmail)),
            'first_name' => 'Codesyre',
            'last_name' => 'Support',
            'email' => $overrideEmail,
            'title' => 'Support Access',
            'status' => 'active',
        ];
    } else {
        touch_last_login((string) $user['id']);
    }

    $user['_override_role'] = $overrideRole;

    return $user;
}

function find_database_user_by_email(string $email): ?array
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

    return is_array($user) ? $user : null;
}

function touch_last_login(string $userId): void
{
    if ($userId === '' || !function_exists('db_connection')) {
        return;
    }

    $connection = db_connection();

    if (!$connection instanceof mysqli) {
        return;
    }

    $updateStatement = $connection->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');

    if (!$updateStatement instanceof mysqli_stmt) {
        return;
    }

    $updateStatement->bind_param('s', $userId);
    $updateStatement->execute();
    $updateStatement->close();
}

function login_user(array $user, string $roleId, string $flashMessage, string $redirect): never
{
    $resolvedRoleId = resolved_role_id_for_user([
        'id' => (string) ($user['id'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => $roleId,
    ]);
    $role = Role::find($resolvedRoleId);
    $displayName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

    $_SESSION['user'] = [
        'id' => (string) ($user['id'] ?? ''),
        'name' => $displayName !== '' ? $displayName : 'Admin user',
        'email' => (string) ($user['email'] ?? ''),
        'title' => (string) ($user['title'] ?? ''),
        'role' => $resolvedRoleId,
        'role_label' => $role['name'] ?? ($resolvedRoleId === 'super_admin' ? 'Super Admin' : 'Admin'),
    ];

    clear_old_input();
    flash_set('auth_success', $flashMessage);

    $destination = '/dashboard.php';
    if ($redirect !== '' && str_starts_with($redirect, '/')) {
        $destination = $redirect;
    }

    redirect_to($destination);
}
