<?php declare(strict_types=1);

final class PayrollEngine
{
    private static ?array $runCache = null;
    private static ?array $profileCache = null;
    private static ?array $adjustmentCache = null;
    private static ?array $paymentEntryCache = null;
    private static ?array $payslipCache = null;
    private static bool $schemaEnsured = false;

    public static function statuses(): array
    {
        return ['draft', 'under_review', 'approved', 'locked', 'paid', 'cancelled'];
    }

    public static function statusLabel(string $status): string
    {
        return match (self::normalizeStatus($status)) {
            'under_review' => 'Under review',
            default => ucwords(str_replace('_', ' ', self::normalizeStatus($status))),
        };
    }

    public static function statusTone(string $status): string
    {
        return match (self::normalizeStatus($status)) {
            'paid' => 'success',
            'approved', 'locked' => 'info',
            'cancelled' => 'danger',
            default => 'warning',
        };
    }

    public static function adjustmentTypes(): array
    {
        return ['bonus', 'deduction', 'advance', 'overtime'];
    }

    public static function adjustmentTypeLabel(string $type): string
    {
        return match ($type) {
            'advance' => 'Advance recovery',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    public static function adjustmentStatuses(): array
    {
        return ['pending', 'approved', 'cancelled'];
    }

    public static function employmentTypes(): array
    {
        return ['commission', 'salaried', 'hourly', 'hybrid', 'contract'];
    }

    public static function commissionModels(): array
    {
        return ['none', 'percent', 'per_booking'];
    }

    public static function currentPeriod(): array
    {
        return [
            'period_start' => date('Y-m-01'),
            'period_end' => date('Y-m-t'),
        ];
    }

    public static function staffOptions(): array
    {
        $rows = array_map(static function (array $member): array {
            $profile = self::profileForStaff((string) $member['id']);

            return [
                'id' => (string) $member['id'],
                'name' => (string) $member['name'],
                'salary_structure' => (string) $profile['salary_structure'],
                'employment_type' => (string) $profile['employment_type'],
                'payment_method' => (string) $profile['payment_method'],
            ];
        }, self::eligibleStaff());

        usort($rows, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $rows;
    }

    public static function stats(): array
    {
        $preview = self::previewRun(self::currentPeriod() + ['selected_staff' => []]);
        $runs = self::runs();
        $openRuns = array_filter($runs, static fn (array $run): bool => !in_array($run['status'], ['paid', 'cancelled'], true));
        $paidRuns = array_filter($runs, static fn (array $run): bool => $run['status'] === 'paid');
        $paidValue = array_sum(array_map(static fn (array $run): float => (float) ($run['totals']['net_payout'] ?? 0), $paidRuns));

        return [
            ['label' => 'Current projected net payroll', 'value' => format_money((float) ($preview['totals']['net_payout'] ?? 0)), 'tone' => 'warning'],
            ['label' => 'Runs awaiting completion', 'value' => (string) count($openRuns), 'tone' => 'info'],
            ['label' => 'Gross current-cycle exposure', 'value' => format_money((float) ($preview['totals']['gross_pay'] ?? 0)), 'tone' => 'info'],
            ['label' => 'Paid payroll value', 'value' => format_money($paidValue), 'tone' => 'success'],
        ];
    }

    public static function earnings(array $filters = []): array
    {
        $periodStart = (string) ($filters['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($filters['period_end'] ?? date('Y-m-t'));
        $selectedIds = self::normalizeSelectedStaff($filters['selected_staff'] ?? ($filters['staff_id'] ?? 'all'));
        $rows = [];

        foreach (self::eligibleStaff($selectedIds) as $member) {
            $rows[] = self::buildPayrollRow($member, $periodStart, $periodEnd);
        }

        usort($rows, static fn (array $left, array $right): int => strcmp((string) $left['staff_name'], (string) $right['staff_name']));

        return $rows;
    }

    public static function earningsStats(array $filters = []): array
    {
        $rows = self::earnings($filters);

        return [
            ['label' => 'Gross payroll', 'value' => format_money(array_sum(array_map(static fn (array $row): float => (float) $row['gross_pay'], $rows))), 'tone' => 'info'],
            ['label' => 'Deductions total', 'value' => format_money(array_sum(array_map(static fn (array $row): float => (float) $row['total_deductions'], $rows))), 'tone' => 'danger'],
            ['label' => 'Net payroll', 'value' => format_money(array_sum(array_map(static fn (array $row): float => (float) $row['net_pay'], $rows))), 'tone' => 'success'],
            ['label' => 'Commission-ready bookings', 'value' => (string) array_sum(array_map(static fn (array $row): int => (int) $row['commission_eligible_count'], $rows)), 'tone' => 'warning'],
        ];
    }

    public static function previewRun(array $payload): array
    {
        $periodStart = (string) ($payload['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($payload['period_end'] ?? date('Y-m-t'));
        $selectedStaff = self::normalizeSelectedStaff($payload['selected_staff'] ?? []);
        $adjustments = is_array($payload['adjustments'] ?? null) ? $payload['adjustments'] : [];
        $staffNotes = is_array($payload['staff_notes'] ?? null) ? $payload['staff_notes'] : [];
        $rows = self::earnings([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'selected_staff' => $selectedStaff,
        ]);

        $items = array_map(static function (array $row) use ($adjustments, $staffNotes): array {
            $manualAdjustment = round((float) ($adjustments[$row['staff_id']] ?? 0), 2);
            $adjustmentNote = trim((string) ($staffNotes[$row['staff_id']] ?? ''));
            $row['adjustment'] = $manualAdjustment;
            $row['adjustment_note'] = $adjustmentNote;
            $row['net_pay'] = round((float) $row['gross_pay'] - (float) $row['total_deductions'] + $manualAdjustment, 2);
            $row['total_payout'] = $row['net_pay'];

            return $row;
        }, $rows);

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'items' => $items,
            'totals' => [
                'staff_count' => count($items),
                'completed_bookings' => array_sum(array_map(static fn (array $item): int => (int) $item['completed_count'], $items)),
                'commission_eligible_count' => array_sum(array_map(static fn (array $item): int => (int) $item['commission_eligible_count'], $items)),
                'commissionable_value' => array_sum(array_map(static fn (array $item): float => (float) $item['commissionable_value'], $items)),
                'base_payout' => array_sum(array_map(static fn (array $item): float => (float) $item['base_payout'], $items)),
                'bonus_total' => array_sum(array_map(static fn (array $item): float => (float) $item['bonus_total'], $items)),
                'overtime_total' => array_sum(array_map(static fn (array $item): float => (float) $item['overtime_total'], $items)),
                'advance_total' => array_sum(array_map(static fn (array $item): float => (float) $item['advance_total'], $items)),
                'deduction_total' => array_sum(array_map(static fn (array $item): float => (float) $item['total_deductions'], $items)),
                'gross_pay' => array_sum(array_map(static fn (array $item): float => (float) $item['gross_pay'], $items)),
                'adjustment_total' => array_sum(array_map(static fn (array $item): float => (float) $item['adjustment'], $items)),
                'net_payout' => array_sum(array_map(static fn (array $item): float => (float) $item['net_pay'], $items)),
            ],
        ];
    }

    public static function validateRunPayload(array $payload): array
    {
        $errors = [];
        $periodStart = trim((string) ($payload['period_start'] ?? ''));
        $periodEnd = trim((string) ($payload['period_end'] ?? ''));

        if ($periodStart === '') {
            $errors['period_start'] = 'Period start is required.';
        }

        if ($periodEnd === '') {
            $errors['period_end'] = 'Period end is required.';
        }

        if ($periodStart !== '' && $periodEnd !== '' && $periodStart > $periodEnd) {
            $errors['period_end'] = 'Period end must be on or after the start date.';
        }

        foreach ((array) ($payload['adjustments'] ?? []) as $staffId => $adjustment) {
            if ($adjustment === '') {
                continue;
            }

            if (!is_numeric((string) $adjustment)) {
                $errors['adjustments.' . $staffId] = 'Adjustments must be numeric.';
            }
        }

        if ($periodStart !== '' && $periodEnd !== '' && self::hasOverlappingOpenRun($periodStart, $periodEnd, trim((string) ($payload['run_id'] ?? '')))) {
            $errors['_run'] = 'Another active payroll run already overlaps this period.';
        }

        $preview = self::previewRun($payload);

        if ($preview['items'] === []) {
            $errors['selected_staff'] = 'Choose at least one eligible staff member or widen the period.';
        }

        foreach ($preview['items'] as $item) {
            if ((float) $item['net_pay'] < 0) {
                $errors['adjustments.' . $item['staff_id']] = 'This row would produce a negative net pay.';
            }
        }

        return $errors;
    }

    public static function createRun(array $payload, string $createdBy = 'Admin panel'): array
    {
        $preview = self::previewRun($payload);
        $runId = self::nextId();
        $reference = self::nextReference();
        $label = trim((string) ($payload['label'] ?? ''));
        $periodLabel = date('j M', strtotime($preview['period_start'])) . ' - ' . date('j M Y', strtotime($preview['period_end']));
        $run = [
            'id' => $runId,
            'reference' => $reference,
            'label' => $label !== '' ? $label : 'Payroll run ' . $periodLabel,
            'period_start' => $preview['period_start'],
            'period_end' => $preview['period_end'],
            'status' => 'draft',
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
            'finalized_at' => null,
            'approved_at' => null,
            'locked_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'status_reason' => trim((string) ($payload['notes'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'totals' => $preview['totals'],
            'items' => array_map(static function (array $item): array {
                $item['id'] = self::nextId();

                return $item;
            }, $preview['items']),
            'history' => [[
                'label' => 'Payroll run created',
                'meta' => $createdBy . ' generated this payroll run.',
                'tone' => 'info',
            ]],
            'payment_entries' => [],
        ];

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);

            try {
                $runParams = [
                    $runId,
                    $reference,
                    $run['label'],
                    $preview['period_start'],
                    $preview['period_end'],
                    'draft',
                    $createdBy,
                    $run['status_reason'],
                    $run['notes'],
                    (string) (int) $preview['totals']['staff_count'],
                    (string) (int) $preview['totals']['completed_bookings'],
                    (string) round((float) $preview['totals']['commissionable_value'], 2),
                    (string) round((float) $preview['totals']['base_payout'], 2),
                    (string) round((float) $preview['totals']['adjustment_total'], 2),
                    (string) round((float) $preview['totals']['bonus_total'], 2),
                    (string) round((float) $preview['totals']['overtime_total'], 2),
                    (string) round((float) $preview['totals']['advance_total'], 2),
                    (string) round((float) $preview['totals']['deduction_total'], 2),
                    (string) round((float) $preview['totals']['gross_pay'], 2),
                    (string) round((float) $preview['totals']['net_payout'], 2),
                ];

                $runStatement = self::prepare(
                    $conn,
                    'INSERT INTO payroll_runs (
                        id, reference, label, period_start, period_end, status, created_by,
                        finalized_at, approved_at, locked_at, paid_at, cancelled_at, status_reason, notes,
                        staff_count, completed_bookings, commissionable_value, base_payout, adjustment_total,
                        bonus_total, overtime_total, advance_total, deduction_total, gross_pay, net_payout,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    $runParams
                );

                if (!$runStatement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to insert payroll run.');
                }
                $runStatement->close();

                foreach ($run['items'] as $item) {
                    $itemParams = [
                        (string) $item['id'],
                        $runId,
                        (string) $item['staff_id'],
                        (string) $item['staff_name'],
                        (string) $item['role_type'],
                        (string) $item['salary_structure'],
                        (string) round((float) $item['commission_rate'], 2),
                        (string) round((float) $item['fixed_pay'], 2),
                        (string) (int) $item['completed_count'],
                        (string) round((float) $item['commissionable_value'], 2),
                        (string) round((float) $item['collected_value'], 2),
                        (string) round((float) $item['commission_total'], 2),
                        (string) round((float) $item['base_payout'], 2),
                        (string) round((float) $item['adjustment'], 2),
                        (string) $item['adjustment_note'],
                        (string) round((float) $item['total_payout'], 2),
                        (string) $item['employment_type'],
                        (string) $item['commission_model'],
                        (string) $item['payment_method'],
                        (string) round((float) $item['gross_pay'], 2),
                        (string) round((float) $item['total_deductions'], 2),
                        (string) round((float) $item['net_pay'], 2),
                        json_encode(self::snapshotItem($item), JSON_UNESCAPED_SLASHES),
                    ];

                    $itemStatement = self::prepare(
                        $conn,
                        'INSERT INTO payroll_run_items (
                            id, run_id, staff_id, staff_name, role_type, salary_structure,
                            commission_rate, fixed_pay, completed_count, commissionable_value, collected_value,
                            commission_total, base_payout, adjustment, adjustment_note, total_payout,
                            employment_type, commission_model, payment_method, gross_pay, total_deductions, net_pay, snapshot_json
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        $itemParams
                    );

                    if (!$itemStatement instanceof mysqli_stmt) {
                        throw new RuntimeException('Unable to insert payroll run item.');
                    }
                    $itemStatement->close();
                }

                self::insertHistory($conn, $runId, 'Payroll run created', $createdBy . ' generated this payroll run.', 'info');
                mysqli_commit($conn);
                self::flushCaches();

                return self::find($runId) ?? $run;
            } catch (Throwable $exception) {
                mysqli_rollback($conn);
                error_log('Payroll createRun failed: ' . $exception->getMessage());
                throw $exception;
            }
        }

        $_SESSION['payroll_runs'][$runId] = $run;

        return $run;
    }

    public static function transitionRun(string $runId, string $action, string $actor = 'Admin panel', string $reason = ''): ?array
    {
        $run = self::find($runId);

        if ($run === null) {
            return null;
        }

        $action = $action === 'finalize' ? 'lock' : $action;
        $reason = trim($reason);
        $transition = match (true) {
            $action === 'submit_review' && $run['status'] === 'draft' => [
                'status' => 'under_review',
                'label' => 'Run sent for review',
                'meta' => $actor . ' submitted the payroll run for review.',
                'tone' => 'warning',
            ],
            $action === 'approve' && $run['status'] === 'under_review' => [
                'status' => 'approved',
                'field' => 'approved_at',
                'timestamp_field' => 'approved_at',
                'label' => 'Run approved',
                'meta' => $actor . ' approved the payroll calculations.',
                'tone' => 'info',
            ],
            $action === 'lock' && $run['status'] === 'approved' => [
                'status' => 'locked',
                'field' => 'locked_at',
                'timestamp_field' => 'locked_at',
                'label' => 'Run locked',
                'meta' => $actor . ' locked the run and generated payslips.',
                'tone' => 'info',
            ],
            $action === 'pay' && $run['status'] === 'locked' => [
                'status' => 'paid',
                'field' => 'paid_at',
                'timestamp_field' => 'paid_at',
                'label' => 'Run paid',
                'meta' => $actor . ' posted payroll payment entries.',
                'tone' => 'success',
            ],
            $action === 'cancel' && in_array($run['status'], ['draft', 'under_review', 'approved'], true) => [
                'status' => 'cancelled',
                'field' => 'cancelled_at',
                'timestamp_field' => 'cancelled_at',
                'label' => 'Run cancelled',
                'meta' => $actor . ' cancelled the run.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'tone' => 'danger',
            ],
            default => null,
        };

        if (!is_array($transition)) {
            return $run;
        }

        $now = date('Y-m-d H:i:s');
        $run['status'] = $transition['status'];
        if (isset($transition['timestamp_field']) && is_string($transition['timestamp_field']) && $transition['timestamp_field'] !== '') {
            $run[$transition['timestamp_field']] = $now;
        }

        if ($transition['status'] === 'locked') {
            $run['finalized_at'] = $run['finalized_at'] ?? $now;
        }

        if ($transition['status'] === 'paid') {
            $run['locked_at'] = $run['locked_at'] ?? $now;
            $run['finalized_at'] = $run['finalized_at'] ?? $run['locked_at'];
        }

        if ($reason !== '') {
            $run['status_reason'] = $reason;
        }

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);

            try {
                $statement = self::prepare(
                    $conn,
                    'UPDATE payroll_runs
                     SET status = ?, finalized_at = ?, approved_at = ?, locked_at = ?, paid_at = ?, cancelled_at = ?, status_reason = ?, updated_at = NOW()
                     WHERE id = ?',
                    [
                        $run['status'],
                        self::nullableString($run['finalized_at']),
                        self::nullableString($run['approved_at']),
                        self::nullableString($run['locked_at']),
                        self::nullableString($run['paid_at']),
                        self::nullableString($run['cancelled_at']),
                        (string) ($run['status_reason'] ?? ''),
                        $runId,
                    ]
                );

                if (!$statement instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to update payroll run.');
                }
                $statement->close();

                if ($transition['status'] === 'locked') {
                    self::generatePayslips($conn, $run, $actor);
                }

                if ($transition['status'] === 'paid') {
                    self::postPayrollPayments($conn, $run, $actor);
                }

                self::insertHistory($conn, $runId, $transition['label'], $transition['meta'], $transition['tone']);
                mysqli_commit($conn);
                self::flushCaches();

                return self::find($runId);
            } catch (Throwable $exception) {
                mysqli_rollback($conn);
                error_log('Payroll transition failed: ' . $exception->getMessage());
                throw $exception;
            }
        }

        $runs = $_SESSION['payroll_runs'] ?? [];
        if (isset($runs[$runId]) && is_array($runs[$runId])) {
            $runs[$runId] = $run;
            $runs[$runId]['history'][] = [
                'label' => $transition['label'],
                'meta' => $transition['meta'],
                'tone' => $transition['tone'],
            ];
            $_SESSION['payroll_runs'] = $runs;
        }

        return $run;
    }

    public static function runs(array $filters = []): array
    {
        $runs = array_values(self::databaseRuns());
        $status = self::normalizeStatus((string) ($filters['status'] ?? 'all'));
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        $runs = array_values(array_filter($runs, static function (array $run) use ($status, $search): bool {
            if ($status !== 'all' && $run['status'] !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                (string) ($run['reference'] ?? ''),
                (string) ($run['label'] ?? ''),
                (string) ($run['notes'] ?? ''),
                (string) ($run['status_reason'] ?? ''),
            ]));

            return str_contains($haystack, $search);
        }));

        usort($runs, static fn (array $left, array $right): int => strcmp((string) $right['created_at'], (string) $left['created_at']));

        return $runs;
    }

