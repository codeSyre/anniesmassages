<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Customer
{
    public static function all(array $filters = []): array
    {
        $customers = self::rawAll($filters);

        return array_map(static function (array $customer): array {
            return $customer + self::bookingSummary($customer['id']);
        }, $customers);
    }

    public static function rawAll(array $filters = []): array
    {
        return self::filteredRecords(self::mergedRecords(), $filters);
    }

    public static function stats(): array
    {
        $customers = self::all();
        $today = date('Y-m-d');
        $returning = 0;
        $upcoming = 0;
        $preferencesTracked = 0;
        $customerIds = array_map(static fn (array $customer): string => (string) $customer['id'], $customers);

        foreach ($customers as $customer) {
            if ((int) $customer['booking_count'] >= 2) {
                $returning++;
            }

            if (trim((string) $customer['preference']) !== '') {
                $preferencesTracked++;
            }
        }

        foreach (Booking::all() as $booking) {
            $bookingCustomerId = (string) ($booking['customer']['id'] ?? '');

            if (
                $booking['date'] >= $today
                && !in_array($booking['status'], ['cancelled'], true)
                && in_array($bookingCustomerId, $customerIds, true)
            ) {
                $upcoming++;
            }
        }

        return [
            ['label' => 'Customer profiles', 'value' => (string) count($customers), 'tone' => 'info'],
            ['label' => 'Returning guests', 'value' => (string) $returning, 'tone' => 'success'],
            ['label' => 'Upcoming visits', 'value' => (string) $upcoming, 'tone' => 'warning'],
            ['label' => 'Preferences tracked', 'value' => (string) $preferencesTracked, 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        $customer = self::mergedRecords()[$id] ?? null;

        if ($customer === null) {
            return null;
        }

        return $customer + self::bookingSummary($id);
    }

    public static function ban(string $customerId): array
    {
        $customer = self::find($customerId);

        if ($customer === null) {
            return ['success' => false, 'error' => 'Customer not found.'];
        }

        if (($customer['status'] ?? 'active') === 'banned') {
            return ['success' => false, 'error' => 'This customer is already banned.'];
        }

        $tags = $customer['tags'];
        $tags[] = self::bannedTag();

        $saved = self::save([
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'location' => $customer['location'],
            'preference' => $customer['preference'],
            'admin_notes' => $customer['admin_notes'],
            'tags' => implode(', ', $tags),
        ], $customerId);

        if (!is_array($saved) || trim((string) ($saved['id'] ?? '')) === '') {
            return ['success' => false, 'error' => 'We could not ban this customer in the database.'];
        }

        return ['success' => true, 'name' => (string) $customer['name']];
    }

    public static function bookings(string $customerId): array
    {
        $bookings = array_values(array_filter(Booking::all(), static function (array $booking) use ($customerId): bool {
            return ($booking['customer']['id'] ?? '') === $customerId;
        }));

        usort($bookings, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

        return $bookings;
    }

    public static function paymentHistory(string $customerId): array
    {
        return array_map(static function (array $booking): array {
            return [
                'reference' => $booking['reference'],
                'date' => $booking['date'],
                'service' => $booking['service']['name'],
                'paid' => (float) $booking['amount_paid'],
                'balance' => (float) $booking['balance'],
                'status' => $booking['payment_status'],
            ];
        }, self::bookings($customerId));
    }

    public static function validate(array $payload, string $formType = 'profile'): array
    {
        $errors = [];
        $normalized = self::normalizePayload($payload);

        if ($formType === 'profile') {
            foreach (['first_name', 'last_name', 'phone'] as $field) {
                if (trim((string) ($normalized[$field] ?? '')) === '') {
                    $errors[$field] = 'This field is required.';
                }
            }

            $email = trim((string) ($normalized['email'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Enter a valid email address.';
            }
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): ?array
    {
        $existing = $id !== null ? self::find($id) : null;
        $customer = self::normalizePayload($payload, $existing);
        $customerId = $existing['id'] ?? self::nextId();
        $customer['id'] = $customerId;

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            mysqli_begin_transaction($connection);

            try {
                $statement = self::prepare(
                    $connection,
                    'INSERT INTO customers (
                        id, first_name, last_name, phone, email, preference, admin_notes, location, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        first_name = VALUES(first_name),
                        last_name = VALUES(last_name),
                        phone = VALUES(phone),
                        email = VALUES(email),
                        preference = VALUES(preference),
                        admin_notes = VALUES(admin_notes),
                        location = VALUES(location),
                        updated_at = NOW()',
                    'ssssssss',
                    [
                        $customerId,
                        $customer['first_name'],
                        $customer['last_name'],
                        $customer['phone'],
                        $customer['email'],
                        $customer['preference'],
                        $customer['admin_notes'],
                        $customer['location'],
                    ]
                );

                if (!$statement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to save customer.');
                }
                $statement->close();

                $deleteStatement = self::prepare($connection, 'DELETE FROM customer_tags WHERE customer_id = ?', 's', [$customerId]);

                if (!$deleteStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to reset customer tags.');
                }
                $deleteStatement->close();

                foreach (array_values($customer['tags']) as $tag) {
                    $tagStatement = self::prepare(
                        $connection,
                        'INSERT INTO customer_tags (customer_id, tag) VALUES (?, ?)',
                        'ss',
                        [$customerId, $tag]
                    );

                    if (!$tagStatement instanceof mysqli_stmt) {
                        throw new RuntimeException('Unable to save customer tags.');
                    }
                    $tagStatement->close();
                }

                mysqli_commit($connection);
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Customer save failed: ' . $exception->getMessage());

                return null;
            }

            return self::find($customerId);
        }

        if (function_exists('db_configured') && db_configured()) {
            return null;
        }

        $records = $_SESSION['customer_records'] ?? [];
        $records[$customerId] = $customer;
        $_SESSION['customer_records'] = $records;

        return self::find($customerId);
    }

    public static function saveNotes(string $customerId, array $payload): ?array
    {
        $customer = self::find($customerId);

        if ($customer === null) {
            throw new RuntimeException('Customer not found.');
        }

        return self::save([
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'location' => $customer['location'],
            'preference' => trim((string) ($payload['preference'] ?? $customer['preference'])),
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? $customer['admin_notes'])),
            'tags' => trim((string) ($payload['tags'] ?? implode(', ', $customer['tags']))),
        ], $customerId);
    }

    public static function search(string $query): array
    {
        return array_slice(self::all(['search' => $query]), 0, 8);
    }

    private static function bookingSummary(string $customerId): array
    {
        $bookings = self::bookings($customerId);
        $today = date('Y-m-d');
        $spent = 0.0;
        $nextVisit = null;
        $lastVisit = null;

        foreach ($bookings as $booking) {
            $spent += (float) $booking['amount_paid'];

            if ($booking['date'] >= $today && $nextVisit === null && !in_array($booking['status'], ['cancelled'], true)) {
                $nextVisit = $booking['date'] . ' ' . $booking['time'];
            }

            if ($booking['date'] < $today && $lastVisit === null) {
                $lastVisit = $booking['date'] . ' ' . $booking['time'];
            }
        }

        return [
            'booking_count' => count($bookings),
            'total_spent' => $spent,
            'next_visit' => $nextVisit,
            'last_visit' => $lastVisit,
        ];
    }

    private static function filteredRecords(array $records, array $filters = []): array
    {
        $customers = array_values($records);
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        if ($search !== '') {
            $customers = array_values(array_filter($customers, static function (array $customer) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $customer['name'],
                    $customer['first_name'],
                    $customer['last_name'],
                    $customer['email'],
                    $customer['phone'],
                    $customer['preference'],
                    $customer['admin_notes'],
                    $customer['location'],
                    implode(' ', $customer['tags']),
                ]));

                return str_contains($haystack, $search);
            }));
        }

        usort($customers, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $customers;
    }

    private static function mergedRecords(): array
    {
        $databaseRecords = self::databaseRecords();

        if ($databaseRecords !== null) {
            return $databaseRecords;
        }

        $records = self::baseRecords();

        foreach ($records as $id => $customer) {
            $records[$id] = self::finalizeRecord($customer);
        }

        foreach ($_SESSION['customer_records'] ?? [] as $id => $customer) {
            $records[$id] = self::finalizeRecord(self::normalizePayload($customer, ['id' => $id]));
        }

        return $records;
    }

    private static function databaseRecords(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = $connection->query('SELECT * FROM customers');

        if (!$result instanceof mysqli_result) {
            error_log('Unable to fetch customer records from database: ' . $connection->error);

            return [];
        }

        $records = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $customer = self::mapDatabaseRow($row);
            $records[$customer['id']] = $customer;
        }
        $result->free();

        $tagResult = $connection->query('SELECT customer_id, tag FROM customer_tags ORDER BY customer_id, tag');

        if ($tagResult instanceof mysqli_result) {
            while ($row = $tagResult->fetch_assoc()) {
                $customerId = (string) ($row['customer_id'] ?? '');
                $tag = trim((string) ($row['tag'] ?? ''));

                if ($customerId !== '' && $tag !== '' && isset($records[$customerId])) {
                    $records[$customerId]['tags'][] = $tag;
                }
            }
            $tagResult->free();
        }

        foreach ($records as $id => $customer) {
            $records[$id] = self::finalizeRecord($customer);
        }

        return $records;
    }

    private static function mapDatabaseRow(array $row): array
    {
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));

        return [
            'id' => (string) ($row['id'] ?? ''),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'preference' => trim((string) ($row['preference'] ?? '')),
            'admin_notes' => trim((string) ($row['admin_notes'] ?? '')),
            'location' => trim((string) ($row['location'] ?? 'Harare')) ?: 'Harare',
            'tags' => [],
            'created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
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

        return [
            'id' => (string) ($payload['id'] ?? ($existing['id'] ?? '')),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'phone' => trim((string) ($payload['phone'] ?? ($existing['phone'] ?? ''))),
            'email' => trim((string) ($payload['email'] ?? ($existing['email'] ?? ''))),
            'preference' => trim((string) ($payload['preference'] ?? ($existing['preference'] ?? ''))),
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? ($existing['admin_notes'] ?? ''))),
            'location' => trim((string) ($payload['location'] ?? ($existing['location'] ?? 'Harare'))) ?: 'Harare',
            'tags' => self::normalizedTagSet(
                (string) ($payload['tags'] ?? implode(', ', $existing['tags'] ?? [])),
                self::systemTagsFromExisting($existing)
            ),
            'created_at' => (string) ($existing['created_at'] ?? date('Y-m-d H:i:s')),
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

    private static function normalizeTags(string $tags): array
    {
        $values = array_filter(array_map(static fn (string $value): string => trim($value), explode(',', $tags)));

        return array_values(array_unique($values));
    }

    private static function normalizedTagSet(string $tags, array $systemTags = []): array
    {
        $values = array_merge(self::normalizeTags($tags), $systemTags);

        return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
    }

    private static function systemTagsFromExisting(?array $existing): array
    {
        if (!is_array($existing)) {
            return [];
        }

        $systemTags = [];

        if (($existing['status'] ?? 'active') === 'banned') {
            $systemTags[] = self::bannedTag();
        }

        return $systemTags;
    }

    private static function finalizeRecord(array $customer): array
    {
        $rawTags = array_values($customer['tags'] ?? []);
        $isBanned = in_array(self::bannedTag(), $rawTags, true);
        $tags = array_values(array_filter(
            $rawTags,
            static fn (string $tag): bool => $tag !== self::bannedTag()
        ));

        $customer['tags'] = $tags;
        $customer['status'] = $isBanned ? 'banned' : 'active';
        $customer['can_ban'] = !$isBanned;

        return $customer;
    }

    private static function bannedTag(): string
    {
        return '__banned__';
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $connection, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $statement = $connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('Customer statement prepare failed: ' . $connection->error);

            return null;
        }

        if ($params !== []) {
            $statement->bind_param($types, ...$params);
        }

        if (!$statement->execute()) {
            error_log('Customer statement execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function baseRecords(): array
    {
        return [
            'cust-rudo' => [
                'id' => 'cust-rudo',
                'first_name' => 'Rudo',
                'last_name' => 'Ncube',
                'name' => 'Rudo Ncube',
                'phone' => '+263 77 100 2001',
                'email' => 'rudo.ncube@example.com',
                'preference' => 'Light pressure, lavender oil',
                'admin_notes' => 'Prefers quieter treatment rooms and tends to rebook after travel weeks.',
                'location' => 'Borrowdale',
                'tags' => ['returning', 'wellness plan'],
                'created_at' => '2026-03-08 09:10:00',
            ],
            'cust-lauren' => [
                'id' => 'cust-lauren',
                'first_name' => 'Lauren',
                'last_name' => 'Price',
                'name' => 'Lauren Price',
                'phone' => '+263 77 100 2002',
                'email' => 'lauren.price@example.com',
                'preference' => 'Deep tissue shoulders',
                'admin_notes' => 'Usually books after training blocks. Likes direct confirmation calls.',
                'location' => 'Avondale',
                'tags' => ['athlete', 'deposit required'],
                'created_at' => '2026-02-19 14:45:00',
            ],
            'cust-angela' => [
                'id' => 'cust-angela',
                'first_name' => 'Angela',
                'last_name' => 'Banda',
                'name' => 'Angela Banda',
                'phone' => '+263 77 100 2003',
                'email' => 'angela.banda@example.com',
                'preference' => 'Warm room, minimal scent',
                'admin_notes' => 'Sensitive to heavily perfumed oils. Best experience in lower-traffic afternoon slots.',
                'location' => 'Mount Pleasant',
                'tags' => ['premium', 'allergy aware'],
                'created_at' => '2026-01-11 11:30:00',
            ],
            'cust-james-linda' => [
                'id' => 'cust-james-linda',
                'first_name' => 'James',
                'last_name' => 'Linda',
                'name' => 'James Linda',
                'phone' => '+263 77 100 2004',
                'email' => 'james.linda@example.com',
                'preference' => 'Dual room setup',
                'admin_notes' => 'Books experience packages. Confirm arrival times and room prep in advance.',
                'location' => 'Glen Lorne',
                'tags' => ['couples', 'experience package'],
                'created_at' => '2026-04-01 16:20:00',
            ],
            'cust-chipo' => [
                'id' => 'cust-chipo',
                'first_name' => 'Chipo',
                'last_name' => 'Nyoni',
                'name' => 'Chipo Nyoni',
                'phone' => '+263 77 100 2005',
                'email' => 'chipo.nyoni@example.com',
                'preference' => 'Midday availability',
                'admin_notes' => 'Schedule is flexible but often shifts within the same day. WhatsApp works best.',
                'location' => 'CBD',
                'tags' => ['reschedules often', 'midday'],
                'created_at' => '2026-04-22 10:05:00',
            ],
        ];
    }
}
