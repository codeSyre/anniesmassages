<?php declare(strict_types=1);

function app_config(string $key, mixed $default = null): mixed
{
    global $appConfig;

    return $appConfig[$key] ?? $default;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn (string $part): string => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2));

    return implode('', $letters) ?: 'AM';
}

function app_navigation(): array
{
    return [
        'Operations' => [
            ['label' => 'Dashboard', 'href' => '/dashboard.php', 'icon' => 'overview', 'route' => 'dashboard'],
            ['label' => 'Bookings', 'href' => '/bookings/list.php', 'icon' => 'bookings', 'route' => 'bookings'],
            ['label' => 'Calendar', 'href' => '/scheduling/calendar.php', 'icon' => 'calendar', 'route' => 'calendar'],
            ['label' => 'Customers', 'href' => '/customers/list.php', 'icon' => 'customers', 'route' => 'customers'],
            ['label' => 'Services', 'href' => '/services/list.php', 'icon' => 'services', 'route' => 'services'],
            ['label' => 'Staff / Therapists', 'href' => '/staff/list.php', 'icon' => 'staff', 'route' => 'staff'],
        ],
        'Finance' => [
            ['label' => 'Payments Ledger', 'href' => '/payments/ledger.php', 'icon' => 'payments', 'route' => 'payments'],
            ['label' => 'Inventory', 'href' => '/inventory/list.php', 'icon' => 'inventory', 'route' => 'inventory'],
            ['label' => 'Payroll', 'href' => '/payroll/dashboard.php', 'icon' => 'payroll', 'route' => 'payroll'],
        ],
        'Management' => [
            ['label' => 'Notifications', 'href' => '/notifications/templates.php', 'icon' => 'notifications', 'route' => 'notifications'],
            ['label' => 'Reports', 'href' => '/reports/dashboard.php', 'icon' => 'reports', 'route' => 'reports'],
            ['label' => 'Roles & Permissions', 'href' => '/roles/list.php', 'icon' => 'roles', 'route' => 'roles'],
        ],
        'System' => [
            ['label' => 'Admin Profile', 'href' => '/profile/index.php', 'icon' => 'profile', 'route' => 'profile'],
            ['label' => 'Settings', 'href' => '/settings/index.php', 'icon' => 'settings', 'route' => 'settings'],
        ],
    ];
}

function badge_class(string $tone): string
{
    return match ($tone) {
        'success' => 'badge badge-success',
        'warning' => 'badge badge-warning',
        'danger' => 'badge badge-danger',
        'info' => 'badge badge-info',
        default => 'badge badge-neutral',
    };
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function flash_get(string $key, mixed $default = null): mixed
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function remember_old_input(array $input): void
{
    $_SESSION['_old'] = $input;
}

function old_input(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function status_badge_class(string $status): string
{
    return badge_class(match ($status) {
        'confirmed', 'completed', 'paid' => 'success',
        'pending', 'partial', 'pending_payment', 'rescheduled' => 'warning',
        'cancelled', 'no_show', 'refunded' => 'danger',
        default => 'info',
    });
}

function active_filter(string $current, string $expected): string
{
    return $current === $expected ? 'filter-chip is-active' : 'filter-chip';
}
