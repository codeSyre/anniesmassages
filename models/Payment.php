<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Payment
{
    public static function all(array $filters = []): array
    {
        $payments = array_values(self::mergedRecords());
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $method = (string) ($filters['method'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');
        $staffId = (string) ($filters['staff_id'] ?? 'all');
        $bookingId = (string) ($filters['booking_id'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $payments = array_values(array_filter($payments, static function (array $payment) use ($search, $method, $status, $staffId, $bookingId, $dateFrom, $dateTo): bool {
            if ($method !== 'all' && $payment['method'] !== $method) {
                return false;
            }

            if ($status !== 'all' && $payment['payment_status'] !== $status) {
                return false;
            }

            if ($staffId !== 'all' && ($payment['staff']['id'] ?? '') !== $staffId) {
                return false;
            }

            if ($bookingId !== '' && $payment['booking_id'] !== $bookingId) {
                return false;
            }

            if ($dateFrom !== '' && $payment['payment_date'] < $dateFrom) {
                return false;
            }

            if ($dateTo !== '' && $payment['payment_date'] > $dateTo) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $payment['reference'],
                $payment['booking_reference'],
                $payment['customer']['name'] ?? '',
                $payment['service']['name'] ?? '',
                $payment['staff']['name'] ?? '',
                $payment['method'],
                $payment['note'],
            ]));

            return str_contains($haystack, $search);
        }));

        usort($payments, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

        return $payments;
    }

    public static function methods(): array
    {
        return ['ecocash', 'innbucks', 'zimswitch', 'bank_transfer', 'visa_mastercard', 'cash'];
    }

    public static function methodLabels(): array
    {
        return [
            'ecocash' => 'Ecocash',
            'innbucks' => 'Innbucks',
            'zimswitch' => 'Zimswitch',
            'bank_transfer' => 'Bank Transfer',
            'visa_mastercard' => 'Visa/Mastercard',
            'cash' => 'Cash',
            'mobile_money' => 'Ecocash',
            'card' => 'Visa/Mastercard',
            'other' => 'Cash',
        ];
    }

    public static function methodLabel(string $method): string
    {
        $labels = self::methodLabels();

        return $labels[$method] ?? ucwords(str_replace('_', ' ', $method));
    }

    public static function statuses(): array
    {
        return ['unpaid', 'partial', 'paid', 'refunded'];
    }

    public static function bookingOptions(): array
    {
        return Booking::all();
    }

    public static function staffOptions(): array
    {
        $staff = array_values(Booking::formOptions()['staff']);
        usort($staff, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $staff;
    }

    public static function stats(): array
    {
        $today = date('Y-m-d');
        $reconciliation = self::reconciliation($today);
        $bookings = Booking::all();
        $partialBookings = array_filter($bookings, static fn (array $booking): bool => $booking['payment_status'] === 'partial');
        $outstanding = array_sum(array_map(static function (array $booking): float {
            if ($booking['payment_status'] === 'refunded') {
                return 0.0;
            }

            return (float) $booking['balance'];
        }, $bookings));
        $refunds = array_sum(array_map(static function (array $payment): float {
            return $payment['payment_status'] === 'refunded' ? (float) $payment['amount'] : 0.0;
        }, self::all()));

        return [
            ['label' => 'Collected today', 'value' => format_money((float) $reconciliation['net_total']), 'tone' => 'success'],
            ['label' => 'Partial-payment bookings', 'value' => (string) count($partialBookings), 'tone' => 'warning'],
            ['label' => 'Outstanding balance', 'value' => format_money($outstanding), 'tone' => 'danger'],
            ['label' => 'Refunded value', 'value' => format_money($refunds), 'tone' => 'info'],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::mergedRecords() as $payment) {
            if ($payment['id'] === $id) {
                return $payment;
            }
        }

        return null;
    }

    public static function forBooking(string $bookingId): array
    {
        return self::all(['booking_id' => $bookingId]);
    }

    public static function bookingDetails(string $bookingId): ?array
    {
        $booking = Booking::find($bookingId);

        if ($booking === null) {
            return null;
        }

        $payments = self::forBooking($bookingId);
        $methodBreakdown = [];

        foreach (self::methods() as $method) {
            $methodBreakdown[$method] = 0.0;
        }

        foreach ($payments as $payment) {
            if (!array_key_exists($payment['method'], $methodBreakdown)) {
                $methodBreakdown[$payment['method']] = 0.0;
            }

            $methodBreakdown[$payment['method']] += (float) $payment['amount'];
        }

        return [
            'booking' => $booking,
            'payments' => $payments,
            'totals' => [
                'captured' => (float) $booking['amount_paid'],
                'balance' => (float) $booking['balance'],
                'status' => (string) $booking['payment_status'],
                'payment_count' => count($payments),
                'latest_payment_date' => $payments[0]['payment_date'] ?? null,
            ],
            'method_breakdown' => $methodBreakdown,
        ];
    }

    public static function reconciliation(string $date): array
    {
        $payments = self::all([
            'date_from' => $date,
            'date_to' => $date,
        ]);
        $methodTotals = [];
        $staffTotals = [];
        $gross = 0.0;
        $refunds = 0.0;

        foreach (self::methods() as $method) {
            $methodTotals[$method] = 0.0;
        }

        foreach ($payments as $payment) {
            $amount = (float) $payment['amount'];

            if (!array_key_exists($payment['method'], $methodTotals)) {
                $methodTotals[$payment['method']] = 0.0;
            }

            $methodTotals[$payment['method']] += $amount;

            if ($payment['payment_status'] === 'refunded') {
                $refunds += $amount;
            } else {
                $gross += $amount;
                $staffName = $payment['staff']['name'] ?? 'Unassigned';
                $staffTotals[$staffName] = ($staffTotals[$staffName] ?? 0.0) + $amount;
            }
        }

        arsort($staffTotals);

        $outstandingBookings = array_values(array_filter(Booking::all(), static function (array $booking) use ($date): bool {
            return $booking['date'] === $date
                && (float) $booking['balance'] > 0
                && $booking['payment_status'] !== 'refunded';
        }));

        return [
            'date' => $date,
            'payments' => $payments,
            'method_totals' => $methodTotals,
            'staff_totals' => $staffTotals,
            'gross_total' => $gross,
            'refund_total' => $refunds,
            'net_total' => $gross - $refunds,
            'outstanding_bookings' => $outstandingBookings,
        ];
    }

    public static function exportRows(array $filters = []): array
    {
        return array_map(static function (array $payment): array {
            return [
                'Payment Reference' => $payment['reference'],
                'Payment Date' => $payment['payment_date'],
                'Booking Reference' => $payment['booking_reference'],
                'Customer' => $payment['customer']['name'] ?? '',
                'Service' => $payment['service']['name'] ?? '',
                'Therapist' => $payment['staff']['name'] ?? '',
                'Method' => self::methodLabel((string) $payment['method']),
                'Amount' => number_format((float) $payment['amount'], 2, '.', ''),
                'Status' => $payment['payment_status'],
                'Note' => $payment['note'],
            ];
        }, self::all($filters));
    }

    public static function validate(array $payload): array
    {
        $errors = [];

        foreach (['booking_id', 'payment_date', 'method', 'amount'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (!is_numeric((string) ($payload['amount'] ?? '')) || (float) ($payload['amount'] ?? 0) <= 0) {
            $errors['amount'] = 'Amount must be greater than zero.';
        }

        if (!in_array((string) ($payload['method'] ?? ''), self::methods(), true)) {
            $errors['method'] = 'Select a valid payment method.';
        }

        $bookingId = trim((string) ($payload['booking_id'] ?? ''));
        $booking = $bookingId !== '' ? Booking::find($bookingId) : null;

        if ($booking === null) {
            $errors['booking_id'] = 'Select a valid booking.';
        } elseif (in_array($booking['status'], ['cancelled', 'no_show'], true)) {
            $errors['booking_id'] = 'Payments cannot be recorded against cancelled or no-show bookings.';
        } elseif ($booking['payment_status'] === 'refunded') {
            $errors['booking_id'] = 'This booking has already been refunded and closed in the ledger.';
        } elseif ((float) $booking['balance'] <= 0) {
            $errors['amount'] = 'This booking is already fully settled.';
        }

        if ($booking !== null && $errors === []) {
            $effectivePaid = self::effectivePaidForBooking($booking['id']);
            $allowed = max(0.0, (float) $booking['amount_total'] - $effectivePaid);

            if ((float) $payload['amount'] > $allowed + 0.0001) {
                $errors['amount'] = 'This payment is larger than the outstanding balance.';
            }
        }

        return $errors;
    }

    public static function save(array $payload): ?array
    {
        $bookingId = (string) $payload['booking_id'];
        $booking = Booking::find($bookingId);

        if ($booking === null) {
            throw new RuntimeException('Booking not found.');
        }

        $connection = self::connection();

        if ($connection instanceof mysqli) {
            mysqli_begin_transaction($connection);

            try {
                $existingRows = self::databaseRecordsForBooking($connection, $bookingId);

                if ($existingRows === [] && (float) $booking['amount_paid'] > 0.0) {
                    $openingReference = self::nextReference($connection);
                    $openingStatement = self::prepare(
                        $connection,
                        'INSERT INTO payments (
                            id, reference, booking_id, payment_date, method, amount, payment_status, note, recorded_by,
                            external_reference, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())',
                        'sssssdsss',
                        [
                            self::nextId(),
                            $openingReference,
                            $booking['id'],
                            $booking['date'],
                            'cash',
                            round((float) $booking['amount_paid'], 2),
                            (string) $booking['payment_status'],
                            'Imported from booking intake before the ledger module was opened.',
                            'System import',
                        ]
                    );

                    if (!$openingStatement instanceof mysqli_stmt) {
                        throw new RuntimeException('Unable to import the opening payment row.');
                    }
                    $openingStatement->close();
                }

                $paymentId = self::nextId();
                $paymentReference = self::nextReference($connection);
                $paymentStatement = self::prepare(
                    $connection,
                    'INSERT INTO payments (
                        id, reference, booking_id, payment_date, method, amount, payment_status, note, recorded_by,
                        external_reference, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())',
                    'sssssdsss',
                    [
                        $paymentId,
                        $paymentReference,
                        $booking['id'],
                        (string) $payload['payment_date'],
                        (string) $payload['method'],
                        round((float) $payload['amount'], 2),
                        'partial',
                        trim((string) ($payload['note'] ?? '')),
                        trim((string) ($payload['recorded_by'] ?? 'Admin panel')),
                    ]
                );

                if (!$paymentStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to save payment.');
                }
                $paymentStatement->close();

                $updatedPayments = self::databaseRecordsForBooking($connection, $bookingId);
                $amountPaid = array_sum(array_map(static fn (array $payment): float => (float) ($payment['amount'] ?? 0), $updatedPayments));
                $paymentStatus = self::statusForTotals((float) $booking['amount_total'], $amountPaid);

                $updatedBooking = Booking::applyPaymentSummary($bookingId, $amountPaid, $paymentStatus, [
                    'label' => 'Payment recorded',
                    'meta' => sprintf(
                        '%s captured via %s on %s',
                        format_money((float) $payload['amount']),
                        str_replace('_', ' ', (string) $payload['method']),
                        date('j M Y', strtotime((string) $payload['payment_date']))
                    ),
                    'tone' => $paymentStatus === 'paid' ? 'success' : 'warning',
                ]);

                if (!is_array($updatedBooking)) {
                    throw new RuntimeException('Unable to sync the booking payment summary.');
                }

                mysqli_commit($connection);

                return self::find($paymentId);
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Payment save failed: ' . $exception->getMessage());

                return null;
            }
        }

        if (function_exists('db_configured') && db_configured()) {
            return null;
        }

        $records = $_SESSION['payment_records'] ?? [];

        if (self::persistentPaymentsForBooking($bookingId) === [] && (float) $booking['amount_paid'] > 0) {
            $openingId = self::nextLegacyIdFromRecords($records);
            $records[$openingId] = [
                'id' => $openingId,
                'reference' => 'PMT-' . preg_replace('/\D+/', '', $openingId),
                'booking_id' => $booking['id'],
                'payment_date' => $booking['date'],
                'method' => 'other',
                'amount' => round((float) $booking['amount_paid'], 2),
                'note' => 'Imported from booking intake before the ledger module was opened.',
                'recorded_by' => 'System import',
                'payment_status' => $booking['payment_status'],
            ];
        }

        $paymentId = self::nextLegacyIdFromRecords($records);
        $records[$paymentId] = [
            'id' => $paymentId,
            'reference' => 'PMT-' . preg_replace('/\D+/', '', $paymentId),
            'booking_id' => $booking['id'],
            'payment_date' => (string) $payload['payment_date'],
            'method' => (string) $payload['method'],
            'amount' => round((float) $payload['amount'], 2),
            'note' => trim((string) ($payload['note'] ?? '')),
            'recorded_by' => trim((string) ($payload['recorded_by'] ?? 'Admin panel')),
            'payment_status' => 'partial',
        ];

        $_SESSION['payment_records'] = $records;

        $updatedPayments = self::persistentPaymentsForBooking($bookingId);
        $amountPaid = array_sum(array_map(static fn (array $payment): float => (float) $payment['amount'], $updatedPayments));
        $paymentStatus = self::statusForTotals((float) $booking['amount_total'], $amountPaid);

        Booking::applyPaymentSummary($bookingId, $amountPaid, $paymentStatus, [
            'label' => 'Payment recorded',
            'meta' => sprintf(
                '%s captured via %s on %s',
                format_money((float) $payload['amount']),
                str_replace('_', ' ', (string) $payload['method']),
                date('j M Y', strtotime((string) $payload['payment_date']))
            ),
            'tone' => $paymentStatus === 'paid' ? 'success' : 'warning',
        ]);

        return self::find($paymentId) ?? self::normalizeRecord($records[$paymentId], Booking::find($bookingId));
    }

    private static function effectivePaidForBooking(string $bookingId): float
    {
        return array_sum(array_map(static fn (array $payment): float => (float) $payment['amount'], self::forBooking($bookingId)));
    }

    private static function persistentPaymentsForBooking(string $bookingId): array
    {
        return array_values(array_filter(
            array_merge(array_values(self::baseRecords()), array_values($_SESSION['payment_records'] ?? [])),
            static fn (array $payment): bool => (string) ($payment['booking_id'] ?? '') === $bookingId
        ));
    }

    private static function statusForTotals(float $amountTotal, float $amountPaid): string
    {
        if ($amountPaid <= 0.0) {
            return 'unpaid';
        }

        if ($amountPaid >= $amountTotal) {
            return 'paid';
        }

        return 'partial';
    }

    private static function mergedRecords(): array
    {
        $databaseRecords = self::databaseRecords();

        if ($databaseRecords !== null) {
            $records = [];

            foreach ($databaseRecords as $payment) {
                $records[$payment['id']] = $payment;
            }

            $bookingCoverage = [];

            foreach ($records as $payment) {
                $bookingCoverage[(string) ($payment['booking_id'] ?? '')] = true;
            }

            foreach (Booking::all() as $booking) {
                if ((float) $booking['amount_paid'] <= 0.0 || isset($bookingCoverage[$booking['id']])) {
                    continue;
                }

                $syntheticId = 'PAYI-' . $booking['id'];
                $records[$syntheticId] = self::normalizeRecord([
                    'id' => $syntheticId,
                    'reference' => 'PMT-IMPORT',
                    'booking_id' => $booking['id'],
                    'payment_date' => $booking['date'],
                    'method' => 'cash',
                    'amount' => round((float) $booking['amount_paid'], 2),
                    'note' => 'Imported from booking intake before the ledger module was opened.',
                    'recorded_by' => 'System import',
                    'payment_status' => $booking['payment_status'],
                ], $booking);
            }

            return array_values($records);
        }

        $records = self::baseRecords();

        foreach ($_SESSION['payment_records'] ?? [] as $id => $payment) {
            $records[$id] = $payment;
        }

        $bookingCoverage = [];

        foreach ($records as $payment) {
            $bookingCoverage[(string) ($payment['booking_id'] ?? '')] = true;
        }

        foreach (Booking::all() as $booking) {
            if ((float) $booking['amount_paid'] <= 0 || isset($bookingCoverage[$booking['id']])) {
                continue;
            }

            $syntheticId = 'PAYI-' . $booking['id'];
            $records[$syntheticId] = [
                'id' => $syntheticId,
                'reference' => 'PMT-IMPORT',
                'booking_id' => $booking['id'],
                'payment_date' => $booking['date'],
                'method' => 'cash',
                'amount' => round((float) $booking['amount_paid'], 2),
                'note' => 'Imported from booking intake before the ledger module was opened.',
                'recorded_by' => 'System import',
                'payment_status' => $booking['payment_status'],
            ];
        }

        return array_map(static fn (array $payment): array => self::normalizeRecord($payment), array_values($records));
    }

    private static function databaseRecords(): ?array
    {
        $connection = self::connection();

        if (!$connection instanceof mysqli) {
            return null;
        }

        $result = $connection->query('SELECT * FROM payments ORDER BY payment_date DESC, created_at DESC, reference DESC');

        if (!$result instanceof mysqli_result) {
            error_log('Unable to fetch payment records from database: ' . $connection->error);

            return [];
        }

        $records = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $records[] = self::normalizeRecord($row);
        }
        $result->free();

        return $records;
    }

    private static function databaseRecordsForBooking(mysqli $connection, string $bookingId): array
    {
        $statement = self::prepare(
            $connection,
            'SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date ASC, created_at ASC, reference ASC',
            's',
            [$bookingId]
        );

        if (!$statement instanceof mysqli_stmt) {
            return [];
        }

        $result = $statement->get_result();
        $rows = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            $result->free();
        }

        $statement->close();

        return $rows;
    }

    private static function normalizeRecord(array $payment, ?array $booking = null): array
    {
        $booking ??= Booking::find((string) ($payment['booking_id'] ?? ''));
        $id = (string) ($payment['id'] ?? '');
        $digits = preg_replace('/\D+/', '', $id);

        return [
            'id' => $id,
            'reference' => (string) ($payment['reference'] ?? ('PMT-' . ($digits !== '' ? $digits : strtoupper(substr(sha1($id), 0, 6))))),
            'booking_id' => (string) ($payment['booking_id'] ?? ''),
            'booking_reference' => (string) ($payment['booking_reference'] ?? ($booking['reference'] ?? 'Unlinked booking')),
            'payment_date' => (string) ($payment['payment_date'] ?? date('Y-m-d')),
            'method' => (string) ($payment['method'] ?? 'other'),
            'amount' => round((float) ($payment['amount'] ?? 0), 2),
            'note' => trim((string) ($payment['note'] ?? '')),
            'recorded_by' => (string) ($payment['recorded_by'] ?? 'Admin panel'),
            'payment_status' => (string) ($booking['payment_status'] ?? ($payment['payment_status'] ?? 'unpaid')),
            'booking_status' => (string) ($booking['status'] ?? ($payment['booking_status'] ?? 'pending')),
            'balance' => round((float) ($booking['balance'] ?? ($payment['balance'] ?? 0)), 2),
            'customer' => $booking['customer'] ?? ($payment['customer'] ?? ['id' => '', 'name' => 'Unknown guest']),
            'service' => $booking['service'] ?? ($payment['service'] ?? ['id' => '', 'name' => 'Unknown service']),
            'staff' => $booking['staff'] ?? ($payment['staff'] ?? ['id' => '', 'name' => 'Unassigned']),
            'sort_key' => ((string) ($payment['payment_date'] ?? date('Y-m-d'))) . ' ' . $id,
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

    private static function nextReference(?mysqli $connection = null): string
    {
        if ($connection instanceof mysqli) {
            $result = $connection->query(
                "SELECT reference
                 FROM payments
                 WHERE reference REGEXP '^PMT-[0-9]+$'
                 ORDER BY CAST(SUBSTRING(reference, 5) AS UNSIGNED) DESC
                 LIMIT 1"
            );

            if ($result instanceof mysqli_result) {
                $row = $result->fetch_assoc() ?: [];
                $result->free();
                $lastReference = (string) ($row['reference'] ?? '');

                if ($lastReference !== '') {
                    $numeric = (int) preg_replace('/\D+/', '', $lastReference);

                    return 'PMT-' . ($numeric + 1);
                }
            }
        }

        $max = 5000;

        foreach (self::all() as $payment) {
            $reference = (string) ($payment['reference'] ?? '');
            $numeric = (int) preg_replace('/\D+/', '', $reference);
            $max = max($max, $numeric);
        }

        return 'PMT-' . ($max + 1);
    }

    private static function nextLegacyIdFromRecords(array $records): string
    {
        $max = 5000;

        foreach (array_keys(self::baseRecords()) as $id) {
            $numeric = (int) preg_replace('/\D+/', '', $id);
            $max = max($max, $numeric);
        }

        foreach (array_keys($records) as $id) {
            $numeric = (int) preg_replace('/\D+/', '', $id);
            $max = max($max, $numeric);
        }

        return 'PAY' . ($max + 1);
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $connection, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $statement = $connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('Payment statement prepare failed: ' . $connection->error);

            return null;
        }

        if ($params !== []) {
            $statement->bind_param($types, ...$params);
        }

        if (!$statement->execute()) {
            error_log('Payment statement execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function baseRecords(): array
    {
        return [
            'PAY5001' => [
                'id' => 'PAY5001',
                'reference' => 'PMT-5001',
                'booking_id' => 'BK1101',
                'payment_date' => date('Y-m-d'),
                'method' => 'cash',
                'amount' => 45.00,
                'note' => 'Paid in full at front desk check-in.',
                'recorded_by' => 'Front desk',
                'payment_status' => 'paid',
            ],
            'PAY5002' => [
                'id' => 'PAY5002',
                'reference' => 'PMT-5002',
                'booking_id' => 'BK1102',
                'payment_date' => date('Y-m-d'),
                'method' => 'visa_mastercard',
                'amount' => 20.00,
                'note' => 'Deposit captured to hold the therapist block.',
                'recorded_by' => 'Front desk',
                'payment_status' => 'partial',
            ],
            'PAY5003' => [
                'id' => 'PAY5003',
                'reference' => 'PMT-5003',
                'booking_id' => 'BK1103',
                'payment_date' => date('Y-m-d'),
                'method' => 'ecocash',
                'amount' => 50.00,
                'note' => 'First settlement captured before treatment start.',
                'recorded_by' => 'Reception',
                'payment_status' => 'paid',
            ],
            'PAY5004' => [
                'id' => 'PAY5004',
                'reference' => 'PMT-5004',
                'booking_id' => 'BK1103',
                'payment_date' => date('Y-m-d'),
                'method' => 'cash',
                'amount' => 28.00,
                'note' => 'Balance cleared at checkout.',
                'recorded_by' => 'Reception',
                'payment_status' => 'paid',
            ],
            'PAY5005' => [
                'id' => 'PAY5005',
                'reference' => 'PMT-5005',
                'booking_id' => 'BK1105',
                'payment_date' => date('Y-m-d', strtotime('+1 day')),
                'method' => 'ecocash',
                'amount' => 15.00,
                'note' => 'Deposit carried over after reschedule.',
                'recorded_by' => 'WhatsApp desk',
                'payment_status' => 'partial',
            ],
            'PAY5006' => [
                'id' => 'PAY5006',
                'reference' => 'PMT-5006',
                'booking_id' => 'BK1100',
                'payment_date' => date('Y-m-d', strtotime('-1 day')),
                'method' => 'visa_mastercard',
                'amount' => 45.00,
                'note' => 'Refund issued after cancellation.',
                'recorded_by' => 'Front desk',
                'payment_status' => 'refunded',
            ],
        ];
    }
}
