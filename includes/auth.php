<?php declare(strict_types=1);

function current_user(): ?array
{
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $user = $_SESSION['user'];
    } else {
        return null;
    }

    require_once __DIR__ . '/../models/Role.php';
    $roleId = Role::roleIdForUser((string) ($user['id'] ?? ''), (string) ($user['role'] ?? app_config('default_role', 'super_admin')));
    $role = Role::find($roleId);

    if ($role !== null) {
        $user['role'] = $roleId;
        $user['role_label'] = $role['name'];
        $_SESSION['user'] = $user;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
        header('Location: /index.php?redirect=' . urlencode((string) $redirect));
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

    require_once __DIR__ . '/../models/Role.php';
    $roleId = Role::roleIdForUser((string) ($user['id'] ?? ''), (string) ($user['role'] ?? app_config('default_role', 'super_admin')));
    $granted = $_SESSION['permissions'] ?? Role::permissionsForRole($roleId);

    return in_array($permission, $granted, true);
}

function require_permission(string $permission): void
{
    if (!user_can($permission)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
