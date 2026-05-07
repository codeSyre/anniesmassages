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

    public static function validate(array $payload, array $currentUser): array
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

        $currentPassword = trim((string) ($payload['current_password'] ?? ''));
        $newPassword = trim((string) ($payload['new_password'] ?? ''));
        $confirmPassword = trim((string) ($payload['confirm_password'] ?? ''));
        $wantsPasswordChange = $currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '';

        if ($wantsPasswordChange) {
            if ($currentPassword === '') {
                $errors['current_password'] = 'Enter your current password.';
            }

            if ($newPassword === '') {
                $errors['new_password'] = 'Enter a new password.';
            } elseif (strlen($newPassword) < 8) {
                $errors['new_password'] = 'New password must be at least 8 characters.';
            }

            if ($confirmPassword === '') {
                $errors['confirm_password'] = 'Confirm the new password.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'The new passwords do not match.';
            }

            if (!isset($errors['current_password']) && !self::verifyCurrentPassword($currentUser, $currentPassword)) {
                $errors['current_password'] = 'The current password is incorrect.';
            }
        }

        return $errors;
    }

    public static function save(array $currentUser, array $payload): array
    {
        $userId = (string) ($currentUser['id'] ?? '');
        $profile = self::current($currentUser);
        $name = trim((string) ($payload['name'] ?? $profile['name']));
        [$firstName, $lastName] = self::splitName($name);

        $saved = [
            'name' => $name,
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

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $statement = $connection->prepare(
                'UPDATE users
                 SET first_name = ?, last_name = ?, email = ?, title = ?, phone = ?, timezone = ?, bio = ?, updated_at = NOW()
                 WHERE id = ?'
            );

            if ($statement instanceof mysqli_stmt) {
                $email = $saved['email'];
                $title = $saved['title'];
                $phone = $saved['phone'];
                $timezone = $saved['timezone'];
                $bio = $saved['bio'];
                $statement->bind_param('ssssssss', $firstName, $lastName, $email, $title, $phone, $timezone, $bio, $userId);
                $statement->execute();
                $statement->close();
            }

            $newPassword = trim((string) ($payload['new_password'] ?? ''));
            if ($newPassword !== '') {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordStatement = $connection->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');

                if ($passwordStatement instanceof mysqli_stmt) {
                    $passwordStatement->bind_param('ss', $passwordHash, $userId);
                    $passwordStatement->execute();
                    $passwordStatement->close();
                }

                $saved['password_changed_at'] = date('Y-m-d H:i:s');
            }
        }

        $_SESSION['profile_overrides'][$userId] = $saved;

        if (isset($_SESSION['user']) && (string) ($_SESSION['user']['id'] ?? '') === $userId) {
            $_SESSION['user']['name'] = $saved['name'];
            $_SESSION['user']['email'] = $saved['email'];
            $_SESSION['user']['title'] = $saved['title'];
        }

        return self::current($_SESSION['user'] ?? $currentUser);
    }

    private static function verifyCurrentPassword(array $currentUser, string $currentPassword): bool
    {
        $connection = self::connection();
        $userId = (string) ($currentUser['id'] ?? '');

        if ($connection instanceof mysqli) {
            $statement = $connection->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');

            if ($statement instanceof mysqli_stmt) {
                $statement->bind_param('s', $userId);

                if ($statement->execute()) {
                    $result = $statement->get_result();
                    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
                    $statement->close();

                    if (is_array($row)) {
                        $hash = (string) ($row['password_hash'] ?? '');

                        return $hash !== '' && password_verify($currentPassword, $hash);
                    }
                } else {
                    $statement->close();
                }
            }
        }

        $isDemoSuperAdmin = strtolower((string) ($currentUser['email'] ?? '')) === 'codesyre@gmail.com';

        return $isDemoSuperAdmin && $currentPassword === 'Password@123%';
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
        $databaseProfile = self::databaseProfile((string) ($currentUser['id'] ?? ''));
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
            'name' => (string) ($databaseProfile['name'] ?? ($currentUser['name'] ?? 'Admin User')),
            'email' => (string) ($databaseProfile['email'] ?? ($currentUser['email'] ?? 'admin@example.com')),
            'title' => (string) ($databaseProfile['title'] ?? ($mapped['title'] ?? 'Administrator')),
            'phone' => (string) ($databaseProfile['phone'] ?? '+263 77 200 1000'),
            'timezone' => (string) ($databaseProfile['timezone'] ?? 'Africa/Harare'),
            'bio' => (string) ($databaseProfile['bio'] ?? 'Keeps the Annie’s Massages control room calm, accurate, and ready for the day ahead.'),
            'status' => (string) ($databaseProfile['status'] ?? ($mapped['status'] ?? 'active')),
            'role_id' => (string) ($currentUser['role'] ?? 'super_admin'),
            'role_label' => (string) ($currentUser['role_label'] ?? 'Super Admin'),
            'last_active_at' => (string) ($databaseProfile['last_active_at'] ?? ($mapped['last_active_at'] ?? date('Y-m-d H:i:s'))),
            'password_changed_at' => (string) ($databaseProfile['password_changed_at'] ?? date('Y-m-d H:i:s', strtotime('first day of last month 09:15'))),
            'created_at' => (string) ($databaseProfile['created_at'] ?? date('Y-m-d H:i:s', strtotime('first day of this year 09:00'))),
            'two_factor_enabled' => true,
            'daily_brief' => true,
            'payment_alerts' => true,
            'inventory_alerts' => true,
            'marketing_updates' => false,
            'updated_at' => (string) ($databaseProfile['updated_at'] ?? date('Y-m-d H:i:s', strtotime('today 07:30'))),
        ];
    }

    private static function databaseProfile(string $userId): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli || $userId === '') {
            return null;
        }

        $statement = $connection->prepare(
            'SELECT id, first_name, last_name, email, title, phone, timezone, bio, status, last_login_at, created_at, updated_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        if (!$statement instanceof mysqli_stmt) {
            return null;
        }

        $statement->bind_param('s', $userId);

        if (!$statement->execute()) {
            $statement->close();

            return null;
        }

        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $statement->close();

        if (!is_array($row)) {
            return null;
        }

        $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));

        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => $name !== '' ? $name : 'Admin User',
            'email' => (string) ($row['email'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'timezone' => (string) ($row['timezone'] ?? 'Africa/Harare'),
            'bio' => (string) ($row['bio'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
            'last_active_at' => (string) (($row['last_login_at'] ?? '') !== '' ? $row['last_login_at'] : ($row['updated_at'] ?? date('Y-m-d H:i:s'))),
            'password_changed_at' => (string) ($row['updated_at'] ?? date('Y-m-d H:i:s')),
            'created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string) ($row['updated_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        if ($firstName === '') {
            $firstName = 'Admin';
        }

        if ($lastName === '') {
            $lastName = 'User';
        }

        return [$firstName, $lastName];
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }
}
