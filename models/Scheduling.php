<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Staff.php';
require_once __DIR__ . '/Service.php';

final class Scheduling
{
    public static function slotSettings(): array
    {
        $defaults = [
            'day_start' => '08:00',
            'day_end' => '18:00',
            'slot_interval' => 15,
            'default_duration' => 60,
            'buffer_minutes' => 15,
            'same_day_lead_minutes' => 60,
            'max_parallel_rooms' => 2,
        ];

        return array_merge($defaults, $_SESSION['slot_settings'] ?? []);
    }

    public static function validateSlotSettings(array $payload): array
    {
        $errors = [];

        foreach (['day_start', 'day_end', 'slot_interval', 'default_duration', 'buffer_minutes', 'same_day_lead_minutes', 'max_parallel_rooms'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ((int) ($payload['slot_interval'] ?? 0) <= 0) {
            $errors['slot_interval'] = 'Slot interval must be greater than zero.';
        }

        if ((int) ($payload['default_duration'] ?? 0) <= 0) {
            $errors['default_duration'] = 'Default duration must be greater than zero.';
        }

        if (((string) ($payload['day_start'] ?? '')) >= ((string) ($payload['day_end'] ?? ''))) {
            $errors['day_end'] = 'Day end must be later than day start.';
        }

        return $errors;
    }

    public static function saveSlotSettings(array $payload): array
    {
        $_SESSION['slot_settings'] = [
            'day_start' => (string) $payload['day_start'],
            'day_end' => (string) $payload['day_end'],
            'slot_interval' => (int) $payload['slot_interval'],
            'default_duration' => (int) $payload['default_duration'],
            'buffer_minutes' => (int) $payload['buffer_minutes'],
            'same_day_lead_minutes' => (int) $payload['same_day_lead_minutes'],
            'max_parallel_rooms' => (int) $payload['max_parallel_rooms'],
        ];

        return self::slotSettings();
    }

    public static function weeklyAvailabilityProfiles(): array
    {
        $defaultDays = [
            'monday' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00'],
            'tuesday' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00'],
            'wednesday' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00'],
            'thursday' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00'],
            'friday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            'saturday' => ['enabled' => true, 'start' => '09:00', 'end' => '15:00'],
            'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '13:00'],
        ];

        $profiles = [];

        foreach (Staff::rawAll() as $staff) {
            $profiles[$staff['id']] = [
                'staff_id' => $staff['id'],
                'days' => $defaultDays,
            ];
        }

        if (isset($profiles['stf-kuda']['days']['wednesday'])) {
            $profiles['stf-kuda']['days']['wednesday']['enabled'] = false;
        }

        if (isset($profiles['stf-kuda']['days']['thursday'])) {
            $profiles['stf-kuda']['days']['thursday']['enabled'] = false;
        }

        if (isset($profiles['stf-shamiso']['days']['saturday'])) {
            $profiles['stf-shamiso']['days']['saturday']['end'] = '16:00';
        }

        return array_replace_recursive($profiles, $_SESSION['availability_profiles'] ?? []);
    }

    public static function validateAvailabilityPayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['staff_id'] ?? '')) === '') {
            $errors['staff_id'] = 'Select a staff member.';
        }

        if (($payload['weekdays'] ?? []) === []) {
            $errors['weekdays'] = 'Choose at least one weekday.';
        }

        if (($payload['mode'] ?? 'available') === 'available') {
            if (trim((string) ($payload['start_time'] ?? '')) === '') {
                $errors['start_time'] = 'Start time is required.';
            }

            if (trim((string) ($payload['end_time'] ?? '')) === '') {
                $errors['end_time'] = 'End time is required.';
            }

            if (((string) ($payload['start_time'] ?? '')) >= ((string) ($payload['end_time'] ?? ''))) {
                $errors['end_time'] = 'End time must be later than start time.';
            }
        }

        return $errors;
    }

    public static function saveAvailability(array $payload): void
    {
        $profiles = self::weeklyAvailabilityProfiles();
        $staffId = (string) $payload['staff_id'];
        $mode = (string) ($payload['mode'] ?? 'available');
        $start = (string) ($payload['start_time'] ?? '08:00');
        $end = (string) ($payload['end_time'] ?? '17:00');

        foreach ((array) ($payload['weekdays'] ?? []) as $weekday) {
            $day = strtolower((string) $weekday);

            if (!isset($profiles[$staffId]['days'][$day])) {
                continue;
            }

            if ($mode === 'available') {
                $profiles[$staffId]['days'][$day] = [
                    'enabled' => true,
                    'start' => $start,
                    'end' => $end,
                ];
            } else {
                $profiles[$staffId]['days'][$day]['enabled'] = false;
            }
        }

        $_SESSION['availability_profiles'] = $profiles;
    }

    public static function blockedSlots(): array
    {
        $defaults = array_column(self::defaultBlockedSlots(), null, 'id');
        $stored = $_SESSION['blocked_slots'] ?? [];
        $merged = array_values(array_replace($defaults, $stored));

        usort($merged, static fn (array $left, array $right): int => strcmp($left['date'] . ' ' . $left['start'], $right['date'] . ' ' . $right['start']));

        return $merged;
    }

    public static function validateBlockedSlot(array $payload): array
    {
        $errors = [];

        foreach (['date', 'start_time', 'end_time', 'reason'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (((string) ($payload['start_time'] ?? '')) >= ((string) ($payload['end_time'] ?? ''))) {
            $errors['end_time'] = 'End time must be later than start time.';
        }

        return $errors;
    }

    public static function saveBlockedSlot(array $payload): array
    {
        $stored = $_SESSION['blocked_slots'] ?? [];
        $id = 'BLK' . (time() . random_int(10, 99));

        $stored[$id] = [
            'id' => $id,
            'date' => (string) $payload['date'],
            'start' => (string) $payload['start_time'],
            'end' => (string) $payload['end_time'],
            'staff_id' => trim((string) ($payload['staff_id'] ?? '')),
            'reason' => trim((string) $payload['reason']),
            'scope' => trim((string) ($payload['staff_id'] ?? '')) === '' ? 'global' : 'staff',
        ];

        $_SESSION['blocked_slots'] = $stored;

        return $stored[$id];
    }

    public static function availabilityOverview(): array
    {
        $profiles = self::weeklyAvailabilityProfiles();
        $overview = [];
        $todayKey = strtolower(date('l'));

        foreach (Staff::all() as $staff) {
            $days = $profiles[$staff['id']]['days'];
            $enabledDays = array_filter($days, static fn (array $day): bool => (bool) $day['enabled']);
            $todayWindow = $days[$todayKey];

            $overview[] = [
                'staff' => $staff,
                'enabled_days' => count($enabledDays),
                'today' => $todayWindow['enabled'] ? ($todayWindow['start'] . ' - ' . $todayWindow['end']) : 'Unavailable',
                'summary' => $staff['capacity'] . ' · ' . count($enabledDays) . ' days active this week',
            ];
        }

        return $overview;
    }

    public static function weekMatrix(?string $startDate = null): array
    {
        $monday = date('Y-m-d', strtotime(($startDate ?? 'monday this week')));
        $days = [];

        for ($index = 0; $index < 7; $index++) {
            $date = date('Y-m-d', strtotime($monday . ' +' . $index . ' days'));
            $days[] = [
                'date' => $date,
                'label' => date('D', strtotime($date)),
                'display' => date('j M', strtotime($date)),
            ];
        }

        $rows = [];
        $totalBookings = 0;
        $totalOpenSlots = 0;
        $totalBlocks = 0;
        $busyStaff = 0;

        foreach (Staff::all() as $staff) {
            $cells = [];
            $staffMinutes = 0;

            foreach ($days as $day) {
                $summary = self::staffDaySummary($staff['id'], $day['date']);
                $cells[] = $summary;
                $totalBookings += $summary['bookings_count'];
                $totalOpenSlots += $summary['open_slots'];
                $totalBlocks += $summary['blocked_count'];
                $staffMinutes += $summary['occupied_minutes'];
            }

            if ($staffMinutes >= 240) {
                $busyStaff++;
            }

            $rows[] = [
                'staff' => $staff,
                'cells' => $cells,
            ];
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'stats' => [
                'bookings' => $totalBookings,
                'open_slots' => $totalOpenSlots,
                'blocked_periods' => $totalBlocks,
                'busy_staff' => $busyStaff,
            ],
        ];
    }

    public static function staffAvailability(string $staffId, string $date): array
    {
        $staff = Staff::find($staffId);
        $profiles = self::weeklyAvailabilityProfiles();
        $dayKey = strtolower(date('l', strtotime($date)));
        $window = $profiles[$staffId]['days'][$dayKey] ?? ['enabled' => false, 'start' => '', 'end' => ''];
        $bookings = self::bookingsForStaffDate($staffId, $date);
        $blocked = self::blockedForDate($date, $staffId);

        return [
            'staff' => $staff,
            'date' => $date,
            'window' => $window,
            'bookings' => $bookings,
            'blocked' => $blocked,
            'open_slots' => self::availableSlots(Service::all()[0]['id'], $date, $staffId),
        ];
    }

    public static function availableSlots(string $serviceId, string $date, ?string $staffId = null): array
    {
        $service = Service::find($serviceId);

        if ($service === null) {
            return [];
        }

        $staffIds = $staffId !== null && $staffId !== '' ? [$staffId] : array_map(static fn (array $staff): string => $staff['id'], Staff::all());
        $slots = [];

        foreach ($staffIds as $candidateStaffId) {
            foreach (self::rawSlotsForStaff($candidateStaffId, $date, (int) $service['duration']) as $time) {
                $slots[] = [
                    'staff' => Staff::find($candidateStaffId),
                    'service' => $service,
                    'date' => $date,
                    'start' => $time,
                    'end' => date('H:i', strtotime($time . ' +' . (int) $service['duration'] . ' minutes')),
                ];
            }
        }

        return $slots;
    }

    public static function conflictReason(string $staffId, string $date, string $start, int $duration, ?string $ignoreBookingId = null): ?string
    {
        $profiles = self::weeklyAvailabilityProfiles();
        $dayKey = strtolower(date('l', strtotime($date)));
        $window = $profiles[$staffId]['days'][$dayKey] ?? ['enabled' => false, 'start' => '', 'end' => ''];
        $end = date('H:i', strtotime($start . ' +' . $duration . ' minutes'));

        if (!(bool) $window['enabled']) {
            return 'This therapist is unavailable on the selected day.';
        }

        if ($start < $window['start'] || $end > $window['end']) {
            return 'The selected time is outside this therapist’s working window.';
        }

        foreach (self::blockedForDate($date, $staffId) as $block) {
            if (self::overlaps($start, $end, $block['start'], $block['end'])) {
                return 'The selected time overlaps a blocked slot: ' . $block['reason'] . '.';
            }
        }

        foreach (self::bookingsForStaffDate($staffId, $date) as $booking) {
            if ($ignoreBookingId !== null && $booking['id'] === $ignoreBookingId) {
                continue;
            }

            if (self::overlaps($start, $end, $booking['time'], $booking['end_time'])) {
                return 'The selected time conflicts with booking ' . $booking['reference'] . '.';
            }
        }

        return null;
    }

    private static function staffDaySummary(string $staffId, string $date): array
    {
        $bookings = self::bookingsForStaffDate($staffId, $date);
        $blocked = self::blockedForDate($date, $staffId);
        $openSlots = self::rawSlotsForStaff($staffId, $date, (int) self::slotSettings()['default_duration']);
        $occupiedMinutes = array_sum(array_map(static fn (array $booking): int => (int) $booking['duration'], $bookings));

        return [
            'date' => $date,
            'bookings_count' => count($bookings),
            'open_slots' => count($openSlots),
            'blocked_count' => count($blocked),
            'occupied_minutes' => $occupiedMinutes,
            'labels' => array_map(static fn (array $booking): string => $booking['time'] . ' ' . $booking['service']['name'], array_slice($bookings, 0, 2)),
        ];
    }

    private static function bookingsForStaffDate(string $staffId, string $date): array
    {
        return array_values(array_filter(Booking::all(), static function (array $booking) use ($staffId, $date): bool {
            return $booking['staff']['id'] === $staffId
                && $booking['date'] === $date
                && !in_array($booking['status'], ['cancelled'], true);
        }));
    }

    private static function blockedForDate(string $date, string $staffId): array
    {
        return array_values(array_filter(self::blockedSlots(), static function (array $block) use ($date, $staffId): bool {
            if ($block['date'] !== $date) {
                return false;
            }

            return $block['staff_id'] === '' || $block['staff_id'] === $staffId;
        }));
    }

    private static function rawSlotsForStaff(string $staffId, string $date, int $duration): array
    {
        $profiles = self::weeklyAvailabilityProfiles();
        $settings = self::slotSettings();
        $dayKey = strtolower(date('l', strtotime($date)));
        $window = $profiles[$staffId]['days'][$dayKey] ?? ['enabled' => false, 'start' => '', 'end' => ''];

        if (!(bool) $window['enabled']) {
            return [];
        }

        $slots = [];
        $interval = (int) $settings['slot_interval'];
        $leadMinutes = (int) $settings['same_day_lead_minutes'];
        $startMinutes = self::timeToMinutes($window['start']);
        $endMinutes = self::timeToMinutes($window['end']);
        $threshold = date('Y-m-d') === $date ? time() + ($leadMinutes * 60) : null;

        for ($minutes = $startMinutes; $minutes + $duration <= $endMinutes; $minutes += $interval) {
            $start = self::minutesToTime($minutes);
            $end = self::minutesToTime($minutes + $duration);

            if ($threshold !== null && strtotime($date . ' ' . $start) < $threshold) {
                continue;
            }

            $conflict = self::conflictReason($staffId, $date, $start, $duration);

            if ($conflict === null) {
                $slots[] = $start;
            }
        }

        return $slots;
    }

    private static function overlaps(string $leftStart, string $leftEnd, string $rightStart, string $rightEnd): bool
    {
        return self::timeToMinutes($leftStart) < self::timeToMinutes($rightEnd)
            && self::timeToMinutes($rightStart) < self::timeToMinutes($leftEnd);
    }

    private static function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private static function minutesToTime(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainder = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remainder);
    }

    private static function defaultBlockedSlots(): array
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        return [
            [
                'id' => 'BLK1001',
                'date' => $today,
                'start' => '13:00',
                'end' => '14:00',
                'staff_id' => 'stf-amanda',
                'reason' => 'Physio referral handoff',
                'scope' => 'staff',
            ],
            [
                'id' => 'BLK1002',
                'date' => $today,
                'start' => '15:45',
                'end' => '16:15',
                'staff_id' => '',
                'reason' => 'Studio sanitation turnaround',
                'scope' => 'global',
            ],
            [
                'id' => 'BLK1003',
                'date' => $tomorrow,
                'start' => '09:00',
                'end' => '10:00',
                'staff_id' => 'stf-kuda',
                'reason' => 'Manual unavailability block',
                'scope' => 'staff',
            ],
        ];
    }
}
