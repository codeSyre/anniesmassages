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
        return self::filteredRecords($filters);
    }

    public static function stats(): array
    {
        $staff = self::all();
        $active = array_filter($staff, static fn (array $member): bool => $member['status'] === 'active');
        $onLeave = array_filter($staff, static fn (array $member): bool => $member['status'] === 'on_leave');
        $bookedToday = array_filter($staff, static fn (array $member): bool => $member['today_booking_count'] > 0);
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

    public static function validate(array $payload): array
    {
        $errors = [];

        foreach (['name', 'specialty', 'role_type', 'status', 'salary_structure'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (($payload['commission_rate'] ?? '') !== '' && (!is_numeric((string) $payload['commission_rate']) || (float) $payload['commission_rate'] < 0)) {
            $errors['commission_rate'] = 'Commission rate must be zero or greater.';
        }

        if (($payload['fixed_pay'] ?? '') !== '' && (!is_numeric((string) $payload['fixed_pay']) || (float) $payload['fixed_pay'] < 0)) {
            $errors['fixed_pay'] = 'Fixed pay must be zero or greater.';
        }

        return $errors;
    }

    public static function save(array $payload, ?string $id = null): array
    {
        $records = $_SESSION['staff_records'] ?? [];
        $existing = $id !== null ? self::find($id) : null;
        $staffId = $existing['id'] ?? self::nextId();

        $member = [
            'id' => $staffId,
            'name' => trim((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'specialty' => trim((string) ($payload['specialty'] ?? ($existing['specialty'] ?? ''))),
            'role_type' => trim((string) ($payload['role_type'] ?? ($existing['role_type'] ?? 'therapist'))),
            'status' => trim((string) ($payload['status'] ?? ($existing['status'] ?? 'active'))),
            'phone' => trim((string) ($payload['phone'] ?? ($existing['phone'] ?? ''))),
            'email' => trim((string) ($payload['email'] ?? ($existing['email'] ?? ''))),
            'bio' => trim((string) ($payload['bio'] ?? ($existing['bio'] ?? ''))),
            'capacity' => trim((string) ($payload['capacity'] ?? ($existing['capacity'] ?? '4 sessions/day'))),
            'salary_structure' => trim((string) ($payload['salary_structure'] ?? ($existing['salary_structure'] ?? 'commission'))),
            'commission_rate' => round((float) ($payload['commission_rate'] ?? ($existing['commission_rate'] ?? 25)), 2),
            'fixed_pay' => round((float) ($payload['fixed_pay'] ?? ($existing['fixed_pay'] ?? 0)), 2),
            'color' => trim((string) ($payload['color'] ?? ($existing['color'] ?? 'cyan'))),
        ];

        $records[$staffId] = $member;
        $_SESSION['staff_records'] = $records;

        return self::find($staffId) ?? $member;
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

    private static function filteredRecords(array $filters = []): array
    {
        $staff = array_values(self::mergedRecords());
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? 'all');

        if ($search !== '') {
            $staff = array_values(array_filter($staff, static function (array $member) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $member['name'],
                    $member['specialty'],
                    $member['role_type'],
                    $member['phone'],
                    $member['email'],
                    $member['bio'],
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
        $records = self::baseRecords();

        foreach ($_SESSION['staff_records'] ?? [] as $id => $member) {
            $records[$id] = $member;
        }

        return $records;
    }

    private static function nextId(): string
    {
        $max = 4000;

        foreach (array_keys(self::mergedRecords()) as $id) {
            $numeric = (int) preg_replace('/\D+/', '', $id);
            $max = max($max, $numeric);
        }

        return 'stf-' . ($max + 1);
    }

    private static function baseRecords(): array
    {
        return [
            'stf-tariro' => [
                'id' => 'stf-tariro',
                'name' => 'Tariro Moyo',
                'specialty' => 'Recovery and sports',
                'role_type' => 'therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3001',
                'email' => 'tariro@anniesmassages.test',
                'bio' => 'Focused on athletic recovery, post-travel reset work, and structured pressure progression.',
                'capacity' => '4 sessions/day',
                'salary_structure' => 'commission',
                'commission_rate' => 28.0,
                'fixed_pay' => 0.0,
                'color' => 'cyan',
            ],
            'stf-amanda' => [
                'id' => 'stf-amanda',
                'name' => 'Amanda Sibanda',
                'specialty' => 'Deep tissue and posture work',
                'role_type' => 'senior therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3002',
                'email' => 'amanda@anniesmassages.test',
                'bio' => 'Handles high-intensity deep tissue sessions and clients needing corrective bodywork.',
                'capacity' => '5 sessions/day',
                'salary_structure' => 'hybrid',
                'commission_rate' => 22.0,
                'fixed_pay' => 110.0,
                'color' => 'teal',
            ],
            'stf-shamiso' => [
                'id' => 'stf-shamiso',
                'name' => 'Shamiso Chuma',
                'specialty' => 'Relaxation and hot stone',
                'role_type' => 'therapist',
                'status' => 'active',
                'phone' => '+263 77 200 3003',
                'email' => 'shamiso@anniesmassages.test',
                'bio' => 'Leads the premium warm-stone experience and slower-paced therapeutic relaxation sessions.',
                'capacity' => '4 sessions/day',
                'salary_structure' => 'commission',
                'commission_rate' => 30.0,
                'fixed_pay' => 0.0,
                'color' => 'amber',
            ],
            'stf-kuda' => [
                'id' => 'stf-kuda',
                'name' => 'Kuda Mlambo',
                'specialty' => 'Aromatherapy and mobile visits',
                'role_type' => 'therapist',
                'status' => 'on_leave',
                'phone' => '+263 77 200 3004',
                'email' => 'kuda@anniesmassages.test',
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
