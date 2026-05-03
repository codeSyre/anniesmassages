<?php declare(strict_types=1);

require_once __DIR__ . '/Role.php';

final class Profile
{
    public static function current(array $currentUser): array
    {
        $userId = (string) ($currentUser['id'] ?? '');
        $base = self::baseProfile($currentUser);
        $overrides = $_SESSION['profile_overrides'][$userId] ?? [];
        $profile = array_replace($base, is_array($overrides) ? $overrides : []);

        $role = Role::find((string) ($currentUser['role'] ?? 'super_admin'));
        $permissions = $role['permissions'] ?? [];
        $groupedPermissions = [];

        foreach (Role::permissionGroups() as $group => $items) {
            $groupedPermissions[$group] = [];

            foreach ($items as $permission => $description) {
                if (!in_array($permission, $permissions, true)) {
                    continue;
                }

                $groupedPermissions[$group][$permission] = $description;
            }
        }

        $groupedPermissions = array_filter($groupedPermissions);
        $profile['role'] = $role;
        $profile['permissions'] = $permissions;
        $profile['permission_groups'] = $groupedPermissions;
        $profile['permission_count'] = count($permissions);
        $profile['access_scope_count'] = count($groupedPermissions);
        $profile['recent_activity'] = self::recentActivity($profile);

        return $profile;
    }

    public static function validate(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'] = 'Full name is required.';
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'Job title is required.';
        }

        if (trim((string) ($payload['timezone'] ?? '')) === '') {
            $errors['timezone'] = 'Timezone is required.';
        }

        return $errors;
    }

    public static function save(array $currentUser, array $payload): array
    {
        $userId = (string) ($currentUser['id'] ?? '');
        $profile = self::current($currentUser);

        $saved = [
            'name' => trim((string) ($payload['name'] ?? $profile['name'])),
            'email' => trim((string) ($payload['email'] ?? $profile['email'])),
            'title' => trim((string) ($payload['title'] ?? $profile['title'])),
            'phone' => trim((string) ($payload['phone'] ?? $profile['phone'])),
            'timezone' => trim((string) ($payload['timezone'] ?? $profile['timezone'])),
            'bio' => trim((string) ($payload['bio'] ?? $profile['bio'])),
            'daily_brief' => ($payload['daily_brief'] ?? '0') === '1',
            'payment_alerts' => ($payload['payment_alerts'] ?? '0') === '1',
            'inventory_alerts' => ($payload['inventory_alerts'] ?? '0') === '1',
            'marketing_updates' => ($payload['marketing_updates'] ?? '0') === '1',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['profile_overrides'][$userId] = $saved;

        if (isset($_SESSION['user']) && (string) ($_SESSION['user']['id'] ?? '') === $userId) {
            $_SESSION['user']['name'] = $saved['name'];
            $_SESSION['user']['email'] = $saved['email'];
        }

        return self::current($_SESSION['user'] ?? $currentUser);
    }

    private static function recentActivity(array $profile): array
    {
        return [
            [
                'label' => 'Last sign-in',
                'meta' => date('j M Y H:i', strtotime((string) $profile['last_active_at'])),
                'tone' => 'info',
            ],
            [
                'label' => 'Role in use',
                'meta' => (string) ($profile['role']['name'] ?? 'Unassigned'),
                'tone' => 'success',
            ],
            [
                'label' => 'Password last rotated',
                'meta' => date('j M Y', strtotime((string) $profile['password_changed_at'])),
                'tone' => 'warning',
            ],
            [
                'label' => 'Security note',
                'meta' => $profile['two_factor_enabled'] ? 'Two-factor enabled' : 'Two-factor not yet enabled',
                'tone' => $profile['two_factor_enabled'] ? 'success' : 'warning',
            ],
        ];
    }

    private static function baseProfile(array $currentUser): array
    {
        $roleUsers = Role::users();
        $mapped = null;

        foreach ($roleUsers as $user) {
            if ((string) $user['id'] === (string) ($currentUser['id'] ?? '')) {
                $mapped = $user;
                break;
            }
        }

        return [
            'id' => (string) ($currentUser['id'] ?? ''),
            'name' => (string) ($currentUser['name'] ?? 'Admin User'),
            'email' => (string) ($currentUser['email'] ?? 'admin@example.com'),
            'title' => (string) ($mapped['title'] ?? 'Administrator'),
            'phone' => '+263 77 200 1000',
            'timezone' => 'Africa/Harare',
            'bio' => 'Keeps the Annie’s Massages control room calm, accurate, and ready for the day ahead.',
            'status' => (string) ($mapped['status'] ?? 'active'),
            'role_id' => (string) ($currentUser['role'] ?? 'super_admin'),
            'role_label' => (string) ($currentUser['role_label'] ?? 'Super Admin'),
            'last_active_at' => (string) ($mapped['last_active_at'] ?? date('Y-m-d H:i:s')),
            'password_changed_at' => date('Y-m-d H:i:s', strtotime('first day of last month 09:15')),
            'created_at' => date('Y-m-d H:i:s', strtotime('first day of this year 09:00')),
            'two_factor_enabled' => true,
            'daily_brief' => true,
            'payment_alerts' => true,
            'inventory_alerts' => true,
            'marketing_updates' => false,
            'updated_at' => date('Y-m-d H:i:s', strtotime('today 07:30')),
        ];
    }
}
