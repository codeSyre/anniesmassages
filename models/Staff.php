<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Scheduling.php';

final class Staff
{
    public static function all(array $filters = []): array
    {
        $staff = self::rawAll($filters);

        return array_map(static function (array $member): array {
            return $member + self::bookingSummary($member['id']) + self::availabilitySummary($member['id']);
        }, $staff);
    }

    public static function rawAll(array $filters = []): array
    {
        return self::filteredRecords(self::mergedRecords(), $filters);
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

        return $staff + self::bookingSummary($id) + self::availabilitySummary($id);
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
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($email !== '' && self::emailExists($email, $ignoreId)) {
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
