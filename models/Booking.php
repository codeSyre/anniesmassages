<?php declare(strict_types=1);

final class Booking
{
    public static function all(array $filters = []): array
    {
        $bookings = array_values(self::mergedRecords());

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
        return self::mergedRecords()[$id] ?? null;
    }

    public static function formOptions(): array
    {
        require_once __DIR__ . '/Service.php';
        require_once __DIR__ . '/Customer.php';
        require_once __DIR__ . '/Staff.php';

        $services = [
            'svc-swedish' => ['id' => 'svc-swedish', 'name' => 'Swedish Reset', 'duration' => 60, 'price' => 45.00, 'active' => true],
            'svc-deep' => ['id' => 'svc-deep', 'name' => 'Deep Tissue Focus', 'duration' => 75, 'price' => 62.00, 'active' => true],
            'svc-hot-stone' => ['id' => 'svc-hot-stone', 'name' => 'Hot Stone Flow', 'duration' => 90, 'price' => 78.00, 'active' => true],
            'svc-aroma' => ['id' => 'svc-aroma', 'name' => 'Aromatherapy Calm', 'duration' => 45, 'price' => 38.00, 'active' => true],
            'svc-couples' => ['id' => 'svc-couples', 'name' => 'Couples Escape', 'duration' => 90, 'price' => 135.00, 'active' => true],
        ];

        if (class_exists('Service')) {
            foreach (Service::rawAll() as $service) {
                $services[(string) $service['id']] = [
                    'id' => (string) ($service['id'] ?? ''),
                    'name' => (string) ($service['name'] ?? 'Service'),
                    'duration' => (int) ($service['duration'] ?? 60),
                    'price' => (float) ($service['price'] ?? 0),
                    'active' => (bool) ($service['active'] ?? true),
                    'room' => (string) ($service['room'] ?? ''),
                ];
            }
        } else {
            foreach ($_SESSION['service_records'] ?? [] as $id => $service) {
                $services[$id] = [
                    'id' => (string) ($service['id'] ?? $id),
                    'name' => (string) ($service['name'] ?? 'Service'),
                    'duration' => (int) ($service['duration'] ?? 60),
                    'price' => (float) ($service['price'] ?? 0),
                    'active' => (bool) ($service['active'] ?? true),
                    'room' => (string) ($service['room'] ?? ''),
                ];
            }
        }

        $customers = [
            'cust-rudo' => ['id' => 'cust-rudo', 'name' => 'Rudo Ncube', 'phone' => '+263 77 100 2001', 'preference' => 'Light pressure, lavender oil'],
            'cust-lauren' => ['id' => 'cust-lauren', 'name' => 'Lauren Price', 'phone' => '+263 77 100 2002', 'preference' => 'Deep tissue shoulders'],
            'cust-angela' => ['id' => 'cust-angela', 'name' => 'Angela Banda', 'phone' => '+263 77 100 2003', 'preference' => 'Warm room, minimal scent'],
            'cust-james-linda' => ['id' => 'cust-james-linda', 'name' => 'James & Linda', 'phone' => '+263 77 100 2004', 'preference' => 'Dual room setup'],
            'cust-chipo' => ['id' => 'cust-chipo', 'name' => 'Chipo Nyoni', 'phone' => '+263 77 100 2005', 'preference' => 'Midday availability'],
        ];

        if (class_exists('Customer')) {
            foreach (Customer::rawAll() as $customer) {
                $customers[(string) $customer['id']] = [
                    'id' => (string) ($customer['id'] ?? ''),
                    'name' => (string) ($customer['name'] ?? 'Guest'),
                    'phone' => (string) ($customer['phone'] ?? ''),
                    'email' => (string) ($customer['email'] ?? ''),
                    'preference' => (string) ($customer['preference'] ?? ''),
                    'status' => (string) ($customer['status'] ?? 'active'),
                ];
            }
        } else {
            foreach ($_SESSION['customer_records'] ?? [] as $id => $customer) {
                $customers[$id] = [
                    'id' => (string) ($customer['id'] ?? $id),
                    'name' => (string) ($customer['name'] ?? 'Guest'),
                    'phone' => (string) ($customer['phone'] ?? ''),
                    'email' => (string) ($customer['email'] ?? ''),
                    'preference' => (string) ($customer['preference'] ?? ''),
                    'status' => (string) ($customer['status'] ?? 'active'),
                ];
            }
        }

        $staff = [
            'stf-tariro' => ['id' => 'stf-tariro', 'name' => 'Tariro Moyo', 'specialty' => 'Recovery and sports'],
            'stf-amanda' => ['id' => 'stf-amanda', 'name' => 'Amanda Sibanda', 'specialty' => 'Deep tissue and posture work'],
            'stf-shamiso' => ['id' => 'stf-shamiso', 'name' => 'Shamiso Chuma', 'specialty' => 'Relaxation and hot stone'],
            'stf-kuda' => ['id' => 'stf-kuda', 'name' => 'Kuda Mlambo', 'specialty' => 'Aromatherapy and mobile visits'],
        ];

        if (class_exists('Staff')) {
            $staff = [];

            foreach (Staff::rawTherapists() as $member) {
                $staff[(string) $member['id']] = [
                    'id' => (string) ($member['id'] ?? ''),
                    'name' => (string) ($member['name'] ?? 'Therapist'),
                    'specialty' => (string) ($member['specialty'] ?? ''),
                    'role_type' => (string) ($member['role_type'] ?? ''),
                ];
            }
        } else {
            foreach ($_SESSION['staff_records'] ?? [] as $id => $member) {
                if (!empty($member['role_type']) && !str_contains(strtolower((string) $member['role_type']), 'therapist')) {
                    continue;
                }

                $staff[$id] = [
                    'id' => (string) ($member['id'] ?? $id),
                    'name' => (string) ($member['name'] ?? 'Therapist'),
                    'specialty' => (string) ($member['specialty'] ?? ''),
                    'role_type' => (string) ($member['role_type'] ?? ''),
                ];
            }
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

    public static function save(array $payload, ?string $id = null): ?array
    {
        $options = self::formOptions();
        $service = $options['services'][$payload['service_id']] ?? reset($options['services']);
        $customer = $options['customers'][$payload['customer_id']] ?? reset($options['customers']);
        $staff = $options['staff'][$payload['staff_id']] ?? reset($options['staff']);
        $existing = $id !== null ? self::find($id) : null;

        if (!is_array($service) || !is_array($customer) || !is_array($staff)) {
            return null;
        }

        $bookingId = $existing['id'] ?? self::nextId();
        $reference = $existing['reference'] ?? self::nextReference();
        $date = (string) $payload['date'];
        $start = (string) $payload['start_time'];
        $duration = (int) ($service['duration'] ?? 60);
        $end = date('H:i:s', strtotime($start . ' +' . $duration . ' minutes'));
        $amountTotal = round((float) ($service['price'] ?? 0), 2);
        $amountPaid = min($amountTotal, max(0.0, (float) ($payload['amount_paid'] ?? 0)));
        $status = (string) ($payload['status'] ?? 'pending');
        $paymentStatus = (string) ($payload['payment_status'] ?? 'unpaid');

        if ($amountPaid >= $amountTotal) {
            $paymentStatus = 'paid';
        } elseif ($amountPaid > 0.0 && $paymentStatus === 'unpaid') {
            $paymentStatus = 'partial';
        }

        $booking = [
            'id' => $bookingId,
            'reference' => $reference,
            'date' => $date,
            'time' => date('H:i', strtotime($start)),
            'end_time' => date('H:i', strtotime($end)),
            'duration' => $duration,
            'sort_key' => $date . ' ' . date('H:i', strtotime($start)),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'channel' => trim((string) ($payload['channel'] ?? 'admin')),
            'customer' => $customer,
            'service' => $service,
            'staff' => $staff,
            'amount_total' => $amountTotal,
            'amount_paid' => round($amountPaid, 2),
            'balance' => round(max(0.0, $amountTotal - $amountPaid), 2),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'location' => self::locationForService($service, $existing),
            'history' => $existing['history'] ?? [],
        ];

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            mysqli_begin_transaction($connection);

            try {
                $statement = self::prepare(
                    $connection,
                    'INSERT INTO bookings (
                        id, reference, customer_id, service_id, staff_id,
                        customer_name_snapshot, customer_phone_snapshot, customer_email_snapshot,
                        service_name_snapshot, service_price_snapshot, staff_name_snapshot,
                        appointment_date, start_time, end_time, duration_minutes, status, payment_status,
                        channel, amount_total, amount_paid, balance, notes, location, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        customer_id = VALUES(customer_id),
                        service_id = VALUES(service_id),
                        staff_id = VALUES(staff_id),
                        customer_name_snapshot = VALUES(customer_name_snapshot),
                        customer_phone_snapshot = VALUES(customer_phone_snapshot),
                        customer_email_snapshot = VALUES(customer_email_snapshot),
                        service_name_snapshot = VALUES(service_name_snapshot),
                        service_price_snapshot = VALUES(service_price_snapshot),
                        staff_name_snapshot = VALUES(staff_name_snapshot),
                        appointment_date = VALUES(appointment_date),
                        start_time = VALUES(start_time),
                        end_time = VALUES(end_time),
                        duration_minutes = VALUES(duration_minutes),
                        status = VALUES(status),
                        payment_status = VALUES(payment_status),
                        channel = VALUES(channel),
                        amount_total = VALUES(amount_total),
                        amount_paid = VALUES(amount_paid),
                        balance = VALUES(balance),
                        notes = VALUES(notes),
                        location = VALUES(location),
                        updated_at = NOW()',
                    str_repeat('s', 23),
                    [
                        $booking['id'],
                        $booking['reference'],
                        (string) $customer['id'],
                        (string) $service['id'],
                        (string) $staff['id'],
                        (string) ($customer['name'] ?? ''),
                        (string) ($customer['phone'] ?? ''),
                        (string) ($customer['email'] ?? ''),
                        (string) ($service['name'] ?? ''),
                        (string) $booking['amount_total'],
                        (string) ($staff['name'] ?? ''),
                        $booking['date'],
                        date('H:i:s', strtotime($start)),
                        $end,
                        (string) $booking['duration'],
                        $booking['status'],
                        $booking['payment_status'],
                        $booking['channel'],
                        (string) $booking['amount_total'],
                        (string) $booking['amount_paid'],
                        (string) $booking['balance'],
                        $booking['notes'],
                        $booking['location'],
                    ]
                );

                if (!$statement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to save booking.');
                }
                $statement->close();

                self::appendHistory(
                    $connection,
                    $booking['id'],
                    $existing === null ? 'Booking created' : 'Booking updated',
                    sprintf('%s at %s by admin panel', date('F j, Y'), date('H:i')),
                    'info'
                );

                self::appendHistory(
                    $connection,
                    $booking['id'],
                    'Status set to ' . str_replace('_', ' ', $booking['status']),
                    sprintf(
                        '%s with %s on %s at %s',
                        (string) ($service['name'] ?? 'Service'),
                        (string) ($staff['name'] ?? 'Therapist'),
                        date('j M Y', strtotime($booking['date'])),
                        $booking['time']
                    ),
                    $booking['status'] === 'confirmed' ? 'success' : 'warning'
                );

                mysqli_commit($connection);

                return self::find($booking['id']);
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Booking save failed: ' . $exception->getMessage());

                return null;
            }
        }

        if (function_exists('db_configured') && db_configured()) {
            return null;
        }

        $_SESSION['booking_overrides'][$bookingId] = $booking;

        return $booking;
    }

    public static function applyPaymentSummary(string $bookingId, float $amountPaid, string $paymentStatus, ?array $historyEntry = null): ?array
    {
        $booking = self::find($bookingId);

        if ($booking === null) {
            return null;
        }

        $updated = $booking;
        $updated['amount_paid'] = round(max(0.0, $amountPaid), 2);
        $updated['payment_status'] = $paymentStatus;
        $updated['balance'] = $paymentStatus === 'refunded'
            ? 0.0
            : round(max(0.0, (float) $booking['amount_total'] - (float) $updated['amount_paid']), 2);

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            try {
                $statement = self::prepare(
                    $connection,
                    'UPDATE bookings SET amount_paid = ?, payment_status = ?, balance = ?, updated_at = NOW() WHERE id = ?',
                    'dsds',
                    [
                        (float) $updated['amount_paid'],
                        $updated['payment_status'],
                        (float) $updated['balance'],
                        $bookingId,
                    ]
                );

                if (!$statement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to update booking payment summary.');
                }
                $statement->close();

                if (is_array($historyEntry)) {
                    self::appendHistory(
                        $connection,
                        $bookingId,
                        (string) ($historyEntry['label'] ?? 'Booking updated'),
                        (string) ($historyEntry['meta'] ?? ''),
                        (string) ($historyEntry['tone'] ?? 'info')
                    );
                }
            } catch (Throwable $exception) {
                error_log('Booking payment sync failed: ' . $exception->getMessage());

                return null;
            }

            return self::find($bookingId);
        }

        if (is_array($historyEntry)) {
            $updated['history'][] = $historyEntry;
        }

        $_SESSION['booking_overrides'][$bookingId] = $updated;

        return $updated;
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
            $staffId = (string) ($payload['staff_id'] ?? '');
            $staff = self::formOptions()['staff'][$staffId] ?? null;

            if (!is_array($staff)) {
                $errors['staff_id'] = 'Only staff with the therapist role can be assigned therapy sessions.';
            }
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
        $databaseRecords = self::databaseRecords();

        if ($databaseRecords !== null) {
            return $databaseRecords;
        }

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
            }

