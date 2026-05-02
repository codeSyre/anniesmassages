<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Customer
{
    public static function all(array $filters = []): array
    {
        $customers = array_values(self::mergedRecords());
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        if ($search !== '') {
            $customers = array_values(array_filter($customers, static function (array $customer) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $customer['name'],
                    $customer['email'],
                    $customer['phone'],
                    $customer['preference'],
                    $customer['admin_notes'],
                    implode(' ', $customer['tags']),
                ]));

                return str_contains($haystack, $search);
            }));
        }

        usort($customers, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return array_map(static function (array $customer): array {
            return $customer + self::bookingSummary($customer['id']);
        }, $customers);
    }

    public static function stats(): array
    {
        $customers = self::all();
        $bookings = Booking::all();
        $today = date('Y-m-d');
        $returning = 0;
        $upcoming = 0;
        $preferencesTracked = 0;

        foreach ($customers as $customer) {
            if ((int) $customer['booking_count'] >= 2) {
                $returning++;
            }

            if (trim((string) $customer['preference']) !== '') {
                $preferencesTracked++;
            }
        }

        foreach ($bookings as $booking) {
            if ($booking['date'] >= $today && !in_array($booking['status'], ['cancelled'], true)) {
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

        if ($formType === 'profile') {
            foreach (['name', 'phone'] as $field) {
                if (trim((string) ($payload[$field] ?? '')) === '') {
                    $errors[$field] = 'This field is required.';
                }
            }

            $email = trim((string) ($payload['email'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Enter a valid email address.';
            }
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): array
    {
        $records = $_SESSION['customer_records'] ?? [];
        $existing = $id !== null ? self::find($id) : null;
        $customerId = $existing['id'] ?? self::nextId();

        $customer = [
            'id' => $customerId,
            'name' => trim((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'phone' => trim((string) ($payload['phone'] ?? ($existing['phone'] ?? ''))),
            'email' => trim((string) ($payload['email'] ?? ($existing['email'] ?? ''))),
            'preference' => trim((string) ($payload['preference'] ?? ($existing['preference'] ?? ''))),
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? ($existing['admin_notes'] ?? ''))),
            'source' => trim((string) ($payload['source'] ?? ($existing['source'] ?? 'front desk'))),
            'location' => trim((string) ($payload['location'] ?? ($existing['location'] ?? 'Harare'))),
            'tags' => self::normalizeTags((string) ($payload['tags'] ?? implode(', ', $existing['tags'] ?? []))),
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        $records[$customerId] = $customer;
        $_SESSION['customer_records'] = $records;

        return self::find($customerId) ?? $customer;
    }

    public static function saveNotes(string $customerId, array $payload): array
    {
        $customer = self::find($customerId);

        if ($customer === null) {
            throw new RuntimeException('Customer not found.');
        }

        return self::save([
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'source' => $customer['source'],
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

    private static function mergedRecords(): array
    {
        $records = self::baseRecords();

        foreach ($_SESSION['customer_records'] ?? [] as $id => $customer) {
            $records[$id] = $customer;
        }

        return $records;
    }

    private static function nextId(): string
    {
        $max = 2000;

        foreach (array_keys(self::mergedRecords()) as $id) {
            $numeric = (int) preg_replace('/\D+/', '', $id);
            $max = max($max, $numeric);
        }

        return 'cust-' . ($max + 1);
    }

    private static function normalizeTags(string $tags): array
    {
        $values = array_filter(array_map(static fn (string $value): string => trim($value), explode(',', $tags)));

        return array_values(array_unique($values));
    }

    private static function baseRecords(): array
    {
        return [
            'cust-rudo' => [
                'id' => 'cust-rudo',
                'name' => 'Rudo Ncube',
                'phone' => '+263 77 100 2001',
                'email' => 'rudo.ncube@example.com',
                'preference' => 'Light pressure, lavender oil',
                'admin_notes' => 'Prefers quieter treatment rooms and tends to rebook after travel weeks.',
                'source' => 'front desk',
                'location' => 'Borrowdale',
                'tags' => ['returning', 'wellness plan'],
                'created_at' => '2026-03-08 09:10:00',
            ],
            'cust-lauren' => [
                'id' => 'cust-lauren',
                'name' => 'Lauren Price',
                'phone' => '+263 77 100 2002',
                'email' => 'lauren.price@example.com',
                'preference' => 'Deep tissue shoulders',
                'admin_notes' => 'Usually books after training blocks. Likes direct confirmation calls.',
                'source' => 'phone',
                'location' => 'Avondale',
                'tags' => ['athlete', 'deposit required'],
                'created_at' => '2026-02-19 14:45:00',
            ],
            'cust-angela' => [
                'id' => 'cust-angela',
                'name' => 'Angela Banda',
                'phone' => '+263 77 100 2003',
                'email' => 'angela.banda@example.com',
                'preference' => 'Warm room, minimal scent',
                'admin_notes' => 'Sensitive to heavily perfumed oils. Best experience in lower-traffic afternoon slots.',
                'source' => 'web',
                'location' => 'Mount Pleasant',
                'tags' => ['premium', 'allergy aware'],
                'created_at' => '2026-01-11 11:30:00',
            ],
            'cust-james-linda' => [
                'id' => 'cust-james-linda',
                'name' => 'James & Linda',
                'phone' => '+263 77 100 2004',
                'email' => 'james.linda@example.com',
                'preference' => 'Dual room setup',
                'admin_notes' => 'Books experience packages. Confirm arrival times and room prep in advance.',
                'source' => 'concierge',
                'location' => 'Glen Lorne',
                'tags' => ['couples', 'experience package'],
                'created_at' => '2026-04-01 16:20:00',
            ],
            'cust-chipo' => [
                'id' => 'cust-chipo',
                'name' => 'Chipo Nyoni',
                'phone' => '+263 77 100 2005',
                'email' => 'chipo.nyoni@example.com',
                'preference' => 'Midday availability',
                'admin_notes' => 'Schedule is flexible but often shifts within the same day. WhatsApp works best.',
                'source' => 'WhatsApp',
                'location' => 'CBD',
                'tags' => ['reschedules often', 'midday'],
                'created_at' => '2026-04-22 10:05:00',
            ],
        ];
    }
}
