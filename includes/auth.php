<?php declare(strict_types=1);

function current_user(): ?array
{
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    if ((bool) app_config('allow_demo_login', false)) {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Annie Admin',
            'email' => 'admin@anniesmassages.test',
            'role' => (string) app_config('default_role', 'super_admin'),
        ];

        return $_SESSION['user'];
    }

    return null;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: /index.php');
        exit;
    }

    return $user;
}

function user_can(string $permission): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    if (($user['role'] ?? '') === 'super_admin') {
        return true;
    }

    $granted = $_SESSION['permissions'] ?? [
        'dashboard.view',
        'bookings.view',
        'payments.view',
        'inventory.manage',
        'reports.view',
    ];

    return in_array($permission, $granted, true);
}

function require_permission(string $permission): void
{
    if (!user_can($permission)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