            $staffId = (string) ($record['staff']['id'] ?? '');

            if ($staffId !== '' && isset($currentStaff[$staffId])) {
                $record['staff'] = $currentStaff[$staffId];
            }

            $record['sort_key'] = $record['date'] . ' ' . $record['time'];
        }
        unset($record);

        return $records;
    }

    private static function databaseRecords(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $options = self::formOptions();
        $customerOptions = $options['customers'];
        $serviceOptions = $options['services'];
        $staffOptions = $options['staff'];

        $result = $connection->query('SELECT * FROM bookings ORDER BY appointment_date ASC, start_time ASC, reference ASC');

        if (!$result instanceof mysqli_result) {
            error_log('Unable to fetch booking records from database: ' . $connection->error);

            return [];
        }

        $records = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $booking = self::mapDatabaseRow($row, $customerOptions, $serviceOptions, $staffOptions);
            $records[$booking['id']] = $booking;
        }
        $result->free();

        if ($records === []) {
            return [];
        }

        $historyResult = $connection->query(
            'SELECT booking_id, event_label, event_meta, tone, created_at
             FROM booking_history
             ORDER BY created_at DESC, id DESC'
        );

        if ($historyResult instanceof mysqli_result) {
            while ($row = $historyResult->fetch_assoc()) {
                $bookingId = (string) ($row['booking_id'] ?? '');

                if ($bookingId === '' || !isset($records[$bookingId])) {
                    continue;
                }

                $records[$bookingId]['history'][] = [
                    'label' => (string) ($row['event_label'] ?? 'Booking updated'),
                    'meta' => trim((string) ($row['event_meta'] ?? '')),
                    'tone' => (string) ($row['tone'] ?? 'info'),
                ];
            }
            $historyResult->free();
        }

        return $records;
    }

    private static function mapDatabaseRow(
        array $row,
        array $customerOptions,
        array $serviceOptions,
        array $staffOptions
    ): array {
        $customerId = (string) ($row['customer_id'] ?? '');
        $serviceId = (string) ($row['service_id'] ?? '');
        $staffId = (string) ($row['staff_id'] ?? '');

        $customer = $customerOptions[$customerId] ?? [
            'id' => $customerId,
            'name' => trim((string) ($row['customer_name_snapshot'] ?? 'Guest')) ?: 'Guest',
            'phone' => trim((string) ($row['customer_phone_snapshot'] ?? '')),
            'email' => trim((string) ($row['customer_email_snapshot'] ?? '')),
            'preference' => '',
            'status' => 'active',
        ];

        $service = $serviceOptions[$serviceId] ?? [
            'id' => $serviceId,
            'name' => trim((string) ($row['service_name_snapshot'] ?? 'Service')) ?: 'Service',
            'duration' => (int) ($row['duration_minutes'] ?? 60),
            'price' => (float) ($row['service_price_snapshot'] ?? 0),
            'active' => true,
            'room' => trim((string) ($row['location'] ?? '')),
        ];

        $staff = $staffOptions[$staffId] ?? [
            'id' => $staffId,
            'name' => trim((string) ($row['staff_name_snapshot'] ?? 'Therapist')) ?: 'Therapist',
            'specialty' => '',
            'role_type' => 'therapist',
        ];

        $date = (string) ($row['appointment_date'] ?? date('Y-m-d'));
        $time = date('H:i', strtotime((string) ($row['start_time'] ?? '09:00:00')));
        $endTime = date('H:i', strtotime((string) ($row['end_time'] ?? '10:00:00')));
        $amountTotal = round((float) ($row['amount_total'] ?? $row['service_price_snapshot'] ?? 0), 2);
        $amountPaid = round((float) ($row['amount_paid'] ?? 0), 2);
        $paymentStatus = (string) ($row['payment_status'] ?? 'unpaid');

        return [
            'id' => (string) ($row['id'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'date' => $date,
            'time' => $time,
            'end_time' => $endTime,
            'duration' => (int) ($row['duration_minutes'] ?? ($service['duration'] ?? 60)),
            'sort_key' => $date . ' ' . $time,
            'status' => (string) ($row['status'] ?? 'pending'),
            'payment_status' => $paymentStatus,
            'channel' => trim((string) ($row['channel'] ?? 'admin')) ?: 'admin',
            'customer' => $customer,
            'service' => $service,
            'staff' => $staff,
            'amount_total' => $amountTotal,
            'amount_paid' => $amountPaid,
            'balance' => $paymentStatus === 'refunded'
                ? 0.0
                : round((float) ($row['balance'] ?? max(0.0, $amountTotal - $amountPaid)), 2),
            'notes' => trim((string) ($row['notes'] ?? '')),
            'location' => trim((string) ($row['location'] ?? 'Annie’s Massages Studio')) ?: 'Annie’s Massages Studio',
            'history' => [],
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

    private static function nextReference(): string
    {
        $connection = self::connection();

        if ($connection instanceof mysqli) {
            $result = $connection->query(
                "SELECT reference
                 FROM bookings
                 WHERE reference REGEXP '^BK-[0-9]+$'
                 ORDER BY CAST(SUBSTRING(reference, 4) AS UNSIGNED) DESC
                 LIMIT 1"
            );

            if ($result instanceof mysqli_result) {
                $row = $result->fetch_assoc() ?: [];
                $result->free();
                $lastReference = (string) ($row['reference'] ?? '');

                if ($lastReference !== '') {
                    $numeric = (int) preg_replace('/\D+/', '', $lastReference);

                    return 'BK-' . ($numeric + 1);
                }
            }
        }

        $max = 1100;

        foreach (self::mergedRecords() as $booking) {
            $reference = (string) ($booking['reference'] ?? '');
            $numeric = (int) preg_replace('/\D+/', '', $reference);
            $max = max($max, $numeric);
        }

        return 'BK-' . ($max + 1);
    }

    private static function locationForService(array $service, ?array $existing = null): string
    {
        $room = trim((string) ($service['room'] ?? ''));

        if ($room !== '') {
            return $room;
        }

        return trim((string) ($existing['location'] ?? 'Annie’s Massages Studio')) ?: 'Annie’s Massages Studio';
    }

    private static function appendHistory(
        mysqli $connection,
        string $bookingId,
        string $label,
        string $meta,
        string $tone
    ): void {
        $statement = self::prepare(
            $connection,
            'INSERT INTO booking_history (id, booking_id, event_label, event_meta, tone, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            'sssss',
            [self::nextId(), $bookingId, $label, $meta, $tone]
        );

        if (!$statement instanceof mysqli_stmt) {
            throw new RuntimeException('Unable to save booking history.');
        }

        $statement->close();
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $connection, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $statement = $connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('Booking statement prepare failed: ' . $connection->error);

            return null;
        }

        if ($params !== []) {
            $references = [];

            foreach ($params as $index => $value) {
                $params[$index] = $value;
                $references[] = &$params[$index];
            }

            array_unshift($references, $types);

            if (!call_user_func_array([$statement, 'bind_param'], $references)) {
                error_log('Booking statement bind failed: ' . $statement->error);
                $statement->close();

                return null;
            }
        }

        if (!$statement->execute()) {
            error_log('Booking statement execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function baseRecords(): array
    {
        $options = self::formOptions();
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $customerRudo = self::optionOrFallback($options['customers'], 'cust-rudo', ['id' => 'cust-rudo', 'name' => 'Rudo Ncube', 'phone' => '+263 77 100 2001', 'preference' => 'Light pressure, lavender oil']);
        $customerLauren = self::optionOrFallback($options['customers'], 'cust-lauren', ['id' => 'cust-lauren', 'name' => 'Lauren Price', 'phone' => '+263 77 100 2002', 'preference' => 'Deep tissue shoulders']);
        $customerAngela = self::optionOrFallback($options['customers'], 'cust-angela', ['id' => 'cust-angela', 'name' => 'Angela Banda', 'phone' => '+263 77 100 2003', 'preference' => 'Warm room, minimal scent']);
        $customerJamesLinda = self::optionOrFallback($options['customers'], 'cust-james-linda', ['id' => 'cust-james-linda', 'name' => 'James Linda', 'phone' => '+263 77 100 2004', 'preference' => 'Dual room setup']);
        $customerChipo = self::optionOrFallback($options['customers'], 'cust-chipo', ['id' => 'cust-chipo', 'name' => 'Chipo Nyoni', 'phone' => '+263 77 100 2005', 'preference' => 'Midday availability']);
        $staffTariro = self::optionOrFallback($options['staff'], 'stf-tariro', ['id' => 'stf-tariro', 'name' => 'Tariro Moyo', 'specialty' => 'Recovery and sports', 'role_type' => 'therapist']);
        $staffAmanda = self::optionOrFallback($options['staff'], 'stf-amanda', ['id' => 'stf-amanda', 'name' => 'Amanda Sibanda', 'specialty' => 'Deep tissue and posture work', 'role_type' => 'therapist']);
        $staffShamiso = self::optionOrFallback($options['staff'], 'stf-shamiso', ['id' => 'stf-shamiso', 'name' => 'Shamiso Chuma', 'specialty' => 'Relaxation and hot stone', 'role_type' => 'therapist']);
        $staffKuda = self::optionOrFallback($options['staff'], 'stf-kuda', ['id' => 'stf-kuda', 'name' => 'Kuda Mlambo', 'specialty' => 'Aromatherapy and mobile visits', 'role_type' => 'therapist']);

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
                'customer' => $customerRudo,
                'service' => $options['services']['svc-swedish'],
                'staff' => $staffTariro,
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
                'customer' => $customerLauren,
                'service' => $options['services']['svc-deep'],
                'staff' => $staffAmanda,
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
                'customer' => $customerAngela,
                'service' => $options['services']['svc-hot-stone'],
                'staff' => $staffShamiso,
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
                'customer' => $customerJamesLinda,
                'service' => $options['services']['svc-couples'],
                'staff' => $staffShamiso,
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
                'customer' => $customerChipo,
                'service' => $options['services']['svc-aroma'],
                'staff' => $staffKuda,
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
                'customer' => $customerRudo,
                'service' => $options['services']['svc-swedish'],
                'staff' => $staffTariro,
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

    private static function optionOrFallback(array $options, string $id, array $fallback): array
    {
        $option = $options[$id] ?? null;

        return is_array($option) ? $option : $fallback;
    }
}
