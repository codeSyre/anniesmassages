<?php declare(strict_types=1);

final class Booking
{
    public static function all(array $filters = []): array
    {
        $bookings = self::mergedRecords();

        usort(
            $bookings,
            static fn (array $left, array $right): int => strcmp($left['sort_key'], $right['sort_key'])
        );

        return array_values(array_filter($bookings, static function (array $booking) use ($filters): bool {
            $status = (string) ($filters['status'] ?? '');
            $search = strtolower(trim((string) ($filters['search'] ?? '')));

            if ($status !== '' && $status !== 'all' && $booking['status'] !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $booking['reference'],
                $booking['customer']['name'],
                $booking['service']['name'],
                $booking['staff']['name'],
            ]));

            return str_contains($haystack, $search);
        }));
    }

    public static function stats(): array
    {
        $bookings = self::all();
        $today = date('Y-m-d');

        $todayCount = 0;
        $pendingCount = 0;
        $confirmedCount = 0;
        $outstanding = 0.0;

        foreach ($bookings as $booking) {
            if ($booking['date'] === $today) {
                $todayCount++;
            }

            if ($booking['status'] === 'pending') {
                $pendingCount++;
            }

            if ($booking['status'] === 'confirmed') {
                $confirmedCount++;
            }

            $outstanding += (float) $booking['balance'];
        }

        return [
            ['label' => "Today's bookings", 'value' => (string) $todayCount, 'tone' => 'info'],
            ['label' => 'Pending confirmation', 'value' => (string) $pendingCount, 'tone' => 'warning'],
            ['label' => 'Confirmed sessions', 'value' => (string) $confirmedCount, 'tone' => 'success'],
            ['label' => 'Outstanding balance', 'value' => format_money($outstanding), 'tone' => 'danger'],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::mergedRecords() as $booking) {
            if ($booking['id'] === $id) {
                return $booking;
            }
        }

        return null;
    }

    public static function formOptions(): array
    {
        $services = [
            'svc-swedish' => ['id' => 'svc-swedish', 'name' => 'Swedish Reset', 'duration' => 60, 'price' => 45.00, 'active' => true],
            'svc-deep' => ['id' => 'svc-deep', 'name' => 'Deep Tissue Focus', 'duration' => 75, 'price' => 62.00, 'active' => true],
            'svc-hot-stone' => ['id' => 'svc-hot-stone', 'name' => 'Hot Stone Flow', 'duration' => 90, 'price' => 78.00, 'active' => true],
            'svc-aroma' => ['id' => 'svc-aroma', 'name' => 'Aromatherapy Calm', 'duration' => 45, 'price' => 38.00, 'active' => true],
            'svc-couples' => ['id' => 'svc-couples', 'name' => 'Couples Escape', 'duration' => 90, 'price' => 135.00, 'active' => true],
        ];

        foreach ($_SESSION['service_records'] ?? [] as $id => $service) {
            $services[$id] = [
                'id' => (string) ($service['id'] ?? $id),
                'name' => (string) ($service['name'] ?? 'Service'),
                'duration' => (int) ($service['duration'] ?? 60),
                'price' => (float) ($service['price'] ?? 0),
                'active' => (bool) ($service['active'] ?? true),
            ];
        }

        $customers = [
            'cust-rudo' => ['id' => 'cust-rudo', 'name' => 'Rudo Ncube', 'phone' => '+263 77 100 2001', 'preference' => 'Light pressure, lavender oil'],
            'cust-lauren' => ['id' => 'cust-lauren', 'name' => 'Lauren Price', 'phone' => '+263 77 100 2002', 'preference' => 'Deep tissue shoulders'],
            'cust-angela' => ['id' => 'cust-angela', 'name' => 'Angela Banda', 'phone' => '+263 77 100 2003', 'preference' => 'Warm room, minimal scent'],
            'cust-james-linda' => ['id' => 'cust-james-linda', 'name' => 'James & Linda', 'phone' => '+263 77 100 2004', 'preference' => 'Dual room setup'],
            'cust-chipo' => ['id' => 'cust-chipo', 'name' => 'Chipo Nyoni', 'phone' => '+263 77 100 2005', 'preference' => 'Midday availability'],
        ];

        foreach ($_SESSION['customer_records'] ?? [] as $id => $customer) {
            $customers[$id] = [
                'id' => (string) ($customer['id'] ?? $id),
                'name' => (string) ($customer['name'] ?? 'Guest'),
                'phone' => (string) ($customer['phone'] ?? ''),
                'preference' => (string) ($customer['preference'] ?? ''),
            ];
        }

        $staff = [
            'stf-tariro' => ['id' => 'stf-tariro', 'name' => 'Tariro Moyo', 'specialty' => 'Recovery and sports'],
            'stf-amanda' => ['id' => 'stf-amanda', 'name' => 'Amanda Sibanda', 'specialty' => 'Deep tissue and posture work'],
            'stf-shamiso' => ['id' => 'stf-shamiso', 'name' => 'Shamiso Chuma', 'specialty' => 'Relaxation and hot stone'],
            'stf-kuda' => ['id' => 'stf-kuda', 'name' => 'Kuda Mlambo', 'specialty' => 'Aromatherapy and mobile visits'],
        ];

        foreach ($_SESSION['staff_records'] ?? [] as $id => $member) {
            $staff[$id] = [
                'id' => (string) ($member['id'] ?? $id),
                'name' => (string) ($member['name'] ?? 'Therapist'),
                'specialty' => (string) ($member['specialty'] ?? ''),
            ];
        }

        return [
            'services' => $services,
            'customers' => $customers,
            'staff' => $staff,
            'statuses' => ['pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled'],
            'payment_statuses' => ['unpaid', 'partial', 'paid', 'refunded'],
        ];
    }

    public static function calendarDays(): array
    {
        $days = [];

        foreach (self::all() as $booking) {
            $days[$booking['date']][] = $booking;
        }

        ksort($days);

        return $days;
    }

    public static function save(array $payload, ?string $id = null): array
    {
        $options = self::formOptions();

        $service = $options['services'][$payload['service_id']] ?? reset($options['services']);
        $customer = $options['customers'][$payload['customer_id']] ?? reset($options['customers']);
        $staff = $options['staff'][$payload['staff_id']] ?? reset($options['staff']);
        $existing = $id !== null ? self::find($id) : null;

        $bookingId = $existing['id'] ?? self::nextId();
        $reference = $existing['reference'] ?? ('BK-' . substr($bookingId, 3));
        $date = (string) $payload['date'];
        $start = (string) $payload['start_time'];
        $duration = (int) $service['duration'];
        $end = date('H:i', strtotime($start . ' +' . $duration . ' minutes'));
        $amountTotal = (float) $service['price'];
        $amountPaid = min($amountTotal, max(0.0, (float) ($payload['amount_paid'] ?? 0)));

        $status = (string) $payload['status'];
        $paymentStatus = (string) $payload['payment_status'];

        if ($amountPaid >= $amountTotal) {
            $paymentStatus = 'paid';
        } elseif ($amountPaid > 0.0 && $paymentStatus === 'unpaid') {
            $paymentStatus = 'partial';
        }

        $booking = [
            'id' => $bookingId,
            'reference' => $reference,
            'date' => $date,
            'time' => $start,
            'end_time' => $end,
            'duration' => $duration,
            'sort_key' => $date . ' ' . $start,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'channel' => (string) ($payload['channel'] ?? 'admin'),
            'customer' => $customer,
            'service' => $service,
            'staff' => $staff,
            'amount_total' => $amountTotal,
            'amount_paid' => $amountPaid,
            'balance' => max(0.0, $amountTotal - $amountPaid),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'location' => 'Annie’s Massages Studio',
            'history' => [
                [
                    'label' => $existing === null ? 'Booking created' : 'Booking updated',
                    'meta' => sprintf('%s at %s by admin panel', date('F j, Y'), date('H:i')),
                    'tone' => 'info',
                ],
                [
                    'label' => 'Status set to ' . str_replace('_', ' ', $status),
                    'meta' => 'Workflow ready for confirmations and reminders',
                    'tone' => $status === 'confirmed' ? 'success' : 'warning',
                ],
            ],
        ];

        $_SESSION['booking_overrides'][$bookingId] = $booking;

        return $booking;
    }

    public static function validate(array $payload, ?string $ignoreBookingId = null): array
    {
        $errors = [];

        foreach (['customer_id', 'service_id', 'staff_id', 'date', 'start_time', 'status', 'payment_status'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (!empty($payload['amount_paid']) && !is_numeric((string) $payload['amount_paid'])) {
            $errors['amount_paid'] = 'Amount paid must be a valid number.';
        }

        if ($errors === []) {
            require_once __DIR__ . '/Scheduling.php';

            $service = self::formOptions()['services'][$payload['service_id']] ?? null;

            if ($service !== null) {
                $reason = Scheduling::conflictReason(
                    (string) $payload['staff_id'],
                    (string) $payload['date'],
                    (string) $payload['start_time'],
                    (int) $service['duration'],
                    $ignoreBookingId
                );

                if ($reason !== null) {
                    $errors['start_time'] = $reason;
                }
            }
        }

        return $errors;
    }

    private static function mergedRecords(): array
    {
        $records = self::baseRecords();
        $currentCustomers = self::formOptions()['customers'];
        $currentServices = self::formOptions()['services'];
        $currentStaff = self::formOptions()['staff'];

        foreach ($_SESSION['booking_overrides'] ?? [] as $id => $booking) {
            $records[$id] = $booking;
        }

        foreach ($records as &$record) {
            $customerId = (string) ($record['customer']['id'] ?? '');

            if ($customerId !== '' && isset($currentCustomers[$customerId])) {
                $record['customer'] = $currentCustomers[$customerId];
            }

            $serviceId = (string) ($record['service']['id'] ?? '');

            if ($serviceId !== '' && isset($currentServices[$serviceId])) {
                $record['service'] = $currentServices[$serviceId];
                $record['duration'] = (int) $currentServices[$serviceId]['duration'];
                $record['amount_total'] = (float) $currentServices[$serviceId]['price'];
                $record['balance'] = max(0.0, (float) $record['amount_total'] - (float) $record['amount_paid']);
            }

            $staffId = (string) ($record['staff']['id'] ?? '');

            if ($staffId !== '' && isset($currentStaff[$staffId])) {
                $record['staff'] = $currentStaff[$staffId];
            }
        }
        unset($record);

        return array_values($records);
    }

    private static function nextId(): string
    {
        $max = 1100;

        foreach (self::mergedRecords() as $booking) {
            $numeric = (int) preg_replace('/\D+/', '', $booking['id']);
            $max = max($max, $numeric);
        }

        return 'BK' . ($max + 1);
    }

    private static function baseRecords(): array
    {
        $options = self::formOptions();
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $records = [
            [
                'id' => 'BK1101',
                'reference' => 'BK-1101',
                'date' => $today,
                'time' => '09:00',
                'end_time' => '10:00',
                'duration' => 60,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'channel' => 'front desk',
                'customer' => $options['customers']['cust-rudo'],
                'service' => $options['services']['svc-swedish'],
                'staff' => $options['staff']['stf-tariro'],
                'amount_total' => 45.00,
                'amount_paid' => 45.00,
                'balance' => 0.0,
                'notes' => 'Needs a quiet room after a long travel day.',
                'location' => 'Annie’s Massages Studio',
                'history' => [
                    ['label' => 'Booking confirmed', 'meta' => 'Confirmation email delivered', 'tone' => 'success'],
                    ['label' => 'Customer preference noted', 'meta' => 'Lavender oil requested', 'tone' => 'info'],
                ],
            ],
            [
                'id' => 'BK1102',
                'reference' => 'BK-1102',
                'date' => $today,
                'time' => '10:30',
                'end_time' => '11:45',
                'duration' => 75,
                'status' => 'pending',
                'payment_status' => 'partial',
                'channel' => 'phone',
                'customer' => $options['customers']['cust-lauren'],
                'service' => $options['services']['svc-deep'],
                'staff' => $options['staff']['stf-amanda'],
                'amount_total' => 62.00,
                'amount_paid' => 20.00,
                'balance' => 42.00,
                'notes' => 'Back and shoulder focus after training.',
                'location' => 'Annie’s Massages Studio',
                'history' => [
                    ['label' => 'Deposit captured', 'meta' => format_money(20.00) . ' settled by card', 'tone' => 'warning'],
                    ['label' => 'Pending confirmation', 'meta' => 'Waiting for therapist callback', 'tone' => 'warning'],
                ],
            ],
            [
                'id' => 'BK1103',
                'reference' => 'BK-1103',
                'date' => $today,
                'time' => '12:00',
                'end_time' => '13:30',
                'duration' => 90,
                'status' => 'completed',
                'payment_status' => 'paid',
                'channel' => 'web',
                'customer' => $options['customers']['cust-angela'],
                'service' => $options['services']['svc-hot-stone'],
                'staff' => $options['staff']['stf-shamiso'],
                'amount_total' => 78.00,
                'amount_paid' => 78.00,
                'balance' => 0.0,
                'notes' => 'Minimal scent requested.',
                'location' => 'Annie’s Massages Studio',
                'history' => [
                    ['label' => 'Service completed', 'meta' => 'Ready for post-treatment follow-up', 'tone' => 'success'],
                    ['label' => 'Payment settled', 'meta' => 'Closed in the ledger', 'tone' => 'success'],
                ],
            ],
            [
                'id' => 'BK1104',
                'reference' => 'BK-1104',
                'date' => $today,
                'time' => '14:30',
                'end_time' => '16:00',
                'duration' => 90,
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'channel' => 'concierge',
                'customer' => $options['customers']['cust-james-linda'],
                'service' => $options['services']['svc-couples'],
                'staff' => $options['staff']['stf-shamiso'],
                'amount_total' => 135.00,
                'amount_paid' => 0.0,
                'balance' => 135.00,
                'notes' => 'Dual-room setup and extra towels needed.',
                'location' => 'Couples Suite',
                'history' => [
                    ['label' => 'Room prep requested', 'meta' => 'Ops alerted for dual-room setup', 'tone' => 'info'],
                    ['label' => 'Confirmed', 'meta' => 'Guests arriving 15 minutes early', 'tone' => 'success'],
                ],
            ],
            [
                'id' => 'BK1105',
                'reference' => 'BK-1105',
                'date' => $tomorrow,
                'time' => '11:00',
                'end_time' => '11:45',
                'duration' => 45,
                'status' => 'rescheduled',
                'payment_status' => 'partial',
                'channel' => 'WhatsApp',
                'customer' => $options['customers']['cust-chipo'],
                'service' => $options['services']['svc-aroma'],
                'staff' => $options['staff']['stf-kuda'],
                'amount_total' => 38.00,
                'amount_paid' => 15.00,
                'balance' => 23.00,
                'notes' => 'Shifted from 09:00 to 11:00 on client request.',
                'location' => 'Annie’s Massages Studio',
                'history' => [
                    ['label' => 'Booking rescheduled', 'meta' => 'Client requested a later slot', 'tone' => 'warning'],
                    ['label' => 'Reminder requeued', 'meta' => 'Updated notification schedule', 'tone' => 'info'],
                ],
            ],
            [
                'id' => 'BK1100',
                'reference' => 'BK-1100',
                'date' => $yesterday,
                'time' => '16:00',
                'end_time' => '17:00',
                'duration' => 60,
                'status' => 'cancelled',
                'payment_status' => 'refunded',
                'channel' => 'web',
                'customer' => $options['customers']['cust-rudo'],
                'service' => $options['services']['svc-swedish'],
                'staff' => $options['staff']['stf-tariro'],
                'amount_total' => 45.00,
                'amount_paid' => 45.00,
                'balance' => 0.0,
                'notes' => 'Cancelled after schedule conflict.',
                'location' => 'Annie’s Massages Studio',
                'history' => [
                    ['label' => 'Refund issued', 'meta' => 'Full amount returned to card', 'tone' => 'danger'],
                    ['label' => 'Slot released', 'meta' => 'Availability reopened in calendar', 'tone' => 'info'],
                ],
            ],
        ];

        foreach ($records as &$record) {
            $record['sort_key'] = $record['date'] . ' ' . $record['time'];
        }
        unset($record);

        return array_column($records, null, 'id');
    }
}
