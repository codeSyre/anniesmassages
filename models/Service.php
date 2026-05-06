<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Service
{
    public static function categories(): array
    {
        return [
            'Massage',
            'Therapeutic',
            'Signature',
            'Wellness',
            'Experience',
        ];
    }

    public static function roomOptions(): array
    {
        return [
            'Reset room',
            'Therapy room',
            'Stone suite',
            'Calm room',
            'Couples suite',
            'Studio',
        ];
    }

    public static function all(array $filters = []): array
    {
        $services = self::rawAll($filters);

        return array_map(static function (array $service): array {
            return $service + self::usageSummary($service['id']) + self::lifecycleSummary($service['id']);
        }, $services);
    }

    public static function rawAll(array $filters = []): array
    {
        return self::filteredRecords(self::mergedRecords(), $filters);
    }

    public static function activeOptions(): array
    {
        return array_values(array_filter(self::rawAll(), static fn (array $service): bool => (bool) $service['active']));
    }

    public static function addonOptions(): array
    {
        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $result = $connection->query(
                "SELECT DISTINCT addon_name
                 FROM service_addons
                 WHERE TRIM(COALESCE(addon_name, '')) <> ''
                 ORDER BY addon_name ASC"
            );

            if ($result instanceof mysqli_result) {
                $addons = [];

                while ($row = $result->fetch_assoc()) {
                    $addonName = trim((string) ($row['addon_name'] ?? ''));

                    if ($addonName !== '') {
                        $addons[] = $addonName;
                    }
                }

                $result->free();

                return array_values(array_unique($addons));
            }
        }

        $addons = [];

        foreach (self::rawAll() as $service) {
            foreach (($service['addons'] ?? []) as $addon) {
                $addonName = trim((string) $addon);

                if ($addonName !== '') {
                    $addons[] = $addonName;
                }
            }
        }

        $addons = array_values(array_unique($addons));
        sort($addons, SORT_NATURAL | SORT_FLAG_CASE);

        return $addons;
    }

    public static function stats(): array
    {
        $services = self::all();
        $active = array_filter($services, static fn (array $service): bool => (bool) $service['active']);
        $inactive = array_filter($services, static fn (array $service): bool => !(bool) $service['active']);
        $averagePrice = count($services) > 0
            ? array_sum(array_map(static fn (array $service): float => (float) $service['price'], $services)) / count($services)
            : 0.0;
        $averageDuration = count($services) > 0
            ? array_sum(array_map(static fn (array $service): int => (int) $service['duration'], $services)) / count($services)
            : 0;

        return [
            ['label' => 'Services on menu', 'value' => (string) count($services), 'tone' => 'info'],
            ['label' => 'Active services', 'value' => (string) count($active), 'tone' => 'success'],
            ['label' => 'Inactive services', 'value' => (string) count($inactive), 'tone' => 'warning'],
            ['label' => 'Average service value', 'value' => format_money($averagePrice) . ' · ' . (string) round($averageDuration) . ' min', 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        $service = self::mergedRecords()[$id] ?? null;

        if ($service === null) {
            return null;
        }

        return $service + self::usageSummary($id) + self::lifecycleSummary($id);
    }

    public static function bookings(string $serviceId): array
    {
        $bookings = array_values(array_filter(Booking::all(), static function (array $booking) use ($serviceId): bool {
            return ($booking['service']['id'] ?? '') === $serviceId;
        }));

        usort($bookings, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

        return $bookings;
    }

    public static function validate(array $payload): array
    {
        $errors = [];
        $normalized = self::normalizePayload($payload);

        foreach (['name', 'category', 'price', 'duration', 'room'] as $field) {
            if (trim((string) ($normalized[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (!in_array((string) ($normalized['category'] ?? ''), self::categories(), true)) {
            $errors['category'] = 'Choose a valid service category.';
        }

        if (!in_array((string) ($normalized['room'] ?? ''), self::roomOptions(), true)) {
            $errors['room'] = 'Choose a valid room / setup option.';
        }

        if (!is_numeric((string) ($normalized['price'] ?? ''))) {
            $errors['price'] = 'Price must be a valid number.';
        }

        if (!is_numeric((string) ($normalized['duration'] ?? '')) || (int) ($normalized['duration'] ?? 0) <= 0) {
            $errors['duration'] = 'Duration must be a positive number.';
        }

        if (($normalized['buffer'] ?? '') !== '' && (!is_numeric((string) $normalized['buffer']) || (int) $normalized['buffer'] < 0)) {
            $errors['buffer'] = 'Buffer must be zero or greater.';
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): ?array
    {
        $existing = $id !== null ? self::find($id) : null;
        $service = self::normalizePayload($payload, $existing);
        $serviceId = $existing['id'] ?? self::nextId();
        $service['id'] = $serviceId;

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            mysqli_begin_transaction($connection);

            try {
                $statement = self::prepare(
                    $connection,
                    'INSERT INTO services (
                        id, name, category, description, price, duration_minutes, buffer_minutes, room, is_active,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        category = VALUES(category),
                        description = VALUES(description),
                        price = VALUES(price),
                        duration_minutes = VALUES(duration_minutes),
                        buffer_minutes = VALUES(buffer_minutes),
                        room = VALUES(room),
                        is_active = VALUES(is_active),
                        updated_at = NOW()',
                    'ssssdissi',
                    [
                        $serviceId,
                        $service['name'],
                        $service['category'],
                        $service['description'],
                        (float) $service['price'],
                        (int) $service['duration'],
                        (int) $service['buffer'],
                        $service['room'],
                        $service['active'] ? 1 : 0,
                    ]
                );

                if (!$statement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to save service.');
                }
                $statement->close();

                $deleteStatement = self::prepare($connection, 'DELETE FROM service_addons WHERE service_id = ?', 's', [$serviceId]);

                if (!$deleteStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to reset service add-ons.');
                }
                $deleteStatement->close();

                foreach (array_values($service['addons']) as $index => $addonName) {
                    $addonStatement = self::prepare(
                        $connection,
                        'INSERT INTO service_addons (id, service_id, addon_name, addon_price, display_order) VALUES (?, ?, ?, NULL, ?)',
                        'sssi',
                        [self::nextId(), $serviceId, $addonName, $index + 1]
                    );

                    if (!$addonStatement instanceof mysqli_stmt) {
                        throw new RuntimeException('Unable to save service add-ons.');
                    }
                    $addonStatement->close();
                }

                mysqli_commit($connection);
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Service save failed: ' . $exception->getMessage());

                return null;
            }

            return self::find($serviceId);
        }

        if (function_exists('db_configured') && db_configured()) {
            return null;
        }

        $records = $_SESSION['service_records'] ?? [];
        $records[$serviceId] = $service;
        $_SESSION['service_records'] = $records;

        return self::find($serviceId);
    }

    public static function delete(string $serviceId): array
    {
        $service = self::find($serviceId);

        if (!is_array($service)) {
            return ['success' => false, 'error' => 'Service not found.'];
        }

        if (!(bool) ($service['can_delete'] ?? false)) {
            return ['success' => false, 'error' => (string) ($service['delete_error'] ?? 'This service cannot be deleted right now.')];
        }

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            mysqli_begin_transaction($connection);

            try {
                $addonsStatement = self::prepare($connection, 'DELETE FROM service_addons WHERE service_id = ?', 's', [$serviceId]);

                if (!$addonsStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to remove service add-ons.');
                }
                $addonsStatement->close();

                $serviceStatement = self::prepare($connection, 'DELETE FROM services WHERE id = ?', 's', [$serviceId]);

                if (!$serviceStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to delete service.');
                }

                $deletedRows = $serviceStatement->affected_rows;
                $serviceStatement->close();

                if ($deletedRows < 1) {
                    throw new RuntimeException('Service could not be deleted.');
                }

                mysqli_commit($connection);

                return ['success' => true, 'name' => (string) $service['name']];
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Service delete failed: ' . $exception->getMessage());

                return ['success' => false, 'error' => 'We could not delete this service from the database.'];
            }
        }

        if (function_exists('db_configured') && db_configured()) {
            return ['success' => false, 'error' => 'The database is currently unavailable.'];
        }

        $records = $_SESSION['service_records'] ?? [];
        unset($records[$serviceId]);
        $_SESSION['service_records'] = $records;

        return ['success' => true, 'name' => (string) $service['name']];
    }

    public static function freeze(string $serviceId): array
    {
        $service = self::find($serviceId);

        if (!is_array($service)) {
            return ['success' => false, 'error' => 'Service not found.'];
        }

        if (!(bool) ($service['active'] ?? false)) {
            return ['success' => false, 'error' => 'This service is already frozen.'];
        }

        $saved = self::save([
            'name' => $service['name'],
            'category' => $service['category'],
            'description' => $service['description'],
            'price' => (string) $service['price'],
            'duration' => (string) $service['duration'],
            'buffer' => (string) $service['buffer'],
            'room' => $service['room'],
            'addons' => implode(', ', $service['addons']),
            'active' => '0',
        ], $serviceId);

        if (!is_array($saved) || trim((string) ($saved['id'] ?? '')) === '') {
            return ['success' => false, 'error' => 'We could not freeze this service in the database.'];
        }

        return ['success' => true, 'name' => (string) $service['name']];
    }

    public static function search(string $query): array
    {
        return array_slice(self::all(['search' => $query]), 0, 8);
    }

    private static function usageSummary(string $serviceId): array
    {
        $bookings = self::bookings($serviceId);
        $today = date('Y-m-d');
        $upcoming = 0;
        $completed = 0;
        $revenue = 0.0;

        foreach ($bookings as $booking) {
            if ($booking['date'] >= $today && !in_array($booking['status'], ['cancelled'], true)) {
                $upcoming++;
            }

            if ($booking['status'] === 'completed') {
                $completed++;
            }

            $revenue += (float) $booking['amount_paid'];
        }

        return [
            'booking_count' => count($bookings),
            'upcoming_count' => $upcoming,
            'completed_count' => $completed,
            'revenue' => $revenue,
        ];
    }

    private static function lifecycleSummary(string $serviceId): array
    {
        $linkedBookingCount = self::linkedBookingCount($serviceId);

        if ($linkedBookingCount > 0) {
            return [
                'can_delete' => false,
                'delete_error' => 'This service cannot be deleted because it is linked to existing bookings.',
            ];
        }

        return [
            'can_delete' => true,
            'delete_error' => null,
        ];
    }

    private static function linkedBookingCount(string $serviceId): int
    {
        $count = count(self::bookings($serviceId));
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return $count;
        }

        $statement = $connection->prepare('SELECT COUNT(*) AS booking_count FROM bookings WHERE service_id = ?');

        if (!$statement instanceof mysqli_stmt) {
            error_log('Service booking count prepare failed: ' . $connection->error);

            return $count;
        }

        $statement->bind_param('s', $serviceId);

        if (!$statement->execute()) {
            error_log('Service booking count execute failed: ' . $statement->error);
            $statement->close();

            return $count;
        }

        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $statement->close();

        return max($count, (int) ($row['booking_count'] ?? 0));
    }

    private static function filteredRecords(array $records, array $filters = []): array
    {
        $services = array_values($records);
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? 'all');

        if ($search !== '') {
            $services = array_values(array_filter($services, static function (array $service) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $service['name'],
                    $service['category'],
                    $service['description'],
                    $service['room'],
                    implode(' ', $service['addons']),
                ]));

                return str_contains($haystack, $search);
            }));
        }

        if ($status !== 'all') {
            $isActive = $status === 'active';
            $services = array_values(array_filter($services, static fn (array $service): bool => (bool) $service['active'] === $isActive));
        }

        usort($services, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $services;
    }

    private static function mergedRecords(): array
    {
        $databaseRecords = self::databaseRecords();

        if ($databaseRecords !== null) {
            return $databaseRecords;
        }

        $records = self::baseRecords();

        foreach ($_SESSION['service_records'] ?? [] as $id => $service) {
            $records[$id] = self::normalizePayload($service, ['id' => $id]);
        }

        return $records;
    }

    private static function databaseRecords(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = $connection->query('SELECT * FROM services');

        if (!$result instanceof mysqli_result) {
            error_log('Unable to fetch service records from database: ' . $connection->error);

            return [];
        }

        $records = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $service = self::mapDatabaseRow($row);
            $records[$service['id']] = $service;
        }
        $result->free();

        $addonResult = $connection->query('SELECT service_id, addon_name, display_order FROM service_addons ORDER BY service_id, display_order, addon_name');

        if ($addonResult instanceof mysqli_result) {
            while ($row = $addonResult->fetch_assoc()) {
                $serviceId = (string) ($row['service_id'] ?? '');
                $addonName = trim((string) ($row['addon_name'] ?? ''));

                if ($serviceId !== '' && $addonName !== '' && isset($records[$serviceId])) {
                    $records[$serviceId]['addons'][] = $addonName;
                }
            }
            $addonResult->free();
        }

        return $records;
    }

    private static function mapDatabaseRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => trim((string) ($row['name'] ?? '')),
            'category' => trim((string) ($row['category'] ?? 'Massage')),
            'description' => trim((string) ($row['description'] ?? '')),
            'price' => round((float) ($row['price'] ?? 0), 2),
            'duration' => (int) ($row['duration_minutes'] ?? 60),
            'buffer' => (int) ($row['buffer_minutes'] ?? 15),
            'room' => trim((string) ($row['room'] ?? 'Studio')),
            'addons' => [],
            'active' => (bool) ($row['is_active'] ?? true),
        ];
    }

    private static function normalizePayload(array $payload, ?array $existing = null): array
    {
        $category = trim((string) ($payload['category'] ?? ($existing['category'] ?? 'Massage')));

        if (!in_array($category, self::categories(), true)) {
            $category = 'Massage';
        }

        $room = trim((string) ($payload['room'] ?? ($existing['room'] ?? 'Studio')));

        if (!in_array($room, self::roomOptions(), true)) {
            $room = 'Studio';
        }

        return [
            'id' => (string) ($payload['id'] ?? ($existing['id'] ?? '')),
            'name' => trim((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'category' => $category,
            'description' => trim((string) ($payload['description'] ?? ($existing['description'] ?? ''))),
            'price' => round((float) ($payload['price'] ?? ($existing['price'] ?? 0)), 2),
            'duration' => (int) ($payload['duration'] ?? ($existing['duration'] ?? 60)),
            'buffer' => (int) ($payload['buffer'] ?? ($existing['buffer'] ?? 15)),
            'room' => $room,
            'addons' => self::normalizeAddons((string) ($payload['addons'] ?? implode(', ', $existing['addons'] ?? []))),
            'active' => ($payload['active'] ?? (($existing['active'] ?? true) ? '1' : '0')) === '1',
        ];
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

    private static function normalizeAddons(string $addons): array
    {
        $values = array_filter(array_map(static fn (string $value): string => trim($value), explode(',', $addons)));

        return array_values(array_unique($values));
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $connection, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $statement = $connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('Service statement prepare failed: ' . $connection->error);

            return null;
        }

        if ($params !== []) {
            $statement->bind_param($types, ...$params);
        }

        if (!$statement->execute()) {
            error_log('Service statement execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function baseRecords(): array
    {
        return [
            'svc-swedish' => [
                'id' => 'svc-swedish',
                'name' => 'Swedish Reset',
                'category' => 'Massage',
                'description' => 'A full-body reset focused on circulation, gentle release, and nervous-system calm.',
                'price' => 45.00,
                'duration' => 60,
                'buffer' => 15,
                'room' => 'Reset room',
                'addons' => ['Lavender oil', 'Scalp finish'],
                'active' => true,
            ],
            'svc-deep' => [
                'id' => 'svc-deep',
                'name' => 'Deep Tissue Focus',
                'category' => 'Therapeutic',
                'description' => 'Targeted deep tissue work for guests needing postural relief and concentrated muscle release.',
                'price' => 62.00,
                'duration' => 75,
                'buffer' => 15,
                'room' => 'Therapy room',
                'addons' => ['Hot towel reset', 'Muscle balm'],
                'active' => true,
            ],
            'svc-hot-stone' => [
                'id' => 'svc-hot-stone',
                'name' => 'Hot Stone Flow',
                'category' => 'Signature',
                'description' => 'A premium warm-stone treatment that layers heat, pressure, and calm pacing.',
                'price' => 78.00,
                'duration' => 90,
                'buffer' => 20,
                'room' => 'Stone suite',
                'addons' => ['Stone ritual', 'Aromatherapy upgrade'],
                'active' => true,
            ],
            'svc-aroma' => [
                'id' => 'svc-aroma',
                'name' => 'Aromatherapy Calm',
                'category' => 'Wellness',
                'description' => 'Short-format calming session built around gentle movement and carefully selected oils.',
                'price' => 38.00,
                'duration' => 45,
                'buffer' => 10,
                'room' => 'Calm room',
                'addons' => ['Breathwork open', 'Foot compress'],
                'active' => true,
            ],
            'svc-couples' => [
                'id' => 'svc-couples',
                'name' => 'Couples Escape',
                'category' => 'Experience',
                'description' => 'A dual-therapist experience package for guests booking a shared premium treatment.',
                'price' => 135.00,
                'duration' => 90,
                'buffer' => 20,
                'room' => 'Couples suite',
                'addons' => ['Sparkling tea service', 'Extended room prep'],
                'active' => true,
            ],
        ];
    }
}