    public static function find(string $runId): ?array
    {
        return self::databaseRuns()[$runId] ?? null;
    }

    public static function recentRuns(int $limit = 5): array
    {
        return array_slice(self::runs(), 0, $limit);
    }

    public static function profiles(array $filters = []): array
    {
        $selectedStaff = trim((string) ($filters['staff_id'] ?? ''));
        $profiles = [];

        foreach (Staff::all() as $member) {
            if ($selectedStaff !== '' && $selectedStaff !== (string) $member['id']) {
                continue;
            }

            $profiles[] = self::profileForStaff((string) $member['id']);
        }

        usort($profiles, static fn (array $left, array $right): int => strcmp((string) $left['staff_name'], (string) $right['staff_name']));

        return $profiles;
    }

    public static function profileForStaff(string $staffId): array
    {
        $member = Staff::find($staffId);
        $defaults = self::defaultProfile($member);
        $stored = self::databaseProfiles()[$staffId] ?? [];

        return self::normalizeProfile($defaults + $stored + ['staff_id' => $staffId], $member);
    }

    public static function validateProfilePayload(array $payload): array
    {
        $errors = [];
        $staffId = trim((string) ($payload['staff_id'] ?? ''));

        if ($staffId === '' || Staff::find($staffId) === null) {
            $errors['staff_id'] = 'Select a valid staff member.';
        }

        if (!in_array((string) ($payload['employment_type'] ?? ''), self::employmentTypes(), true)) {
            $errors['employment_type'] = 'Select a valid employment type.';
        }

        if (!in_array((string) ($payload['salary_structure'] ?? ''), ['commission', 'fixed', 'hybrid'], true)) {
            $errors['salary_structure'] = 'Select a valid salary structure.';
        }

        if (!in_array((string) ($payload['commission_model'] ?? ''), self::commissionModels(), true)) {
            $errors['commission_model'] = 'Select a valid commission model.';
        }

        if (!in_array((string) ($payload['payment_method'] ?? ''), Payment::methods(), true)) {
            $errors['payment_method'] = 'Select a valid payment method.';
        }

        foreach ([
            'commission_rate',
            'commission_per_booking',
            'base_salary',
            'hourly_rate',
            'overtime_rate',
            'tax_percent',
            'pension_percent',
            'nssa_percent',
            'medical_aid_amount',
            'advance_limit',
        ] as $field) {
            $value = (string) ($payload[$field] ?? '');
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || (float) $value < 0) {
                $errors[$field] = 'Enter a valid zero-or-greater amount.';
            }
        }

