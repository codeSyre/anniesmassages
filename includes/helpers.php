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

function format_quantity(int|float $quantity): string
{
    $formatted = number_format((float) $quantity, 2, '.', '');

    return rtrim(rtrim($formatted, '0'), '.');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn (string $part): string => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2));

    return implode('', $letters) ?: 'AM';
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function app_navigation(): array
{
    return [
        'Operations' => [
            ['label' => 'Dashboard',        'href' => '/dashboard.php',          'icon' => 'overview',  'route' => 'dashboard', 'permission' => 'dashboard.view'],
            ['label' => 'Bookings',         'href' => '/bookings/list.php',       'icon' => 'bookings',  'route' => 'bookings',  'permission' => 'bookings.view'],
            ['label' => 'Calendar',         'href' => '/scheduling/calendar.php', 'icon' => 'calendar',  'route' => 'calendar',  'permission' => 'bookings.view'],
            ['label' => 'Customers',        'href' => '/customers/list.php',      'icon' => 'customers', 'route' => 'customers', 'permission' => 'customers.view'],
            ['label' => 'Services',         'href' => '/services/list.php',       'icon' => 'services',  'route' => 'services',  'permission' => 'services.view'],
            ['label' => 'Staff',            'href' => '/staff/list.php',         'icon' => 'staff',     'route' => 'staff',     'permission' => 'staff.view'],
        ],
        'Finance' => [
            ['label' => 'Payments Ledger', 'href' => '/payments/ledger.php',    'icon' => 'payments',  'route' => 'payments', 'permission' => 'payments.view'],
            ['label' => 'Inventory',       'href' => '/inventory/list.php',     'icon' => 'inventory', 'route' => 'inventory','permission' => 'inventory.manage'],
            ['label' => 'Payroll',         'href' => '/payroll/dashboard.php',  'icon' => 'payroll',   'route' => 'payroll',  'permission' => 'payroll.manage'],
        ],
        'Management' => [
            ['label' => 'Reports',           'href' => '/reports/dashboard.php', 'icon' => 'reports', 'route' => 'reports', 'permission' => 'reports.view'],
            ['label' => 'Roles & Permissions','href' => '/roles/list.php',       'icon' => 'roles',   'route' => 'roles',   'permission' => 'roles.view'],
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
        'pending', 'partial', 'pending_payment', 'rescheduled', 'suspended', 'on_leave' => 'warning',
        'banned' => 'danger',
        'cancelled', 'no_show', 'refunded' => 'danger',
        default => 'info',
    });
}

function active_filter(string $current, string $expected): string
{
    return $current === $expected ? 'filter-chip is-active' : 'filter-chip';
}

