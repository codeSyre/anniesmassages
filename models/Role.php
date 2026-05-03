<?php declare(strict_types=1);

final class Role
{
    public static function permissionGroups(): array
    {
        return [
            'Operations' => [
                'dashboard.view' => 'Access the main operational dashboard.',
                'bookings.view' => 'View the bookings list, details, and calendar data.',
                'bookings.create' => 'Create new bookings from admin intake.',
                'bookings.update' => 'Edit, reschedule, and update existing bookings.',
                'customers.view' => 'View customer profiles, notes, and history.',
                'customers.create' => 'Create new customer records.',
                'customers.update' => 'Edit customer preferences, notes, and tags.',
                'services.view' => 'Review service catalog details and summaries.',
                'services.create' => 'Create new treatment offerings.',
                'services.update' => 'Edit existing services and availability details.',
                'staff.view' => 'View therapist profiles and calendars.',
                'staff.create' => 'Create therapist profiles.',
                'staff.update' => 'Edit therapist records and compensation setup.',
            ],
            'Finance' => [
                'payments.view' => 'Review ledger entries, reconciliation, and booking payment history.',
                'payments.create' => 'Record new payments against bookings.',
                'inventory.manage' => 'Manage stock items and movement logs.',
                'payroll.manage' => 'Review earnings and manage payroll runs.',
            ],
            'Management' => [
                'notifications.view' => 'Access templates, reminders, and communication logs.',
                'reports.view' => 'Open reports and analytics across modules.',
                'roles.view' => 'Review role setup and current access assignments.',
                'roles.create' => 'Create new admin roles.',
                'roles.update' => 'Update role permissions and user assignments.',
            ],
        ];
    }

    public static function permissionOptions(): array
    {
        $options = [];

        foreach (self::permissionGroups() as $group => $permissions) {
            foreach ($permissions as $permission => $description) {
                $options[$permission] = [
                    'group' => $group,
                    'label' => ucwords(str_replace(['.', '_'], [' ', ' '], $permission)),
                    'description' => $description,
                ];
            }
        }

        return $options;
    }

