<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Service
{
    public static function all(array $filters = []): array
    {
        $services = array_values(self::mergedRecords());
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

        return array_map(static function (array $service): array {
            return $service + self::usageSummary($service['id']);
        }, $services);
    }

    public static function activeOptions(): array
    {
        return array_values(array_filter(self::all(), static fn (array $service): bool => (bool) $service['active']));
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

        return $service + self::usageSummary($id);
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

        foreach (['name', 'price', 'duration'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (!is_numeric((string) ($payload['price'] ?? ''))) {
            $errors['price'] = 'Price must be a valid number.';
        }

        if (!is_numeric((string) ($payload['duration'] ?? '')) || (int) ($payload['duration'] ?? 0) <= 0) {
            $errors['duration'] = 'Duration must be a positive number.';
        }

        if (($payload['buffer'] ?? '') !== '' && (!is_numeric((string) $payload['buffer']) || (int) $payload['buffer'] < 0)) {
            $errors['buffer'] = 'Buffer must be zero or greater.';
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): array
    {
        $records = $_SESSION['service_records'] ?? [];
        $existing = $id !== null ? self::find($id) : null;
        $serviceId = $existing['id'] ?? self::nextId();

        $service = [
            'id' => $serviceId,
            'name' => trim((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'category' => trim((string) ($payload['category'] ?? ($existing['category'] ?? 'Massage'))),
            'description' => trim((string) ($payload['description'] ?? ($existing['description'] ?? ''))),
            'price' => round((float) ($payload['price'] ?? ($existing['price'] ?? 0)), 2),
            'duration' => (int) ($payload['duration'] ?? ($existing['duration'] ?? 60)),
            'buffer' => (int) ($payload['buffer'] ?? ($existing['buffer'] ?? 15)),
            'room' => trim((string) ($payload['room'] ?? ($existing['room'] ?? 'Studio'))),
            'addons' => self::normalizeAddons((string) ($payload['addons'] ?? implode(', ', $existing['addons'] ?? []))),
            'active' => ($payload['active'] ?? '1') === '1',
        ];

        $records[$serviceId] = $service;
        $_SESSION['service_records'] = $records;

        return self::find($serviceId) ?? $service;
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

    private static function mergedRecords(): array
    {
        $records = self::baseRecords();

        foreach ($_SESSION['service_records'] ?? [] as $id => $service) {
            $records[$id] = $service;
        }

        return $records;
    }

    private static function nextId(): string
    {
        $max = 3000;

        foreach (array_keys(self::mergedRecords()) as $id) {
            $numeric = (int) preg_replace('/\D+/', '', $id);
            $max = max($max, $numeric);
        }

        return 'svc-' . ($max + 1);
    }

    private static function normalizeAddons(string $addons): array
    {
        $values = array_filter(array_map(static fn (string $value): string => trim($value), explode(',', $addons)));

        return array_values(array_unique($values));
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