function nav_icon_svg(string $icon): string
{
    $paths = match ($icon) {
        'overview' => '<path d="M4.75 5.75h6.5v5.5h-6.5z" /><path d="M12.75 5.75h6.5v8h-6.5z" /><path d="M4.75 12.75h6.5v5.5h-6.5z" /><path d="M12.75 15.25h6.5v3h-6.5z" />',
        'bookings' => '<rect x="4.75" y="6.25" width="14.5" height="12.5" rx="2.5" /><path d="M8 4.75v3" /><path d="M16 4.75v3" /><path d="M4.75 10.25h14.5" />',
        'calendar' => '<rect x="4.75" y="5.75" width="14.5" height="13.5" rx="2.5" /><path d="M8 4.75v3" /><path d="M16 4.75v3" /><path d="M4.75 10h14.5" /><path d="M9 13.25h2.5v2.5H9z" />',
        'customers' => '<path d="M12 12.25a3.25 3.25 0 1 0 0-6.5a3.25 3.25 0 0 0 0 6.5Z" /><path d="M6.75 18.25a5.25 5.25 0 0 1 10.5 0" /><path d="M17.75 8.5a2.25 2.25 0 1 1 0 4.5" />',
        'services' => '<path d="M7.75 7.25h8.5" /><path d="M9 4.75h6" /><rect x="6.25" y="7.25" width="11.5" height="12" rx="3" /><path d="M9.25 11.25h5.5" /><path d="M9.25 14.75h3.5" />',
        'staff' => '<path d="M9.25 11a3 3 0 1 0 0-6a3 3 0 0 0 0 6Z" /><path d="M4.75 18.25a4.5 4.5 0 0 1 9 0" /><path d="M16.5 10.5l1.25 1.25l2.5-2.75" /><circle cx="17.5" cy="10.5" r="3.5" />',
        'payments' => '<rect x="4.75" y="6.25" width="14.5" height="11.5" rx="2.5" /><path d="M4.75 10h14.5" /><path d="M8 14h3.25" /><path d="M15.5 13.25h.01" />',
        'inventory' => '<path d="M12 4.75l6.25 3.25v8L12 19.25L5.75 16V8L12 4.75Z" /><path d="M5.75 8L12 11.25L18.25 8" /><path d="M12 11.25v8" />',
        'payroll' => '<path d="M6.25 18.25h11.5" /><path d="M8.25 15.25V10.5" /><path d="M12 15.25V6.75" /><path d="M15.75 15.25v-3.5" />',
        'notifications' => '<path d="M12 19.25a2.25 2.25 0 0 0 2.25-2.25h-4.5A2.25 2.25 0 0 0 12 19.25Z" /><path d="M7.5 17v-4.75a4.5 4.5 0 1 1 9 0V17" /><path d="M6 17h12" />',
        'reports' => '<path d="M6.25 18.25h11.5" /><path d="M8.25 15.25V12" /><path d="M12 15.25V8.25" /><path d="M15.75 15.25v-5" /><path d="M6.75 6.75h10.5" />',
        'roles' => '<path d="M12 10.75a2.75 2.75 0 1 0 0-5.5a2.75 2.75 0 0 0 0 5.5Z" /><path d="M7 18.25a5 5 0 0 1 10 0" /><path d="M17.25 6.25h2" /><path d="M18.25 5.25v2" />',
        'profile' => '<path d="M12 11.75a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7Z" /><path d="M6.25 18.25a5.75 5.75 0 0 1 11.5 0" />',
        'settings' => '<path d="M12 8.75a3.25 3.25 0 1 0 0 6.5a3.25 3.25 0 0 0 0-6.5Z" /><path d="M12 4.75v1.5" /><path d="M12 17.75v1.5" /><path d="M19.25 12h-1.5" /><path d="M6.25 12h-1.5" /><path d="M17.12 6.88l-1.06 1.06" /><path d="M7.94 16.06l-1.06 1.06" /><path d="M17.12 17.12l-1.06-1.06" /><path d="M7.94 7.94L6.88 6.88" />',
        default => '<circle cx="12" cy="12" r="6.5" />',
    };

    return '<svg class="nav-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

function action_icon_svg(string $icon): string
{
    $paths = match ($icon) {
        'view' => '<path d="M2.75 12s3.25-5.25 9.25-5.25S21.25 12 21.25 12s-3.25 5.25-9.25 5.25S2.75 12 2.75 12Z" /><circle cx="12" cy="12" r="2.5" />',
        'edit' => '<path d="M4.75 19.25h3.5l9-9a1.75 1.75 0 0 0-3.5-3.5l-9 9v3.5Z" /><path d="M12.75 6.75l3.5 3.5" />',
        'suspend' => '<circle cx="12" cy="12" r="8.25" /><path d="M8.5 8.5l7 7" />',
        'delete' => '<path d="M5.75 7.25h12.5" /><path d="M9.25 4.75h5.5" /><path d="M8.25 7.25v10a1 1 0 0 0 1 1h5.5a1 1 0 0 0 1-1v-10" /><path d="M10.25 10.25v5" /><path d="M13.75 10.25v5" />',
        default => '<circle cx="12" cy="12" r="6.5" />',
    };

    return '<svg class="action-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}