    public static function all(array $filters = []): array
    {
        $usersByRole = self::usersGroupedByRole();
        $roles = array_values(array_map(static function (array $role) use ($usersByRole): array {
            $users = $usersByRole[$role['id']] ?? [];
            $role['permission_count'] = count($role['permissions']);
            $role['user_count'] = count($users);
            $role['users'] = $users;
            $role += self::deletionState($role);

            return $role;
        }, array_values(self::roleRecords())));

        $status = (string) ($filters['status'] ?? 'all');
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        $roles = array_values(array_filter($roles, static function (array $role) use ($status, $search): bool {
            if ($status !== 'all' && $role['status'] !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $role['name'],
                $role['description'],
                implode(' ', $role['permissions']),
            ]));

            return str_contains($haystack, $search);
        }));

        usort($roles, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $roles;
    }

    public static function stats(): array
    {
        $roles = self::all();
        $users = self::users();
        $customRoles = array_filter($roles, static fn (array $role): bool => !($role['is_system'] ?? false));
        $inactiveRoles = array_filter($roles, static fn (array $role): bool => $role['status'] !== 'active');
        $activeUsers = array_filter($users, static fn (array $user): bool => ($user['status'] ?? 'active') === 'active');

        return [
            ['label' => 'Roles configured', 'value' => (string) count($roles), 'tone' => 'info'],
            ['label' => 'Admin users mapped', 'value' => (string) count($activeUsers), 'tone' => 'success'],
            ['label' => 'Custom roles', 'value' => (string) count($customRoles), 'tone' => 'warning'],
            ['label' => 'Inactive roles', 'value' => (string) count($inactiveRoles), 'tone' => 'danger'],
        ];
    }

    public static function statuses(): array
    {
        return ['active', 'inactive'];
    }

    public static function roleOptions(bool $activeOnly = false): array
    {
        $roles = self::all();

        if ($activeOnly) {
            $roles = array_values(array_filter($roles, static fn (array $role): bool => $role['status'] === 'active'));
        }

        return array_map(static function (array $role): array {
            return [
                'id' => $role['id'],
                'name' => $role['name'],
                'status' => $role['status'],
            ];
        }, $roles);
    }

    public static function find(string $id): ?array
    {
        $role = self::roleRecords()[$id] ?? null;

        if ($role === null) {
            return null;
        }

        $role = self::withForcedSuperAdminPermissions($role);
        $role['permission_count'] = count($role['permissions']);
        $role['users'] = self::usersForRole($role['id']);
        $role['permission_groups'] = self::permissionGroupsForRole($role);
        $role['user_count'] = count($role['users']);
        $role += self::deletionState($role);

        return $role;
    }

    public static function users(): array
    {
        $users = self::databaseUsers();

        if ($users === null) {
            $users = self::fallbackUsers();
        }

        usort($users, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $users;
    }

    public static function usersForRole(string $roleId): array
    {
        return array_values(array_filter(self::users(), static fn (array $user): bool => (string) $user['role_id'] === $roleId));
    }

    public static function roleIdForUser(string $userId, string $fallbackRole = 'super_admin'): string
    {
        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $statement = self::prepare($connection, 'SELECT role_id FROM user_roles WHERE user_id = ? LIMIT 1', 's', [$userId]);

            if ($statement instanceof mysqli_stmt) {
                $resolvedRoleId = null;
                $statement->bind_result($resolvedRoleId);
                $fetched = $statement->fetch();
                $statement->close();

                if ($fetched === true && trim((string) $resolvedRoleId) !== '') {
                    return (string) $resolvedRoleId;
                }
            }
        }

        $assignments = $_SESSION['role_user_assignments'] ?? [];

        if (isset($assignments[$userId])) {
            return (string) $assignments[$userId];
        }

        $user = self::baseUsers()[$userId] ?? null;
        if (is_array($user) && trim((string) ($user['role_id'] ?? '')) !== '') {
            return (string) $user['role_id'];
        }

        return $fallbackRole;
    }

    public static function permissionsForRole(string $roleId): array
    {
        if ($roleId === 'super_admin') {
            return array_keys(self::permissionOptions());
        }

        $role = self::find($roleId);

        return $role['permissions'] ?? [];
    }

    public static function roleHasPermission(string $roleId, string $permission): bool
    {
        return in_array($permission, self::permissionsForRole($roleId), true);
    }

    public static function validateRole(array $payload, ?string $roleId = null): array
    {
        $errors = [];
        $name = trim((string) ($payload['name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'active'));
        $permissions = self::normalizePermissions($payload['permissions'] ?? []);
        $existing = $roleId !== null ? self::find($roleId) : null;

        if ($name === '') {
            $errors['name'] = 'Role name is required.';
        }

        if ($description === '') {
            $errors['description'] = 'Add a short summary of what this role is allowed to do.';
        }

        if (!in_array($status, self::statuses(), true)) {
            $errors['status'] = 'Choose a valid role status.';
        }

        if ($permissions === []) {
            $errors['permissions'] = 'Choose at least one permission for this role.';
        }

        if ($existing !== null && ($existing['is_locked'] ?? false)) {
            $errors['role'] = 'This system role is locked and cannot be edited from the admin panel.';
        }

        $slug = self::slugify($name);
        foreach (self::roleRecords() as $id => $role) {
            if ($roleId !== null && $id === $roleId) {
                continue;
            }

            if ((string) $role['slug'] === $slug) {
                $errors['name'] = 'Another role already uses a similar name.';
                break;
            }
        }

        return $errors;
    }

    public static function saveRole(array $payload, ?string $roleId = null): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $existing = $roleId !== null ? self::find($roleId) : null;
        $id = $existing['id'] ?? self::nextId();
        $name = trim((string) ($payload['name'] ?? ($existing['name'] ?? '')));
        $description = trim((string) ($payload['description'] ?? ($existing['description'] ?? '')));
        $status = trim((string) ($payload['status'] ?? ($existing['status'] ?? 'active')));
        $permissions = self::normalizePermissions($payload['permissions'] ?? ($existing['permissions'] ?? []));
        $slug = self::slugify($name);
        $isSystem = (int) ($existing['is_system'] ?? 0);
        $isLocked = (int) ($existing['is_locked'] ?? 0);

        mysqli_begin_transaction($connection);

        try {
            $statement = self::prepare(
                $connection,
                'INSERT INTO roles (id, slug, name, description, status, is_system, is_locked, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name), description = VALUES(description), status = VALUES(status), updated_at = NOW()',
                'sssssii',
                [$id, $slug, $name, $description, $status, $isSystem, $isLocked]
            );

            if (!$statement instanceof mysqli_stmt) {
                throw new RuntimeException('Unable to save role record.');
            }
            $statement->close();

            $deleteStatement = self::prepare($connection, 'DELETE FROM role_permissions WHERE role_id = ?', 's', [$id]);
            if (!$deleteStatement instanceof mysqli_stmt) {
                throw new RuntimeException('Unable to reset role permissions.');
            }
            $deleteStatement->close();

            $permissionCatalog = self::permissionOptions();
            foreach ($permissions as $permission) {
                $meta = $permissionCatalog[$permission] ?? ['group' => null, 'description' => null];
                $permissionStatement = self::prepare(
                    $connection,
                    'INSERT INTO role_permissions (role_id, permission_key, group_name, description) VALUES (?, ?, ?, ?)',
                    'ssss',
                    [$id, $permission, $meta['group'] ?? null, $meta['description'] ?? null]
                );

                if (!$permissionStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to save role permissions.');
                }
                $permissionStatement->close();
            }

            mysqli_commit($connection);
        } catch (Throwable $exception) {
            mysqli_rollback($connection);
            error_log('Role save failed: ' . $exception->getMessage());

            return null;
        }

        return self::find($id);
    }

    public static function assignUserRole(string $userId, string $roleId): ?array
    {
        $user = self::findUser($userId);
        $role = self::find($roleId);

        if ($user === null || $role === null) {
            return null;
        }

        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $statement = self::prepare(
            $connection,
            'INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), assigned_at = NOW()',
            'ss',
            [$userId, $roleId]
        );

        if (!$statement instanceof mysqli_stmt) {
            return null;
        }
        $statement->close();

        if (isset($_SESSION['user']) && (string) ($_SESSION['user']['id'] ?? '') === $userId) {
            $_SESSION['user']['role'] = $roleId;
        }

        return self::findUser($userId);
    }

    public static function deleteRole(string $roleId): array
    {
        $role = self::find($roleId);

        if ($role === null) {
            return [
                'success' => false,
                'error' => 'Role not found.',
            ];
        }

        $deletion = self::deletionState($role);
        if (!($deletion['can_delete'] ?? false)) {
            return [
                'success' => false,
                'error' => (string) ($deletion['delete_error'] ?? 'This role cannot be deleted.'),
            ];
        }

        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return [
                'success' => false,
                'error' => 'Database connection is unavailable.',
            ];
        }

        $statement = self::prepare($connection, 'DELETE FROM roles WHERE id = ? LIMIT 1', 's', [$roleId]);

        if (!$statement instanceof mysqli_stmt) {
            return [
                'success' => false,
                'error' => 'Role could not be deleted from the database.',
            ];
        }

        $affectedRows = $statement->affected_rows;
        $statement->close();

        if ($affectedRows < 1) {
            return [
                'success' => false,
                'error' => 'Role could not be deleted from the database.',
            ];
        }

        return [
            'success' => true,
            'name' => $role['name'],
        ];
    }

    private static function roleRecords(): array
    {
        $roles = self::databaseRoles();

        if ($roles !== null) {
            return $roles;
        }

        return self::mergedRoles();
    }

    private static function usersGroupedByRole(): array
    {
        $grouped = [];

        foreach (self::users() as $user) {
            $grouped[(string) $user['role_id']][] = $user;
        }

        return $grouped;
    }

    private static function deletionState(array $role): array
    {
        if ((bool) ($role['is_locked'] ?? false)) {
            return [
                'can_delete' => false,
                'delete_error' => 'Locked roles cannot be deleted.',
            ];
        }

        if ((bool) ($role['is_system'] ?? false)) {
            return [
                'can_delete' => false,
                'delete_error' => 'System roles cannot be deleted.',
            ];
        }

        if ((int) ($role['user_count'] ?? 0) > 0) {
            return [
                'can_delete' => false,
                'delete_error' => 'Reassign or remove users from this role before deleting it.',
            ];
        }

        return [
            'can_delete' => true,
            'delete_error' => '',
        ];
    }

    private static function permissionGroupsForRole(array $role): array
    {
        $groups = [];
        $catalog = self::permissionGroups();
        $selected = array_fill_keys($role['permissions'], true);

        foreach ($catalog as $group => $permissions) {
            $groups[$group] = [];

            foreach ($permissions as $permission => $description) {
                if (!isset($selected[$permission])) {
                    continue;
                }

                $groups[$group][$permission] = $description;
            }
        }

        return array_filter($groups);
    }

    private static function normalizePermissions(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $allowed = array_fill_keys(array_keys(self::permissionOptions()), true);
        $permissions = [];

        foreach ($values as $permission) {
            $permission = trim((string) $permission);
            if ($permission === '' || !isset($allowed[$permission])) {
                continue;
            }
            $permissions[$permission] = $permission;
        }

        return array_values($permissions);
    }

    private static function nextId(): string
    {
        return function_exists('uuid_v4') ? uuid_v4() : self::fallbackUuidV4();
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));

        return $slug !== '' ? $slug : 'role';
    }

    private static function connection(): ?mysqli
    {
        if (!function_exists('db_connection')) {
            return null;
        }

        return db_connection();
    }

    private static function fallbackUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function prepare(mysqli $connection, string $sql, string $types = '', array $params = []): ?mysqli_stmt
    {
        $statement = mysqli_prepare($connection, $sql);

        if (!$statement instanceof mysqli_stmt) {
            return null;
        }

        if ($types !== '') {
            $bindParams = [];
            foreach ($params as $index => $value) {
                $bindParams[$index] = $value;
            }

            $references = [$types];
            foreach ($bindParams as $index => $value) {
                $references[] = &$bindParams[$index];
            }

            if (!call_user_func_array([$statement, 'bind_param'], $references)) {
                $statement->close();

                return null;
            }
        }

        if (!$statement->execute()) {
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function databaseRoles(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = mysqli_query($connection, 'SELECT id, slug, name, description, status, is_system, is_locked, created_at, updated_at FROM roles ORDER BY name');

        if ($result === false) {
            return null;
        }

        $roles = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $roles[(string) $row['id']] = [
                'id' => (string) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'status' => (string) ($row['status'] ?? 'active'),
                'is_system' => (bool) ($row['is_system'] ?? false),
                'is_locked' => (bool) ($row['is_locked'] ?? false),
                'permissions' => [],
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        mysqli_free_result($result);

        $permissionResult = mysqli_query($connection, 'SELECT role_id, permission_key FROM role_permissions ORDER BY role_id, permission_key');

        if ($permissionResult === false) {
            return null;
        }

        while ($row = mysqli_fetch_assoc($permissionResult)) {
            $roleId = (string) ($row['role_id'] ?? '');
            $permission = (string) ($row['permission_key'] ?? '');

            if ($roleId !== '' && $permission !== '' && isset($roles[$roleId])) {
                $roles[$roleId]['permissions'][] = $permission;
            }
        }
        mysqli_free_result($permissionResult);

        foreach ($roles as $id => $role) {
            $roles[$id] = self::withForcedSuperAdminPermissions($role);
        }

        return $roles;
    }

    private static function databaseUsers(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = mysqli_query(
            $connection,
            'SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.title,
                u.status,
                u.last_login_at,
                u.created_at,
                ur.role_id,
                r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             ORDER BY u.first_name, u.last_name'
        );

        if ($result === false) {
            return null;
        }

        $permissionCounts = [];
        foreach (self::roleRecords() as $role) {
            $permissionCounts[(string) $role['id']] = count($role['permissions']);
        }

        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $roleId = trim((string) ($row['role_id'] ?? ''));
            $users[] = [
                'id' => (string) $row['id'],
                'name' => self::composeName((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? '')),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'role_id' => $roleId !== '' ? $roleId : 'super_admin',
                'role_name' => (string) ($row['role_name'] ?? 'Unknown role'),
                'status' => (string) ($row['status'] ?? 'active'),
                'last_active_at' => (string) (($row['last_login_at'] ?? '') !== '' ? $row['last_login_at'] : ($row['created_at'] ?? date('Y-m-d H:i:s'))),
                'permission_count' => $permissionCounts[$roleId] ?? 0,
            ];
        }
        mysqli_free_result($result);

        return $users;
    }

    private static function withForcedSuperAdminPermissions(array $role): array
    {
        if ((string) ($role['id'] ?? '') !== 'super_admin') {
            return $role;
        }

        $role['permissions'] = array_keys(self::permissionOptions());

        return $role;
    }

    private static function findUser(string $userId): ?array
    {
        foreach (self::users() as $user) {
            if ((string) $user['id'] === $userId) {
                return $user;
            }
        }

        return null;
    }

    private static function composeName(string $firstName, string $lastName): string
    {
        $name = trim($firstName . ' ' . $lastName);

        return $name !== '' ? $name : 'Admin user';
    }

    private static function mergedRoles(): array
    {
        $roles = self::baseRoles();

        foreach ($_SESSION['role_records'] ?? [] as $id => $role) {
            $roles[$id] = $role;
        }

        return $roles;
    }

    private static function fallbackUsers(): array
    {
        $users = self::baseUsers();
        $assignments = $_SESSION['role_user_assignments'] ?? [];
        $roles = self::mergedRoles();

        foreach ($users as $id => $user) {
            if (isset($assignments[$id])) {
                $users[$id]['role_id'] = (string) $assignments[$id];
            }

            $role = $roles[(string) $users[$id]['role_id']] ?? null;
            $users[$id]['role_name'] = $role['name'] ?? 'Unknown role';
            $users[$id]['permission_count'] = count($role['permissions'] ?? []);
        }

        return array_values($users);
    }

    private static function baseRoles(): array
    {
        return [];
    }

    private static function baseUsers(): array
    {
        return [];
    }
}