        return $errors;
    }

    public static function saveProfile(array $payload, string $actor = 'Admin panel'): ?array
    {
        $staffId = trim((string) ($payload['staff_id'] ?? ''));
        $member = Staff::find($staffId);

        if ($member === null) {
            return null;
        }

        $profile = self::normalizeProfile($payload, $member);
        $conn = self::connection();

        if ($conn instanceof mysqli) {
            $params = [
                self::profileRecordId($staffId),
                $staffId,
                (string) $profile['employment_type'],
                (string) $profile['salary_structure'],
                (string) $profile['payment_method'],
                (string) $profile['commission_model'],
                (string) round((float) $profile['commission_rate'], 2),
                (string) round((float) $profile['commission_per_booking'], 2),
                (string) round((float) $profile['base_salary'], 2),
                (string) round((float) $profile['hourly_rate'], 2),
                (string) round((float) $profile['overtime_rate'], 2),
                (string) (int) $profile['overtime_eligible'],
                (string) round((float) $profile['tax_percent'], 2),
                (string) round((float) $profile['pension_percent'], 2),
                (string) round((float) $profile['nssa_percent'], 2),
                (string) round((float) $profile['medical_aid_amount'], 2),
                (string) round((float) $profile['advance_limit'], 2),
                (string) $profile['effective_from'],
                (string) $profile['notes'],
                (string) (int) $profile['active'],
                $actor,
            ];

            $statement = self::prepare(
                $conn,
                'INSERT INTO payroll_profiles (
                    id, staff_id, employment_type, salary_structure, payment_method, commission_model,
                    commission_rate, commission_per_booking, base_salary, hourly_rate, overtime_rate,
                    overtime_eligible, tax_percent, pension_percent, nssa_percent, medical_aid_amount,
                    advance_limit, effective_from, notes, active, updated_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    employment_type = VALUES(employment_type),
                    salary_structure = VALUES(salary_structure),
                    payment_method = VALUES(payment_method),
                    commission_model = VALUES(commission_model),
                    commission_rate = VALUES(commission_rate),
                    commission_per_booking = VALUES(commission_per_booking),
                    base_salary = VALUES(base_salary),
                    hourly_rate = VALUES(hourly_rate),
                    overtime_rate = VALUES(overtime_rate),
                    overtime_eligible = VALUES(overtime_eligible),
                    tax_percent = VALUES(tax_percent),
                    pension_percent = VALUES(pension_percent),
                    nssa_percent = VALUES(nssa_percent),
                    medical_aid_amount = VALUES(medical_aid_amount),
                    advance_limit = VALUES(advance_limit),
                    effective_from = VALUES(effective_from),
                    notes = VALUES(notes),
                    active = VALUES(active),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()',
                $params
            );

            if (!$statement instanceof mysqli_stmt) {
                return null;
            }
            $statement->close();
            self::flushCaches();

            return self::profileForStaff($staffId);
        }

        $_SESSION['payroll_profiles'][$staffId] = $profile;

        return self::profileForStaff($staffId);
    }

    public static function adjustments(array $filters = []): array
    {
        $rows = array_values(self::databaseAdjustments());
        $staffId = trim((string) ($filters['staff_id'] ?? ''));
        $type = trim((string) ($filters['type'] ?? 'all'));
        $status = trim((string) ($filters['status'] ?? 'all'));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        $rows = array_values(array_filter($rows, static function (array $row) use ($staffId, $type, $status, $dateFrom, $dateTo): bool {
            if ($staffId !== '' && $row['staff_id'] !== $staffId) {
                return false;
            }
            if ($type !== '' && $type !== 'all' && $row['type'] !== $type) {
                return false;
            }
            if ($status !== '' && $status !== 'all' && $row['status'] !== $status) {
                return false;
            }
            if ($dateFrom !== '' && $row['period_end'] < $dateFrom) {
                return false;
            }
            if ($dateTo !== '' && $row['period_start'] > $dateTo) {
                return false;
            }

            return true;
        }));

        usort($rows, static fn (array $left, array $right): int => strcmp((string) $right['created_at'], (string) $left['created_at']));

        return $rows;
    }

    public static function findAdjustment(string $id): ?array
    {
        return self::databaseAdjustments()[$id] ?? null;
    }

    public static function validateAdjustmentPayload(array $payload): array
    {
        $errors = [];
        $staffId = trim((string) ($payload['staff_id'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'pending'));
        $amount = trim((string) ($payload['amount'] ?? ''));
        $units = trim((string) ($payload['units'] ?? ''));
        $rate = trim((string) ($payload['rate'] ?? ''));

        if ($staffId === '' || Staff::find($staffId) === null) {
            $errors['staff_id'] = 'Select a valid staff member.';
        }

        if (!in_array($type, self::adjustmentTypes(), true)) {
            $errors['type'] = 'Select a valid payroll input type.';
        }

        if (!in_array($status, self::adjustmentStatuses(), true)) {
            $errors['status'] = 'Select a valid approval status.';
        }

        if (trim((string) ($payload['label'] ?? '')) === '') {
            $errors['label'] = 'A short label is required.';
        }

        if (trim((string) ($payload['period_start'] ?? '')) === '') {
            $errors['period_start'] = 'Start date is required.';
        }

        if (trim((string) ($payload['period_end'] ?? '')) === '') {
            $errors['period_end'] = 'End date is required.';
        }

        if (($payload['period_start'] ?? '') !== '' && ($payload['period_end'] ?? '') !== '' && (string) $payload['period_start'] > (string) $payload['period_end']) {
            $errors['period_end'] = 'End date must be on or after the start date.';
        }

        if ($amount === '' && !($type === 'overtime' && $units !== '' && $rate !== '')) {
            $errors['amount'] = 'Enter an amount, or for overtime provide hours and rate.';
        }

        foreach (['amount' => $amount, 'units' => $units, 'rate' => $rate] as $field => $value) {
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || (float) $value < 0) {
                $errors[$field] = 'Enter a valid zero-or-greater amount.';
            }
        }

        return $errors;
    }

    public static function saveAdjustment(array $payload, string $actor = 'Admin panel'): ?array
    {
        $staffId = trim((string) ($payload['staff_id'] ?? ''));
        $member = Staff::find($staffId);

        if ($member === null) {
            return null;
        }

        $profile = self::profileForStaff($staffId);
        $type = trim((string) ($payload['type'] ?? 'bonus'));
        $units = round((float) ($payload['units'] ?? 0), 2);
        $rate = round((float) ($payload['rate'] ?? 0), 2);
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($type === 'overtime' && $amount <= 0 && $units > 0) {
            $rate = $rate > 0 ? $rate : (float) $profile['overtime_rate'];
            $amount = round($units * $rate, 2);
        }

        $record = [
            'id' => self::nextId(),
            'staff_id' => $staffId,
            'staff_name' => (string) $member['name'],
            'type' => $type,
            'label' => trim((string) ($payload['label'] ?? '')),
            'amount' => $amount,
            'units' => $units,
            'rate' => $rate,
            'period_start' => trim((string) ($payload['period_start'] ?? date('Y-m-01'))),
            'period_end' => trim((string) ($payload['period_end'] ?? date('Y-m-t'))),
            'status' => trim((string) ($payload['status'] ?? 'pending')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $actor,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            $params = [
                $record['id'],
                $record['staff_id'],
                $record['type'],
                $record['label'],
                (string) round((float) $record['amount'], 2),
                (string) round((float) $record['units'], 2),
                (string) round((float) $record['rate'], 2),
                $record['period_start'],
                $record['period_end'],
                $record['status'],
                $record['notes'],
                $record['created_by'],
            ];

            $statement = self::prepare(
                $conn,
                'INSERT INTO payroll_adjustments (
                    id, staff_id, type, label, amount, units, rate, period_start, period_end, status, notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                $params
            );

            if (!$statement instanceof mysqli_stmt) {
                return null;
            }
            $statement->close();
            self::flushCaches();

            return self::findAdjustment($record['id']);
        }

        $_SESSION['payroll_adjustments'][$record['id']] = $record;

        return $record;
    }

    public static function transitionAdjustment(string $adjustmentId, string $action, string $actor = 'Admin panel'): ?array
    {
        $record = self::findAdjustment($adjustmentId);

        if ($record === null) {
            return null;
        }

        $newStatus = match ($action) {
            'approve_adjustment' => 'approved',
            'cancel_adjustment' => 'cancelled',
            default => $record['status'],
        };

        if ($newStatus === $record['status']) {
            return $record;
        }

        $conn = self::connection();
        $noteSuffix = trim(($record['notes'] !== '' ? $record['notes'] . "\n" : '') . $actor . ' changed status to ' . $newStatus . ' on ' . date('j M Y H:i'));

        if ($conn instanceof mysqli) {
            $statement = self::prepare(
                $conn,
                'UPDATE payroll_adjustments SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?',
                [$newStatus, $noteSuffix, $adjustmentId]
            );

            if (!$statement instanceof mysqli_stmt) {
                return null;
            }
            $statement->close();
            self::flushCaches();

            return self::findAdjustment($adjustmentId);
        }

        $_SESSION['payroll_adjustments'][$adjustmentId]['status'] = $newStatus;
        $_SESSION['payroll_adjustments'][$adjustmentId]['notes'] = $noteSuffix;

        return $_SESSION['payroll_adjustments'][$adjustmentId] ?? null;
    }

    public static function payslipForRunStaff(string $runId, string $staffId): ?array
    {
        foreach (self::databasePayslips() as $row) {
            if ($row['run_id'] === $runId && $row['staff_id'] === $staffId) {
                return $row;
            }
        }

        return null;
    }

    public static function paymentEntriesForRun(string $runId): array
    {
        $entries = array_values(array_filter(
            self::databasePaymentEntries(),
            static fn (array $entry): bool => $entry['run_id'] === $runId
        ));

        usort($entries, static fn (array $left, array $right): int => strcmp((string) $left['reference'], (string) $right['reference']));

        return $entries;
    }

    private static function buildPayrollRow(array $member, string $periodStart, string $periodEnd): array
    {
        $profile = self::profileForStaff((string) $member['id']);
        $bookings = Staff::bookings((string) $member['id']);
        $completedBookings = array_values(array_filter($bookings, static function (array $booking) use ($periodStart, $periodEnd): bool {
            $date = (string) ($booking['date'] ?? '');

            return $date >= $periodStart
                && $date <= $periodEnd
                && (string) ($booking['status'] ?? '') === 'completed';
        }));
        $commissionableBookings = array_values(array_filter($completedBookings, static function (array $booking): bool {
            return (string) ($booking['payment_status'] ?? '') === 'paid'
                && !in_array((string) ($booking['status'] ?? ''), ['cancelled', 'no_show'], true)
                && (string) ($booking['payment_status'] ?? '') !== 'refunded';
        }));

        usort($commissionableBookings, static fn (array $left, array $right): int => strcmp((string) $right['sort_key'], (string) $left['sort_key']));

        $commissionableValue = array_sum(array_map(static fn (array $booking): float => (float) ($booking['amount_total'] ?? 0), $commissionableBookings));
        $collectedValue = array_sum(array_map(static fn (array $booking): float => (float) ($booking['amount_paid'] ?? 0), $commissionableBookings));
        $regularHours = round(array_sum(array_map(static fn (array $booking): float => ((float) ($booking['duration_minutes'] ?? 0)) / 60, $completedBookings)), 2);
        $commissionTotal = self::commissionFromProfile($profile, $commissionableValue, count($commissionableBookings));
        $baseSalaryComponent = in_array((string) $profile['salary_structure'], ['fixed', 'hybrid'], true) ? (float) $profile['base_salary'] : 0.0;
        $hourlyPay = (string) $profile['employment_type'] === 'hourly' ? round($regularHours * (float) $profile['hourly_rate'], 2) : 0.0;

        if ((string) $profile['salary_structure'] === 'fixed') {
            $commissionTotal = 0.0;
        }

        $summary = self::approvedAdjustmentsSummary((string) $member['id'], $periodStart, $periodEnd);
        $grossPay = round($baseSalaryComponent + $hourlyPay + $commissionTotal + $summary['bonus_total'] + $summary['overtime_total'], 2);
        $taxAmount = round($grossPay * (((float) $profile['tax_percent']) / 100), 2);
        $pensionAmount = round($grossPay * (((float) $profile['pension_percent']) / 100), 2);
        $nssaAmount = round($grossPay * (((float) $profile['nssa_percent']) / 100), 2);
        $medicalAidAmount = round((float) $profile['medical_aid_amount'], 2);
        $statutoryDeductions = round($taxAmount + $pensionAmount + $nssaAmount + $medicalAidAmount, 2);
        $totalDeductions = round($statutoryDeductions + $summary['deduction_total_manual'] + $summary['advance_total'], 2);
        $basePayout = round($baseSalaryComponent + $hourlyPay + $commissionTotal, 2);
        $netPay = round($grossPay - $totalDeductions, 2);

        return [
            'staff_id' => (string) $member['id'],
            'staff_name' => (string) $member['name'],
            'role_type' => (string) ($member['role_type'] ?? ''),
            'employment_type' => (string) $profile['employment_type'],
            'salary_structure' => (string) $profile['salary_structure'],
            'payment_method' => (string) $profile['payment_method'],
            'commission_model' => (string) $profile['commission_model'],
            'commission_rate' => (float) $profile['commission_rate'],
            'commission_per_booking' => (float) $profile['commission_per_booking'],
            'fixed_pay' => (float) $profile['base_salary'],
            'base_salary' => (float) $profile['base_salary'],
            'hourly_rate' => (float) $profile['hourly_rate'],
            'overtime_rate' => (float) $profile['overtime_rate'],
            'completed_count' => count($completedBookings),
            'commission_eligible_count' => count($commissionableBookings),
            'commissionable_value' => round($commissionableValue, 2),
            'collected_value' => round($collectedValue, 2),
            'commission_total' => round($commissionTotal, 2),
            'regular_hours' => $regularHours,
            'base_salary_component' => round($baseSalaryComponent, 2),
            'hourly_pay' => round($hourlyPay, 2),
            'base_payout' => round($basePayout, 2),
            'bonus_total' => (float) $summary['bonus_total'],
            'overtime_hours' => (float) $summary['overtime_hours'],
            'overtime_total' => (float) $summary['overtime_total'],
            'deduction_total_manual' => (float) $summary['deduction_total_manual'],
            'advance_total' => (float) $summary['advance_total'],
            'tax_amount' => $taxAmount,
            'pension_amount' => $pensionAmount,
            'nssa_amount' => $nssaAmount,
            'medical_aid_amount' => $medicalAidAmount,
            'statutory_deductions' => $statutoryDeductions,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'adjustment' => 0.0,
            'adjustment_note' => '',
            'net_pay' => $netPay,
            'total_payout' => $netPay,
            'commission_source_summary' => 'Completed, assigned, paid bookings only. Refunded or cancelled work is excluded.',
            'bookings' => $commissionableBookings,
            'adjustment_rows' => $summary['rows'],
        ];
    }

    private static function approvedAdjustmentsSummary(string $staffId, string $periodStart, string $periodEnd): array
    {
        $summary = [
            'bonus_total' => 0.0,
            'deduction_total_manual' => 0.0,
            'advance_total' => 0.0,
            'overtime_total' => 0.0,
            'overtime_hours' => 0.0,
            'rows' => [],
        ];

        foreach (self::adjustmentsForStaffPeriod($staffId, $periodStart, $periodEnd) as $row) {
            $summary['rows'][] = $row;

            switch ((string) $row['type']) {
                case 'bonus':
                    $summary['bonus_total'] += (float) $row['amount'];
                    break;
                case 'deduction':
                    $summary['deduction_total_manual'] += (float) $row['amount'];
                    break;
                case 'advance':
                    $summary['advance_total'] += (float) $row['amount'];
                    break;
                case 'overtime':
                    $summary['overtime_total'] += (float) $row['amount'];
                    $summary['overtime_hours'] += (float) $row['units'];
                    break;
            }
        }

        foreach (['bonus_total', 'deduction_total_manual', 'advance_total', 'overtime_total', 'overtime_hours'] as $field) {
            $summary[$field] = round((float) $summary[$field], 2);
        }

        return $summary;
    }

    private static function adjustmentsForStaffPeriod(string $staffId, string $periodStart, string $periodEnd): array
    {
        return array_values(array_filter(
            self::databaseAdjustments(),
            static function (array $row) use ($staffId, $periodStart, $periodEnd): bool {
                return $row['staff_id'] === $staffId
                    && $row['status'] === 'approved'
                    && $row['period_start'] <= $periodEnd
                    && $row['period_end'] >= $periodStart;
            }
        ));
    }

    private static function commissionFromProfile(array $profile, float $value, int $count): float
    {
        return match ((string) $profile['commission_model']) {
            'none' => 0.0,
            'per_booking' => round($count * (float) $profile['commission_per_booking'], 2),
            default => round($value * (((float) $profile['commission_rate']) / 100), 2),
        };
    }

    private static function hasOverlappingOpenRun(string $periodStart, string $periodEnd, string $ignoreRunId = ''): bool
    {
        foreach (self::runs() as $run) {
            if ($ignoreRunId !== '' && $run['id'] === $ignoreRunId) {
                continue;
            }

            if (in_array($run['status'], ['paid', 'cancelled'], true)) {
                continue;
            }

            if ((string) $run['period_start'] <= $periodEnd && (string) $run['period_end'] >= $periodStart) {
                return true;
            }
        }

        return false;
    }

    private static function eligibleStaff(array $selectedIds = []): array
    {
        $lookup = array_fill_keys($selectedIds, true);

        return array_values(array_filter(Staff::all(), static function (array $member) use ($lookup): bool {
            if ($lookup !== [] && !isset($lookup[(string) $member['id']])) {
                return false;
            }

            if (!in_array((string) ($member['status'] ?? ''), ['active', 'on_leave'], true)) {
                return false;
            }

            $profile = self::profileForStaff((string) $member['id']);

            return (bool) $profile['active'];
        }));
    }

    private static function normalizeSelectedStaff(mixed $value): array
    {
        if ($value === 'all' || $value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $values));

        return array_values(array_unique($values));
    }

    private static function normalizeProfile(array $payload, ?array $member = null): array
    {
        $defaults = self::defaultProfile($member);

        return [
            'staff_id' => trim((string) ($payload['staff_id'] ?? $defaults['staff_id'])),
            'staff_name' => (string) ($member['name'] ?? $defaults['staff_name']),
            'employment_type' => trim((string) ($payload['employment_type'] ?? $defaults['employment_type'])),
            'salary_structure' => trim((string) ($payload['salary_structure'] ?? $defaults['salary_structure'])),
            'payment_method' => trim((string) ($payload['payment_method'] ?? $defaults['payment_method'])),
            'commission_model' => trim((string) ($payload['commission_model'] ?? $defaults['commission_model'])),
            'commission_rate' => round((float) ($payload['commission_rate'] ?? $defaults['commission_rate']), 2),
            'commission_per_booking' => round((float) ($payload['commission_per_booking'] ?? $defaults['commission_per_booking']), 2),
            'base_salary' => round((float) ($payload['base_salary'] ?? $defaults['base_salary']), 2),
            'hourly_rate' => round((float) ($payload['hourly_rate'] ?? $defaults['hourly_rate']), 2),
            'overtime_rate' => round((float) ($payload['overtime_rate'] ?? $defaults['overtime_rate']), 2),
            'overtime_eligible' => ((string) ($payload['overtime_eligible'] ?? ($defaults['overtime_eligible'] ? '1' : '0'))) === '1' ? 1 : 0,
            'tax_percent' => round((float) ($payload['tax_percent'] ?? $defaults['tax_percent']), 2),
            'pension_percent' => round((float) ($payload['pension_percent'] ?? $defaults['pension_percent']), 2),
            'nssa_percent' => round((float) ($payload['nssa_percent'] ?? $defaults['nssa_percent']), 2),
            'medical_aid_amount' => round((float) ($payload['medical_aid_amount'] ?? $defaults['medical_aid_amount']), 2),
            'advance_limit' => round((float) ($payload['advance_limit'] ?? $defaults['advance_limit']), 2),
            'effective_from' => trim((string) ($payload['effective_from'] ?? $defaults['effective_from'])),
            'notes' => trim((string) ($payload['notes'] ?? $defaults['notes'])),
            'active' => ((string) ($payload['active'] ?? ($defaults['active'] ? '1' : '0'))) === '1' ? 1 : 0,
        ];
    }

    private static function defaultProfile(?array $member): array
    {
        $salaryStructure = trim((string) ($member['salary_structure'] ?? 'commission'));
        $employmentType = match ($salaryStructure) {
            'fixed' => 'salaried',
            'hybrid' => 'hybrid',
            default => 'commission',
        };

        return [
            'staff_id' => (string) ($member['id'] ?? ''),
            'staff_name' => (string) ($member['name'] ?? 'Unknown staff'),
            'employment_type' => $employmentType,
            'salary_structure' => $salaryStructure,
            'payment_method' => 'bank_transfer',
            'commission_model' => in_array($salaryStructure, ['commission', 'hybrid'], true) ? 'percent' : 'none',
            'commission_rate' => round((float) ($member['commission_rate'] ?? 0), 2),
            'commission_per_booking' => 0.0,
            'base_salary' => round((float) ($member['fixed_pay'] ?? 0), 2),
            'hourly_rate' => 0.0,
            'overtime_rate' => 0.0,
            'overtime_eligible' => 0,
            'tax_percent' => 0.0,
            'pension_percent' => 0.0,
            'nssa_percent' => 0.0,
            'medical_aid_amount' => 0.0,
            'advance_limit' => 0.0,
            'effective_from' => date('Y-m-01'),
            'notes' => '',
            'active' => 1,
        ];
    }

    private static function databaseRuns(): array
    {
        if (self::$runCache !== null) {
            return self::$runCache;
        }

        $conn = self::connection();

        if (!$conn instanceof mysqli) {
            $runs = $_SESSION['payroll_runs'] ?? [];
            foreach ($runs as $id => $run) {
                $runs[$id]['status'] = self::normalizeStatus((string) ($run['status'] ?? 'draft'));
            }
            self::$runCache = $runs;

            return $runs;
        }

        $result = $conn->query(
            'SELECT id, reference, label, period_start, period_end, status, created_by, created_at,
                    finalized_at, approved_at, locked_at, paid_at, cancelled_at, status_reason, notes,
                    staff_count, completed_bookings, commissionable_value, base_payout, adjustment_total,
                    bonus_total, overtime_total, advance_total, deduction_total, gross_pay, net_payout
             FROM payroll_runs
             ORDER BY created_at DESC'
        );

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $runs = [];
        while ($row = $result->fetch_assoc()) {
            $run = self::normalizeRun($row);
            $runs[$run['id']] = $run;
        }
        $result->free();

        foreach (array_keys($runs) as $runId) {
            $runs[$runId]['items'] = self::databaseRunItems($conn, $runId);
            $runs[$runId]['history'] = self::databaseRunHistory($conn, $runId);
            $runs[$runId]['payment_entries'] = self::paymentEntriesForRun($runId);
        }

        self::$runCache = $runs;

        return $runs;
    }

    private static function normalizeRun(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'period_start' => (string) ($row['period_start'] ?? ''),
            'period_end' => (string) ($row['period_end'] ?? ''),
            'status' => self::normalizeStatus((string) ($row['status'] ?? 'draft')),
            'created_by' => (string) ($row['created_by'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'finalized_at' => self::nullableString($row['finalized_at'] ?? null),
            'approved_at' => self::nullableString($row['approved_at'] ?? null),
            'locked_at' => self::nullableString($row['locked_at'] ?? null),
            'paid_at' => self::nullableString($row['paid_at'] ?? null),
            'cancelled_at' => self::nullableString($row['cancelled_at'] ?? null),
            'status_reason' => (string) ($row['status_reason'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'totals' => [
                'staff_count' => (int) ($row['staff_count'] ?? 0),
                'completed_bookings' => (int) ($row['completed_bookings'] ?? 0),
                'commissionable_value' => (float) ($row['commissionable_value'] ?? 0),
                'base_payout' => (float) ($row['base_payout'] ?? 0),
                'bonus_total' => (float) ($row['bonus_total'] ?? 0),
                'overtime_total' => (float) ($row['overtime_total'] ?? 0),
                'advance_total' => (float) ($row['advance_total'] ?? 0),
                'deduction_total' => (float) ($row['deduction_total'] ?? 0),
                'gross_pay' => (float) ($row['gross_pay'] ?? 0),
                'adjustment_total' => (float) ($row['adjustment_total'] ?? 0),
                'net_payout' => (float) ($row['net_payout'] ?? 0),
            ],
            'items' => [],
            'history' => [],
            'payment_entries' => [],
        ];
    }

    private static function normalizeStatus(string $status): string
    {
        return match (trim($status)) {
            'finalized' => 'locked',
            default => trim($status) !== '' ? trim($status) : 'draft',
        };
    }

    private static function databaseRunItems(mysqli $conn, string $runId): array
    {
        $statement = self::prepare(
            $conn,
            'SELECT id, staff_id, staff_name, role_type, salary_structure, commission_rate, fixed_pay,
                    completed_count, commissionable_value, collected_value, commission_total, base_payout,
                    adjustment, adjustment_note, total_payout, employment_type, commission_model, payment_method,
                    gross_pay, total_deductions, net_pay, snapshot_json
             FROM payroll_run_items
             WHERE run_id = ?
             ORDER BY staff_name ASC',
            [$runId]
        );

        if (!$statement instanceof mysqli_stmt) {
            return [];
        }

        $result = $statement->get_result();
        $statement->close();

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $snapshot = json_decode((string) ($row['snapshot_json'] ?? ''), true);
            $item = is_array($snapshot) ? $snapshot : [];
            $item['id'] = (string) ($row['id'] ?? '');
            $item['staff_id'] = (string) ($row['staff_id'] ?? ($item['staff_id'] ?? ''));
            $item['staff_name'] = (string) ($row['staff_name'] ?? ($item['staff_name'] ?? ''));
            $item['role_type'] = (string) ($row['role_type'] ?? ($item['role_type'] ?? ''));
            $item['salary_structure'] = (string) ($row['salary_structure'] ?? ($item['salary_structure'] ?? 'commission'));
            $item['commission_rate'] = (float) ($row['commission_rate'] ?? ($item['commission_rate'] ?? 0));
            $item['fixed_pay'] = (float) ($row['fixed_pay'] ?? ($item['fixed_pay'] ?? 0));
            $item['completed_count'] = (int) ($row['completed_count'] ?? ($item['completed_count'] ?? 0));
            $item['commissionable_value'] = (float) ($row['commissionable_value'] ?? ($item['commissionable_value'] ?? 0));
            $item['collected_value'] = (float) ($row['collected_value'] ?? ($item['collected_value'] ?? 0));
            $item['commission_total'] = (float) ($row['commission_total'] ?? ($item['commission_total'] ?? 0));
            $item['base_payout'] = (float) ($row['base_payout'] ?? ($item['base_payout'] ?? 0));
            $item['adjustment'] = (float) ($row['adjustment'] ?? ($item['adjustment'] ?? 0));
            $item['adjustment_note'] = (string) ($row['adjustment_note'] ?? ($item['adjustment_note'] ?? ''));
            $item['employment_type'] = (string) ($row['employment_type'] ?? ($item['employment_type'] ?? 'commission'));
            $item['commission_model'] = (string) ($row['commission_model'] ?? ($item['commission_model'] ?? 'percent'));
            $item['payment_method'] = (string) ($row['payment_method'] ?? ($item['payment_method'] ?? 'bank_transfer'));
            $item['gross_pay'] = (float) ($row['gross_pay'] ?? ($item['gross_pay'] ?? 0));
            $item['total_deductions'] = (float) ($row['total_deductions'] ?? ($item['total_deductions'] ?? 0));
            $item['net_pay'] = (float) ($row['net_pay'] ?? ($item['net_pay'] ?? ($row['total_payout'] ?? 0)));
            $item['total_payout'] = (float) ($row['total_payout'] ?? ($item['net_pay'] ?? 0));
            $item['payslip'] = self::payslipForRunStaff($runId, $item['staff_id']);
            $items[] = $item;
        }
        $result->free();

        return $items;
    }

    private static function databaseRunHistory(mysqli $conn, string $runId): array
    {
        $statement = self::prepare(
            $conn,
            'SELECT event_label, event_meta, tone, created_at FROM payroll_run_history WHERE run_id = ? ORDER BY created_at ASC',
            [$runId]
        );

        if (!$statement instanceof mysqli_stmt) {
            return [];
        }

        $result = $statement->get_result();
        $statement->close();

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'label' => (string) ($row['event_label'] ?? ''),
                'meta' => (string) ($row['event_meta'] ?? ''),
                'tone' => (string) ($row['tone'] ?? 'info'),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        $result->free();

        return $history;
    }

    private static function databaseProfiles(): array
    {
        if (self::$profileCache !== null) {
            return self::$profileCache;
        }

        $conn = self::connection();
        if (!$conn instanceof mysqli) {
            self::$profileCache = $_SESSION['payroll_profiles'] ?? [];

            return self::$profileCache;
        }

        $result = $conn->query('SELECT * FROM payroll_profiles ORDER BY updated_at DESC');

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $staffId = (string) ($row['staff_id'] ?? '');
            if ($staffId === '') {
                continue;
            }

            $profiles[$staffId] = [
                'staff_id' => $staffId,
                'employment_type' => (string) ($row['employment_type'] ?? ''),
                'salary_structure' => (string) ($row['salary_structure'] ?? ''),
                'payment_method' => (string) ($row['payment_method'] ?? ''),
                'commission_model' => (string) ($row['commission_model'] ?? ''),
                'commission_rate' => (float) ($row['commission_rate'] ?? 0),
                'commission_per_booking' => (float) ($row['commission_per_booking'] ?? 0),
                'base_salary' => (float) ($row['base_salary'] ?? 0),
                'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
                'overtime_rate' => (float) ($row['overtime_rate'] ?? 0),
                'overtime_eligible' => (int) ($row['overtime_eligible'] ?? 0),
                'tax_percent' => (float) ($row['tax_percent'] ?? 0),
                'pension_percent' => (float) ($row['pension_percent'] ?? 0),
                'nssa_percent' => (float) ($row['nssa_percent'] ?? 0),
                'medical_aid_amount' => (float) ($row['medical_aid_amount'] ?? 0),
                'advance_limit' => (float) ($row['advance_limit'] ?? 0),
                'effective_from' => (string) ($row['effective_from'] ?? date('Y-m-01')),
                'notes' => (string) ($row['notes'] ?? ''),
                'active' => (int) ($row['active'] ?? 1),
            ];
        }
        $result->free();

        self::$profileCache = $profiles;

        return $profiles;
    }

    private static function databaseAdjustments(): array
    {
        if (self::$adjustmentCache !== null) {
            return self::$adjustmentCache;
        }

        $conn = self::connection();
        if (!$conn instanceof mysqli) {
            self::$adjustmentCache = $_SESSION['payroll_adjustments'] ?? [];

            return self::$adjustmentCache;
        }

        $result = $conn->query('SELECT * FROM payroll_adjustments ORDER BY created_at DESC');

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $staffId = (string) ($row['staff_id'] ?? '');
            $member = $staffId !== '' ? Staff::find($staffId) : null;
            $normalized = [
                'id' => (string) ($row['id'] ?? ''),
                'staff_id' => $staffId,
                'staff_name' => (string) ($member['name'] ?? 'Unknown staff'),
                'type' => (string) ($row['type'] ?? 'bonus'),
                'label' => (string) ($row['label'] ?? ''),
                'amount' => (float) ($row['amount'] ?? 0),
                'units' => (float) ($row['units'] ?? 0),
                'rate' => (float) ($row['rate'] ?? 0),
                'period_start' => (string) ($row['period_start'] ?? ''),
                'period_end' => (string) ($row['period_end'] ?? ''),
                'status' => (string) ($row['status'] ?? 'pending'),
                'notes' => (string) ($row['notes'] ?? ''),
                'created_by' => (string) ($row['created_by'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
            $rows[$normalized['id']] = $normalized;
        }
        $result->free();

        self::$adjustmentCache = $rows;

        return $rows;
    }

    private static function databasePayslips(): array
    {
        if (self::$payslipCache !== null) {
            return self::$payslipCache;
        }

        $conn = self::connection();
        if (!$conn instanceof mysqli) {
            self::$payslipCache = $_SESSION['payroll_payslips'] ?? [];

            return self::$payslipCache;
        }

        $result = $conn->query('SELECT * FROM payroll_payslips ORDER BY created_at DESC');

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            $normalized = [
                'id' => (string) ($row['id'] ?? ''),
                'run_id' => (string) ($row['run_id'] ?? ''),
                'run_item_id' => (string) ($row['run_item_id'] ?? ''),
                'staff_id' => (string) ($row['staff_id'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'version' => (int) ($row['version'] ?? 1),
                'gross_pay' => (float) ($row['gross_pay'] ?? 0),
                'deduction_total' => (float) ($row['deduction_total'] ?? 0),
                'net_pay' => (float) ($row['net_pay'] ?? 0),
                'generated_at' => (string) ($row['generated_at'] ?? ''),
                'payload' => is_array($payload) ? $payload : [],
            ];
            $rows[$normalized['id']] = $normalized;
        }
        $result->free();

        self::$payslipCache = $rows;

        return $rows;
    }

    private static function databasePaymentEntries(): array
    {
        if (self::$paymentEntryCache !== null) {
            return self::$paymentEntryCache;
        }

        $conn = self::connection();
        if (!$conn instanceof mysqli) {
            self::$paymentEntryCache = $_SESSION['payroll_payment_entries'] ?? [];

            return self::$paymentEntryCache;
        }

        $result = $conn->query('SELECT * FROM payroll_payment_entries ORDER BY payment_date DESC, created_at DESC');

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $staffId = (string) ($row['staff_id'] ?? '');
            $member = $staffId !== '' ? Staff::find($staffId) : null;
            $normalized = [
                'id' => (string) ($row['id'] ?? ''),
                'run_id' => (string) ($row['run_id'] ?? ''),
                'run_item_id' => (string) ($row['run_item_id'] ?? ''),
                'staff_id' => $staffId,
                'staff_name' => (string) (($row['staff_name_snapshot'] ?? '') !== '' ? $row['staff_name_snapshot'] : ($member['name'] ?? 'Unknown staff')),
                'reference' => (string) ($row['reference'] ?? ''),
                'payment_method' => (string) ($row['payment_method'] ?? 'bank_transfer'),
                'amount' => (float) ($row['amount'] ?? 0),
                'payment_status' => (string) ($row['payment_status'] ?? 'paid'),
                'payment_date' => (string) ($row['payment_date'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'recorded_by' => (string) ($row['recorded_by'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
            $rows[$normalized['id']] = $normalized;
        }
        $result->free();

        self::$paymentEntryCache = $rows;

        return $rows;
    }

    private static function generatePayslips(mysqli $conn, array $run, string $actor): void
    {
        foreach ($run['items'] as $item) {
            if (self::payslipExists($conn, (string) $item['id'])) {
                continue;
            }

            $payload = [
                'run' => [
                    'id' => $run['id'],
                    'reference' => $run['reference'],
                    'label' => $run['label'],
                    'period_start' => $run['period_start'],
                    'period_end' => $run['period_end'],
                ],
                'item' => self::snapshotItem($item),
                'generated_by' => $actor,
            ];

            $params = [
                self::nextId(),
                $run['id'],
                (string) $item['id'],
                (string) $item['staff_id'],
                self::nextLedgerReference($conn, 'payroll_payslips', 'reference', 'PSLIP-'),
                '1',
                (string) round((float) $item['gross_pay'], 2),
                (string) round((float) $item['total_deductions'], 2),
                (string) round((float) $item['net_pay'], 2),
                json_encode($payload, JSON_UNESCAPED_SLASHES),
            ];

            $statement = self::prepare(
                $conn,
                'INSERT INTO payroll_payslips (
                    id, run_id, run_item_id, staff_id, reference, version, gross_pay, deduction_total, net_pay, payload_json, generated_at, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
                $params
            );

            if (!$statement instanceof mysqli_stmt) {
                throw new RuntimeException('Unable to generate payslip.');
            }
            $statement->close();
        }
    }

    private static function postPayrollPayments(mysqli $conn, array $run, string $actor): void
    {
        foreach ($run['items'] as $item) {
            if ((float) $item['net_pay'] <= 0) {
                continue;
            }

            if (self::paymentEntryExists($conn, $run['id'], (string) $item['id'])) {
                continue;
            }

            $params = [
                self::nextId(),
                $run['id'],
                (string) $item['id'],
                (string) $item['staff_id'],
                self::nextLedgerReference($conn, 'payroll_payment_entries', 'reference', 'PAYOUT-'),
                (string) $item['payment_method'],
                (string) round((float) $item['net_pay'], 2),
                'paid',
                date('Y-m-d'),
                'Payroll payout for ' . $run['reference'],
                $actor,
                (string) $item['staff_name'],
            ];

            $statement = self::prepare(
                $conn,
                'INSERT INTO payroll_payment_entries (
                    id, run_id, run_item_id, staff_id, reference, payment_method, amount, payment_status, payment_date, note,
                    recorded_by, staff_name_snapshot, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                $params
            );

            if (!$statement instanceof mysqli_stmt) {
                throw new RuntimeException('Unable to post payroll payment entry.');
            }
            $statement->close();
        }
    }

    private static function payslipExists(mysqli $conn, string $runItemId): bool
    {
        $statement = self::prepare($conn, 'SELECT id FROM payroll_payslips WHERE run_item_id = ? LIMIT 1', [$runItemId]);

        if (!$statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->store_result();
        $exists = $statement->num_rows > 0;
        $statement->close();

        return $exists;
    }

    private static function paymentEntryExists(mysqli $conn, string $runId, string $runItemId): bool
    {
        $statement = self::prepare($conn, 'SELECT id FROM payroll_payment_entries WHERE run_id = ? AND run_item_id = ? LIMIT 1', [$runId, $runItemId]);

        if (!$statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->store_result();
        $exists = $statement->num_rows > 0;
        $statement->close();

        return $exists;
    }

    private static function insertHistory(mysqli $conn, string $runId, string $label, string $meta, string $tone): void
    {
        $statement = self::prepare(
            $conn,
            'INSERT INTO payroll_run_history (id, run_id, event_label, event_meta, tone, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [self::nextId(), $runId, $label, $meta, $tone]
        );

        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
    }

    private static function snapshotItem(array $item): array
    {
        $snapshot = $item;
        unset($snapshot['bookings'], $snapshot['adjustment_rows'], $snapshot['payslip']);

        return $snapshot;
    }

    private static function normalizeRow(array $row): array
    {
        return $row;
    }

    private static function profileRecordId(string $staffId): string
    {
        return 'PAYPROF-' . preg_replace('/[^A-Za-z0-9]/', '', $staffId);
    }

    private static function nextReference(): string
    {
        $conn = self::connection();

        if ($conn instanceof mysqli) {
            return self::nextLedgerReference($conn, 'payroll_runs', 'reference', 'PAYRUN-');
        }

        $max = 0;
        foreach (self::runs() as $run) {
            $max = max($max, (int) preg_replace('/\D+/', '', (string) ($run['reference'] ?? '')));
        }

        return 'PAYRUN-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function nextLedgerReference(mysqli $conn, string $table, string $column, string $prefix): string
    {
        $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
        $safeColumn = preg_replace('/[^a-z0-9_]+/i', '', $column);
        $safePrefix = preg_replace('/[^A-Z0-9-]+/i', '', $prefix);
        $offset = strlen($safePrefix) + 1;
        $query = sprintf(
            "SELECT %s FROM %s WHERE %s REGEXP '^%s[0-9]+$' ORDER BY CAST(SUBSTRING(%s, %d) AS UNSIGNED) DESC LIMIT 1",
            $safeColumn,
            $safeTable,
            $safeColumn,
            $safePrefix,
            $safeColumn,
            $offset
        );
        $result = $conn->query($query);
        $last = 0;

        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc() ?: [];
            $result->free();
            $last = (int) preg_replace('/\D+/', '', (string) ($row[$safeColumn] ?? ''));
        }

        return $safePrefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function nextId(): string
    {
        return function_exists('uuid_v4') ? uuid_v4() : self::fallbackUuid();
    }

    private static function fallbackUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private static function connection(): ?mysqli
    {
        $conn = function_exists('db_connection') ? db_connection() : null;

        if ($conn instanceof mysqli) {
            self::ensureSchema($conn);
        }

        return $conn;
    }

    private static function prepare(mysqli $conn, string $sql, array $params = []): ?mysqli_stmt
    {
        $statement = $conn->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            error_log('PayrollEngine prepare failed: ' . $conn->error);

            return null;
        }

        if ($params !== []) {
            $types = str_repeat('s', count($params));
            if (!$statement->bind_param($types, ...$params)) {
                error_log('PayrollEngine bind failed: ' . $statement->error);
                $statement->close();

                return null;
            }
        }

        if (!$statement->execute()) {
            error_log('PayrollEngine execute failed: ' . $statement->error);
            $statement->close();

            return null;
        }

        return $statement;
    }

    private static function flushCaches(): void
    {
        self::$runCache = null;
        self::$profileCache = null;
        self::$adjustmentCache = null;
        self::$paymentEntryCache = null;
        self::$payslipCache = null;
    }

    private static function ensureSchema(mysqli $conn): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $conn->query(
            'CREATE TABLE IF NOT EXISTS payroll_profiles (
                id VARCHAR(80) NOT NULL PRIMARY KEY,
                staff_id CHAR(36) NOT NULL UNIQUE,
                employment_type VARCHAR(30) NOT NULL DEFAULT \'commission\',
                salary_structure VARCHAR(20) NOT NULL DEFAULT \'commission\',
                payment_method VARCHAR(40) NOT NULL DEFAULT \'bank_transfer\',
                commission_model VARCHAR(30) NOT NULL DEFAULT \'percent\',
                commission_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                commission_per_booking DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                overtime_eligible TINYINT(1) NOT NULL DEFAULT 0,
                tax_percent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                pension_percent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                nssa_percent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                medical_aid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                advance_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                effective_from DATE NOT NULL,
                notes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                updated_by VARCHAR(120) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_payroll_profiles_staff
                    FOREIGN KEY (staff_id) REFERENCES staff (id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,
                KEY idx_payroll_profiles_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS payroll_adjustments (
                id CHAR(36) NOT NULL PRIMARY KEY,
                staff_id CHAR(36) NOT NULL,
                type VARCHAR(20) NOT NULL,
                label VARCHAR(190) NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                units DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                notes TEXT NULL,
                created_by VARCHAR(120) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_payroll_adjustments_staff
                    FOREIGN KEY (staff_id) REFERENCES staff (id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,
                KEY idx_payroll_adjustments_staff_period (staff_id, period_start, period_end),
                KEY idx_payroll_adjustments_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS payroll_payslips (
                id CHAR(36) NOT NULL PRIMARY KEY,
                run_id CHAR(36) NOT NULL,
                run_item_id CHAR(36) NOT NULL UNIQUE,
                staff_id CHAR(36) NULL,
                reference VARCHAR(64) NOT NULL UNIQUE,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                deduction_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payload_json LONGTEXT NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_payroll_payslips_run
                    FOREIGN KEY (run_id) REFERENCES payroll_runs (id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS payroll_payment_entries (
                id CHAR(36) NOT NULL PRIMARY KEY,
                run_id CHAR(36) NOT NULL,
                run_item_id CHAR(36) NOT NULL,
                staff_id CHAR(36) NULL,
                reference VARCHAR(64) NOT NULL UNIQUE,
                payment_method VARCHAR(40) NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_status VARCHAR(20) NOT NULL DEFAULT \'paid\',
                payment_date DATE NOT NULL,
                note TEXT NULL,
                recorded_by VARCHAR(120) NULL,
                staff_name_snapshot VARCHAR(150) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_payroll_payment_entries_run
                    FOREIGN KEY (run_id) REFERENCES payroll_runs (id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,
                KEY idx_payroll_payment_entries_run (run_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureColumn($conn, 'payroll_runs', 'approved_at', 'DATETIME NULL AFTER finalized_at');
        self::ensureColumn($conn, 'payroll_runs', 'locked_at', 'DATETIME NULL AFTER approved_at');
        self::ensureColumn($conn, 'payroll_runs', 'cancelled_at', 'DATETIME NULL AFTER paid_at');
        self::ensureColumn($conn, 'payroll_runs', 'status_reason', 'TEXT NULL AFTER cancelled_at');
        self::ensureColumn($conn, 'payroll_runs', 'bonus_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER adjustment_total');
        self::ensureColumn($conn, 'payroll_runs', 'overtime_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER bonus_total');
        self::ensureColumn($conn, 'payroll_runs', 'advance_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER overtime_total');
        self::ensureColumn($conn, 'payroll_runs', 'deduction_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER advance_total');
        self::ensureColumn($conn, 'payroll_runs', 'gross_pay', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER deduction_total');

        self::ensureColumn($conn, 'payroll_run_items', 'employment_type', 'VARCHAR(30) NULL AFTER total_payout');
        self::ensureColumn($conn, 'payroll_run_items', 'commission_model', 'VARCHAR(30) NULL AFTER employment_type');
        self::ensureColumn($conn, 'payroll_run_items', 'payment_method', 'VARCHAR(40) NULL AFTER commission_model');
        self::ensureColumn($conn, 'payroll_run_items', 'gross_pay', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER payment_method');
        self::ensureColumn($conn, 'payroll_run_items', 'total_deductions', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER gross_pay');
        self::ensureColumn($conn, 'payroll_run_items', 'net_pay', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_deductions');
        self::ensureColumn($conn, 'payroll_run_items', 'snapshot_json', 'LONGTEXT NULL AFTER net_pay');

        self::$schemaEnsured = true;
    }

    private static function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void
    {
        $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
        $safeColumn = preg_replace('/[^a-z0-9_]+/i', '', $column);
        $result = $conn->query(sprintf("SHOW COLUMNS FROM %s LIKE '%s'", $safeTable, $safeColumn));

        if ($result instanceof mysqli_result) {
            $exists = $result->num_rows > 0;
            $result->free();
            if ($exists) {
                return;
            }
        }

        $conn->query(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $safeTable, $safeColumn, $definition));
    }
}
