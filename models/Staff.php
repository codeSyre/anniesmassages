<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Scheduling.php';

final class Staff
{
    public static function all(array $filters = []): array
    {
        $staff = self::rawAll($filters);

        return array_map(static function (array $member): array {
            return ($member + self::bookingSummary($member['id']) + self::availabilitySummary($member['id'])) + self::lifecycleState($member['id']);
        }, $staff);
    }

    public static function rawAll(array $filters = []): array
    {
        return self::filteredRecords(self::mergedRecords(), $filters);
    }

    public static function therapists(array $filters = []): array
    {
        $staff = self::rawTherapists($filters);

        return array_map(static function (array $member): array {
            return ($member + self::bookingSummary($member['id']) + self::availabilitySummary($member['id'])) + self::lifecycleState($member['id']);
        }, $staff);
    }

    public static function rawTherapists(array $filters = []): array
    {
        return array_values(array_filter(
            self::rawAll($filters),
            static fn (array $member): bool => self::isTherapistRole((string) ($member['role_type'] ?? ''))
        ));
    }

    public static function isTherapistRole(string $roleType): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $roleType), '-'));

        return $normalized === 'therapist' || str_contains($normalized, 'therapist');
    }

    public static function stats(): array
    {
        $staff = self::all();
        $active = array_filter($staff, static fn (array $member): bool => $member['status'] === 'active');
        $onLeave = array_filter($staff, static fn (array $member): bool => $member['status'] === 'on_leave');
        $earnings = array_sum(array_map(static fn (array $member): float => (float) $member['completed_value'], $staff));

        return [
            ['label' => 'Therapists on roster', 'value' => (string) count($staff), 'tone' => 'info'],
            ['label' => 'Active today', 'value' => (string) count($active), 'tone' => 'success'],
            ['label' => 'On leave', 'value' => (string) count($onLeave), 'tone' => 'warning'],
            ['label' => 'Completed-booking value', 'value' => format_money($earnings), 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        $staff = self::mergedRecords()[$id] ?? null;

        if ($staff === null) {
            return null;
        }

        return ($staff + self::bookingSummary($id) + self::availabilitySummary($id)) + self::lifecycleState($id);
    }

    public static function bookings(string $staffId): array
    {
        $bookings = array_values(array_filter(Booking::all(), static function (array $booking) use ($staffId): bool {
            return ($booking['staff']['id'] ?? '') === $staffId;
        }));

        usort($bookings, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

        return $bookings;
    }

    public static function validate(array $payload, ?string $ignoreId = null): array
    {
        $errors = [];
        $normalized = self::normalizePayload($payload);

        foreach (['first_name', 'last_name', 'specialty', 'role_type', 'status', 'salary_structure'] as $field) {
            if (trim((string) ($normalized[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        $email = trim((string) ($normalized['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Email address is required so this staff member can log in.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (self::emailExists($email, $ignoreId)) {
            $errors['email'] = 'Another staff profile already uses this email address.';
        }

        if (($normalized['commission_rate'] ?? '') !== '' && (!is_numeric((string) $normalized['commission_rate']) || (float) $normalized['commission_rate'] < 0)) {
            $errors['commission_rate'] = 'Commission rate must be zero or greater.';
        }

        if (($normalized['fixed_pay'] ?? '') !== '' && (!is_numeric((string) $normalized['fixed_pay']) || (float) $normalized['fixed_pay'] < 0)) {
            $errors['fixed_pay'] = 'Fixed pay must be zero or greater.';
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): ?array
    {
        $existing = $id !== null ? self::find($id) : null;
        $member = self::normalizePayload($payload, $existing);
        $staffId = $existing['id'] ?? self::nextId();
        $member['id'] = $staffId;

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $statement = self::prepare(
                $connection,
                'INSERT INTO staff (
                    id, first_name, last_name, specialty, role_type, status, phone, email,
                    address_line_1, address_line_2, city_town, country, profile_picture_path,
                    bio, capacity_label, salary_structure, commission_rate, fixed_pay, color_hex,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    specialty = VALUES(specialty),
                    role_type = VALUES(role_type),
                    status = VALUES(status),
                    phone = VALUES(phone),
                    email = VALUES(email),
                    address_line_1 = VALUES(address_line_1),
                    address_line_2 = VALUES(address_line_2),
                    city_town = VALUES(city_town),
                    country = VALUES(country),
                    profile_picture_path = VALUES(profile_picture_path),
                    bio = VALUES(bio),
                    capacity_label = VALUES(capacity_label),
                    salary_structure = VALUES(salary_structure),
                    commission_rate = VALUES(commission_rate),
                    fixed_pay = VALUES(fixed_pay),
                    color_hex = VALUES(color_hex),
                    updated_at = NOW()',
                'ssssssssssssssssdds',
                [
                    $staffId,
                    $member['first_name'],
                    $member['last_name'],
                    $member['specialty'],
                    $member['role_type'],
                    $member['status'],
                    $member['phone'],
                    $member['email'],
                    $member['address_line_1'],
                    $member['address_line_2'],
                    $member['city_town'],
                    $member['country'],
                    $member['profile_picture_path'],
                    $member['bio'],
                    $member['capacity'],
                    $member['salary_structure'],
                    (float) $member['commission_rate'],
                    (float) $member['fixed_pay'],
                    $member['color'],
                ]
            );

            if (!$statement instanceof mysqli_stmt) {
                error_log('Staff save failed: unable to prepare statement.');

                return null;
            }

            $statement->close();

             if (!self::syncLoginAccount($connection, $member)) {
                error_log('Staff save failed: unable to sync login account.');

                return null;
            }

            return self::find($staffId);
        }

        if (db_configured()) {
            return null;
        }

        $records = $_SESSION['staff_records'] ?? [];
        $records[$staffId] = $member;
        $_SESSION['staff_records'] = $records;

        return self::find($staffId);
    }

    public static function suspend(string $staffId): array
    {
        $member = self::find($staffId);

        if ($member === null) {
            return [
                'success' => false,
                'error' => 'Staff member not found.',
            ];
        }

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $staffStatement = self::prepare(
                $connection,
                'UPDATE staff SET status = ?, updated_at = NOW() WHERE id = ?',
                'ss',
                ['suspended', $staffId]
            );

            if (!$staffStatement instanceof mysqli_stmt) {
                return [
                    'success' => false,
                    'error' => 'Unable to suspend this staff member.',
                ];
            }
            $staffStatement->close();

            $userStatement = self::prepare(
                $connection,
                'UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?',
                'ss',
                ['suspended', $staffId]
            );

            if ($userStatement instanceof mysqli_stmt) {
                $userStatement->close();
            }
        } else {
            $records = $_SESSION['staff_records'] ?? [];
            $existing = $records[$staffId] ?? self::baseRecords()[$staffId] ?? null;

            if (!is_array($existing)) {
                return [
                    'success' => false,
                    'error' => 'Staff member not found.',
                ];
            }

            $existing['status'] = 'suspended';
            $records[$staffId] = $existing;
            $_SESSION['staff_records'] = $records;
        }

        return [
            'success' => true,
            'name' => $member['name'],
        ];
    }

    public static function delete(string $staffId): array
    {
        $member = self::find($staffId);

        if ($member === null) {
            return [
                'success' => false,
                'error' => 'Staff member not found.',
            ];
        }

        $lifecycle = self::lifecycleState($staffId);

        if (!($lifecycle['can_delete'] ?? false)) {
            return [
                'success' => false,
                'error' => (string) ($lifecycle['delete_error'] ?? 'This staff member cannot be deleted.'),
            ];
        }

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $userStatement = self::prepare($connection, 'DELETE FROM users WHERE id = ?', 's', [$staffId]);

            if ($userStatement instanceof mysqli_stmt) {
                $userStatement->close();
            }

            $staffStatement = self::prepare($connection, 'DELETE FROM staff WHERE id = ? LIMIT 1', 's', [$staffId]);

            if (!$staffStatement instanceof mysqli_stmt) {
                return [
                    'success' => false,
                    'error' => 'Unable to delete this staff member.',
                ];
            }

            $affectedRows = $staffStatement->affected_rows;
            $staffStatement->close();

            if ($affectedRows < 1) {
                return [
                    'success' => false,
                    'error' => 'Unable to delete this staff member.',
                ];
            }
        } else {
            $records = $_SESSION['staff_records'] ?? [];
            unset($records[$staffId]);
            $_SESSION['staff_records'] = $records;
        }

        return [
            'success' => true,
            'name' => $member['name'],
        ];
    }

    private static function bookingSummary(string $staffId): array
    {
        $bookings = self::bookings($staffId);
        $today = date('Y-m-d');
        $todayBookingCount = 0;
        $upcomingCount = 0;
        $completedCount = 0;
        $completedValue = 0.0;
        $commissionable = 0.0;

        foreach ($bookings as $booking) {
            if ($booking['date'] === $today && !in_array($booking['status'], ['cancelled'], true)) {
                $todayBookingCount++;
            }

            if ($booking['date'] >= $today && !in_array($booking['status'], ['cancelled'], true)) {
                $upcomingCount++;
            }

            if ($booking['status'] === 'completed') {
                $completedCount++;
                $completedValue += (float) $booking['amount_paid'];
                $commissionable += (float) $booking['amount_total'];
            }
        }

        return [
            'booking_count' => count($bookings),
            'today_booking_count' => $todayBookingCount,
            'upcoming_count' => $upcomingCount,
            'completed_count' => $completedCount,
            'completed_value' => $completedValue,
            'commissionable_value' => $commissionable,
        ];
    }

    private static function availabilitySummary(string $staffId): array
    {
        $profiles = Scheduling::weeklyAvailabilityProfiles();
        $days = $profiles[$staffId]['days'] ?? [];
        $enabledDays = array_filter($days, static fn (array $day): bool => (bool) $day['enabled']);
        $todayKey = strtolower(date('l'));
        $todayWindow = $days[$todayKey] ?? ['enabled' => false, 'start' => '', 'end' => ''];

        return [
            'enabled_days' => count($enabledDays),
            'today_window' => (bool) $todayWindow['enabled'] ? ($todayWindow['start'] . ' - ' . $todayWindow['end']) : 'Unavailable',
        ];
    }

    private static function lifecycleState(string $staffId): array
    {
        $bookings = self::bookings($staffId);

        if ($bookings !== []) {
            return [
                'can_suspend' => true,
                'can_delete' => false,
                'delete_error' => 'This staff member has booking history and cannot be deleted.',
            ];
        }

        return [
            'can_suspend' => true,
            'can_delete' => true,
            'delete_error' => '',
        ];
    }

    private static function filteredRecords(array $records, array $filters = []): array
    {
        $staff = array_values($records);
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? 'all');

        if ($search !== '') {
            $staff = array_values(array_filter($staff, static function (array $member) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $member['name'],
                    $member['first_name'],
                    $member['last_name'],
                    $member['specialty'],
                    $member['role_type'],
                    $member['phone'],
                    $member['email'],
                    $member['bio'],
                    $member['city_town'],
                    $member['country'],
                ]));

                return str_contains($haystack, $search);
            }));
        }

        if ($status !== 'all') {
            $staff = array_values(array_filter($staff, static fn (array $member): bool => $member['status'] === $status));
        }

        usort($staff, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $staff;
    }

    private static function mergedRecords(): array
    {
        $databaseRecords = self::databaseRecords();

        if ($databaseRecords !== null) {
            return $databaseRecords;
        }

        $records = self::baseRecords();

        foreach ($_SESSION['staff_records'] ?? [] as $id => $member) {
            $records[$id] = self::normalizePayload($member, ['id' => $id]);
        }

        return $records;
    }

    private static function databaseRecords(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = $connection->query('SELECT * FROM staff');

        if (!$result instanceof mysqli_result) {
            error_log('Unable to fetch staff records from database: ' . $connection->error);

            return [];
        }

        $records = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $member = self::mapDatabaseRow($row);
            $records[$member['id']] = $member;
        }

        $result->free();

        return $records;
    }

    private static function mapDatabaseRow(array $row): array
    {
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        return [
            'id' => (string) ($row['id'] ?? ''),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'specialty' => trim((string) ($row['specialty'] ?? '')),
            'role_type' => trim((string) ($row['role_type'] ?? 'therapist')),
            'status' => trim((string) ($row['status'] ?? 'active')),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'address_line_1' => trim((string) ($row['address_line_1'] ?? '')),
            'address_line_2' => trim((string) ($row['address_line_2'] ?? '')),
            'city_town' => trim((string) ($row['city_town'] ?? '')),
            'country' => trim((string) ($row['country'] ?? '')),
            'profile_picture_path' => trim((string) ($row['profile_picture_path'] ?? '')),
            'bio' => trim((string) ($row['bio'] ?? '')),
            'capacity' => trim((string) ($row['capacity_label'] ?? '4 sessions/day')),
            'salary_structure' => trim((string) ($row['salary_structure'] ?? 'commission')),
            'commission_rate' => round((float) ($row['commission_rate'] ?? 0), 2),
            'fixed_pay' => round((float) ($row['fixed_pay'] ?? 0), 2),
            'color' => trim((string) ($row['color_hex'] ?? 'cyan')) ?: 'cyan',
        ];
    }

    private static function normalizePayload(array $payload, ?array $existing = null): array
    {
        $firstName = trim((string) ($payload['first_name'] ?? ($existing['first_name'] ?? '')));
        $lastName = trim((string) ($payload['last_name'] ?? ($existing['last_name'] ?? '')));
        $fullName = trim((string) ($payload['name'] ?? ($existing['name'] ?? '')));

        if (($firstName === '' || $lastName === '') && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];

            if ($firstName === '' && $parts !== []) {
                $firstName = (string) array_shift($parts);
            }

            if ($lastName === '' && $parts !== []) {
                $lastName = trim(implode(' ', $parts));
            }
        }

        $name = trim($firstName . ' ' . $lastName);

        return [
            'id' => (string) ($payload['id'] ?? ($existing['id'] ?? '')),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'specialty' => trim((string) ($payload['specialty'] ?? ($existing['specialty'] ?? ''))),
            'role_type' => trim((string) ($payload['role_type'] ?? ($existing['role_type'] ?? 'therapist'))),
            'status' => trim((string) ($payload['status'] ?? ($existing['status'] ?? 'active'))),
            'phone' => trim((string) ($payload['phone'] ?? ($existing['phone'] ?? ''))),
            'email' => trim((string) ($payload['email'] ?? ($existing['email'] ?? ''))),
            'address_line_1' => trim((string) ($payload['address_line_1'] ?? ($existing['address_line_1'] ?? ''))),
            'address_line_2' => trim((string) ($payload['address_line_2'] ?? ($existing['address_line_2'] ?? ''))),
            'city_town' => trim((string) ($payload['city_town'] ?? ($existing['city_town'] ?? ''))),
            'country' => trim((string) ($payload['country'] ?? ($existing['country'] ?? ''))),
            'profile_picture_path' => trim((string) ($payload['profile_picture_path'] ?? ($existing['profile_picture_path'] ?? ''))),
            'bio' => trim((string) ($payload['bio'] ?? ($existing['bio'] ?? ''))),
            'capacity' => trim((string) ($payload['capacity'] ?? ($existing['capacity'] ?? '4 sessions/day'))),
            'salary_structure' => trim((string) ($payload['salary_structure'] ?? ($existing['salary_structure'] ?? 'commission'))),
            'commission_rate' => round((float) ($payload['commission_rate'] ?? ($existing['commission_rate'] ?? 25)), 2),
            'fixed_pay' => round((float) ($payload['fixed_pay'] ?? ($existing['fixed_pay'] ?? 0)), 2),
            'color' => trim((string) ($payload['color'] ?? ($existing['color'] ?? 'cyan'))) ?: 'cyan',
        ];
    }

    private static function emailExists(string $email, ?string $ignoreId = null): bool
    {
        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $sql = 'SELECT id FROM staff WHERE email = ?';
            $params = [$email];
            $types = 's';

            if ($ignoreId !== null && $ignoreId !== '') {
                $sql .= ' AND id != ?';
                $params[] = $ignoreId;
                $types .= 's';
            }

            $statement = self::prepare($connection, $sql . ' LIMIT 1', $types, $params);

            if ($statement instanceof mysqli_stmt) {
                $statement->store_result();
                $exists = $statement->num_rows > 0;
                $statement->close();

                return $exists;
            }

            return false;
        }

        foreach (self::mergedRecords() as $member) {
            if (strcasecmp((string) ($member['email'] ?? ''), $email) !== 0) {
                continue;
            }

            if ($ignoreId !== null && $ignoreId !== '' && (string) $member['id'] === $ignoreId) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function nextId(): string
    {
        if (function_exists('uuid_v4')) {
            return uuid_v4();
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    public static function defaultLoginPassword(): string
    {
        return (string) (function_exists('app_config') ? app_config('default_member_password', 'Password@123%') : 'Password@123%');
    }

    private static function syncLoginAccount(mysqli $connection, array $member): bool
    {
        $passwordHash = self::existingPasswordHash($connection, (string) $member['id']);

        if ($passwordHash === null || trim($passwordHash) === '') {
            $passwordHash = password_hash(self::defaultLoginPassword(), PASSWORD_DEFAULT);
        }

        $statement = self::prepare(
            $connection,
            'INSERT INTO users (
                id, first_name, last_name, email, password_hash, title, phone,
                address_line_1, address_line_2, city_town, country, profile_picture_path,
                timezone, bio, status,
                notification_booking_updates, notification_payment_updates, notification_system_alerts,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                email = VALUES(email),
                password_hash = VALUES(password_hash),
                title = VALUES(title),
                phone = VALUES(phone),
                address_line_1 = VALUES(address_line_1),
                address_line_2 = VALUES(address_line_2),
                city_town = VALUES(city_town),
                country = VALUES(country),
                profile_picture_path = VALUES(profile_picture_path),
                timezone = VALUES(timezone),
                bio = VALUES(bio),
                status = VALUES(status),
                updated_at = NOW()',
            'sssssssssssssss',
            [
                (string) $member['id'],
                (string) $member['first_name'],
                (string) $member['last_name'],
                (string) $member['email'],
                $passwordHash,
                (string) $member['role_type'],
                (string) $member['phone'],
                (string) $member['address_line_1'],
                (string) $member['address_line_2'],
                (string) $member['city_town'],
                (string) $member['country'],
                (string) $member['profile_picture_path'],
                (string) (function_exists('app_config') ? app_config('timezone', 'Africa/Harare') : 'Africa/Harare'),
                (string) $member['bio'],
                (string) $member['status'],
            ]
        );

        if (!$statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->close();

        $roleId = self::resolvableRoleId($connection, (string) $member['role_type']);

        if ($roleId === null) {
            return true;
        }

        $roleStatement = self::prepare(
            $connection,
            'INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), assigned_at = NOW()',
            'ss',
            [(string) $member['id'], $roleId]
        );

        if (!$roleStatement instanceof mysqli_stmt) {
            return false;
        }

        $roleStatement->close();

        return true;
    }

    private static function existingPasswordHash(mysqli $connection, string $userId): ?string
    {
        $statement = self::prepare($connection, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1', 's', [$userId]);

        if (!$statement instanceof mysqli_stmt) {
            return null;
        }

        $passwordHash = null;
        $statement->bind_result($passwordHash);
        $statement->fetch();
        $statement->close();

        return is_string($passwordHash) ? $passwordHash : null;
    }

    private static function resolvableRoleId(mysqli $connection, string $roleType): ?string
    {
        $roleType = trim($roleType);

        if ($roleType !== '') {
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $roleType), '-'));
            $statement = self::prepare(
                $connection,
                'SELECT id FROM roles WHERE slug = ? OR LOWER(name) = LOWER(?) LIMIT 1',
                'ss',
                [$slug, $roleType]
            );

            if ($statement instanceof mysqli_stmt) {
                $roleId = null;
                $statement->bind_result($roleId);
                $fetched = $statement->fetch();
                $statement->close();

                if ($fetched === true && is_string($roleId) && trim($roleId) !== '') {
                    return $roleId;
                }
            }
        }

        return null;
    }

    private static function prepare(mysqli $connection, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $statement = $connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('Staff statement prepare failed: ' . $connection->error);

            return null;
        }

        if ($params !== []) {
            $statement->bind_param($types, ...$params);
        }

        if (!$statement->execute()) {
            error_log('Staff statement execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function baseRecords(): array
    {
        return [
            'stf-tariro' => [
                'id' => 'stf-tariro',
                'first_name' => 'Tariro',
                'last_name' => 'Moyo',
                'name' => 'Tariro Moyo',
                'specialty' => 'Recovery and sports',
                'role_type' => 'therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3001',
                'email' => 'tariro@anniesmassages.test',
                'address_line_1' => '',
                'address_line_2' => '',
                'city_town' => 'Harare',
                'country' => 'Zimbabwe',
                'profile_picture_path' => '',
                'bio' => 'Focused on athletic recovery, post-travel reset work, and structured pressure progression.',
                'capacity' => '4 sessions/day',
                'salary_structure' => 'commission',
                'commission_rate' => 28.0,
                'fixed_pay' => 0.0,
                'color' => 'cyan',
            ],
            'stf-amanda' => [
                'id' => 'stf-amanda',
                'first_name' => 'Amanda',
                'last_name' => 'Sibanda',
                'name' => 'Amanda Sibanda',
                'specialty' => 'Deep tissue and posture work',
                'role_type' => 'senior therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3002',
                'email' => 'amanda@anniesmassages.test',
                'address_line_1' => '',
                'address_line_2' => '',
                'city_town' => 'Harare',
                'country' => 'Zimbabwe',
                'profile_picture_path' => '',
                'bio' => 'Handles high-intensity deep tissue sessions and clients needing corrective bodywork.',
                'capacity' => '5 sessions/day',
                'salary_structure' => 'hybrid',
                'commission_rate' => 22.0,
                'fixed_pay' => 110.0,
                'color' => 'teal',
            ],
            'stf-shamiso' => [
                'id' => 'stf-shamiso',
                'first_name' => 'Shamiso',
                'last_name' => 'Chuma',
                'name' => 'Shamiso Chuma',
                'specialty' => 'Relaxation and hot stone',
                'role_type' => 'therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3003',
                'email' => 'shamiso@anniesmassages.test',
                'address_line_1' => '',
                'address_line_2' => '',
                'city_town' => 'Harare',
                'country' => 'Zimbabwe',
                'profile_picture_path' => '',
                'bio' => 'Leads the premium warm-stone experience and slower-paced therapeutic relaxation sessions.',
                'capacity' => '4 sessions/day',
                'salary_structure' => 'commission',
                'commission_rate' => 30.0,
                'fixed_pay' => 0.0,
                'color' => 'amber',
            ],
            'stf-kuda' => [
                'id' => 'stf-kuda',
                'first_name' => 'Kuda',
                'last_name' => 'Mlambo',
                'name' => 'Kuda Mlambo',
                'specialty' => 'Aromatherapy and mobile visits',
                'role_type' => 'therapist',
                'status' => 'on_leave',
                'phone' => '+263 77 200 3004',
                'email' => 'kuda@anniesmassages.test',
                'address_line_1' => '',
                'address_line_2' => '',
                'city_town' => 'Harare',
                'country' => 'Zimbabwe',
                'profile_picture_path' => '',
                'bio' => 'Supports lighter aromatherapy sessions and offsite wellness bookings when scheduled.',
                'capacity' => '3 sessions/day',
                'salary_structure' => 'fixed',
                'commission_rate' => 0.0,
                'fixed_pay' => 95.0,
                'color' => 'slate',
            ],
        ];
    }
}
