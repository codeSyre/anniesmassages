<?php declare(strict_types=1);

require_once __DIR__ . '/Staff.php';

final class Payroll
{
    private static ?array $runCache = null;

    // -------------------------------------------------------------------------
    // Static lookup helpers
    // -------------------------------------------------------------------------

    public static function statuses(): array
    {
        return ['draft', 'finalized', 'paid'];
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'paid'      => 'success',
            'finalized' => 'info',
            default     => 'warning',
        };
    }

    public static function staffOptions(): array
    {
        $staff = array_map(static fn (array $m): array => [
            'id'               => $m['id'],
            'name'             => $m['name'],
            'salary_structure' => $m['salary_structure'],
        ], self::eligibleStaff());

        usort($staff, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $staff;
    }

    public static function currentPeriod(): array
    {
        return [
            'period_start' => date('Y-m-01'),
            'period_end'   => date('Y-m-t'),
        ];
    }

    // -------------------------------------------------------------------------
    // Stats / summaries
    // -------------------------------------------------------------------------

    public static function stats(): array
    {
        $preview      = self::previewRun(self::currentPeriod() + ['selected_staff' => []]);
        $runs         = self::runs();
        $pendingRuns  = array_filter($runs, static fn (array $r): bool => $r['status'] !== 'paid');
        $paidRuns     = array_filter($runs, static fn (array $r): bool => $r['status'] === 'paid');
        $pendingValue = array_sum(array_map(static fn (array $r): float => (float) $r['net_payout'], $pendingRuns));
        $paidValue    = array_sum(array_map(static fn (array $r): float => (float) $r['net_payout'], $paidRuns));

        return [
            ['label' => 'Current projected payout', 'value' => format_money((float) $preview['totals']['net_payout']), 'tone' => 'warning'],
            ['label' => 'Pending payroll runs',      'value' => (string) count($pendingRuns),                          'tone' => 'info'],
            ['label' => 'Paid payroll value',        'value' => format_money($paidValue),                              'tone' => 'success'],
            ['label' => 'Unpaid run value',          'value' => format_money($pendingValue),                           'tone' => 'danger'],
        ];
    }

    // -------------------------------------------------------------------------
    // Earnings calculation (live, not snapshotted)
    // -------------------------------------------------------------------------

    public static function earnings(array $filters = []): array
    {
        $periodStart = (string) ($filters['period_start'] ?? date('Y-m-01'));
        $periodEnd   = (string) ($filters['period_end']   ?? date('Y-m-t'));
        $selectedIds = self::normalizeSelectedStaff($filters['selected_staff'] ?? ($filters['staff_id'] ?? 'all'));
        $staff       = self::eligibleStaff($selectedIds);
        $rows        = [];

        foreach ($staff as $member) {
            $completedBookings = array_values(array_filter(
                Staff::bookings($member['id']),
                static fn (array $b): bool =>
                    $b['status'] === 'completed'
                    && $b['date'] >= $periodStart
                    && $b['date'] <= $periodEnd
            ));

            usort($completedBookings, static fn (array $a, array $b): int => strcmp($b['sort_key'], $a['sort_key']));

            $commissionableValue = array_sum(array_map(static fn (array $b): float => (float) $b['amount_total'], $completedBookings));
            $collectedValue      = array_sum(array_map(static fn (array $b): float => (float) $b['amount_paid'],  $completedBookings));
            $commissionRate      = (float) $member['commission_rate'];
            $commissionTotal     = round($commissionableValue * ($commissionRate / 100), 2);
            $basePayout          = match ($member['salary_structure']) {
                'fixed'  => (float) $member['fixed_pay'],
                'hybrid' => (float) $member['fixed_pay'] + $commissionTotal,
                default  => $commissionTotal,
            };

            $rows[] = [
                'staff_id'            => $member['id'],
                'staff_name'          => $member['name'],
                'role_type'           => $member['role_type'],
                'salary_structure'    => $member['salary_structure'],
                'commission_rate'     => $commissionRate,
                'fixed_pay'           => (float) $member['fixed_pay'],
                'completed_count'     => count($completedBookings),
                'commissionable_value'=> $commissionableValue,
                'collected_value'     => $collectedValue,
                'commission_total'    => $commissionTotal,
                'base_payout'         => round($basePayout, 2),
                'adjustment'          => 0.0,
                'adjustment_note'     => '',
                'total_payout'        => round($basePayout, 2),
                'bookings'            => $completedBookings,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['staff_name'], $b['staff_name']));

        return $rows;
    }

    public static function earningsStats(array $filters = []): array
    {
        $rows       = self::earnings($filters);
        $net        = array_sum(array_map(static fn (array $r): float => (float) $r['total_payout'],    $rows));
        $commission = array_sum(array_map(static fn (array $r): float => (float) $r['commission_total'], $rows));
        $fixed      = array_sum(array_map(static function (array $r): float {
            return in_array($r['salary_structure'], ['fixed', 'hybrid'], true) ? (float) $r['fixed_pay'] : 0.0;
        }, $rows));
        $completed  = array_sum(array_map(static fn (array $r): int => (int) $r['completed_count'], $rows));

        return [
            ['label' => 'Estimated payout',           'value' => format_money($net),        'tone' => 'warning'],
            ['label' => 'Commission total',            'value' => format_money($commission), 'tone' => 'success'],
            ['label' => 'Fixed-pay base',              'value' => format_money($fixed),      'tone' => 'info'],
            ['label' => 'Completed bookings counted',  'value' => (string) $completed,       'tone' => 'info'],
        ];
    }

    // -------------------------------------------------------------------------
    // Preview / validate
    // -------------------------------------------------------------------------

    public static function previewRun(array $payload): array
    {
        $periodStart   = (string) ($payload['period_start']   ?? date('Y-m-01'));
        $periodEnd     = (string) ($payload['period_end']     ?? date('Y-m-t'));
        $selectedStaff = self::normalizeSelectedStaff($payload['selected_staff'] ?? []);
        $adjustments   = is_array($payload['adjustments']  ?? null) ? $payload['adjustments']  : [];
        $staffNotes    = is_array($payload['staff_notes']   ?? null) ? $payload['staff_notes']  : [];

        $rows  = self::earnings([
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'selected_staff' => $selectedStaff,
        ]);

        $items = array_map(static function (array $row) use ($adjustments, $staffNotes): array {
            $adjustment = round((float) ($adjustments[$row['staff_id']] ?? 0), 2);
            $note       = trim((string) ($staffNotes[$row['staff_id']] ?? ''));
            $row['adjustment']      = $adjustment;
            $row['adjustment_note'] = $note;
            $row['total_payout']    = round((float) $row['base_payout'] + $adjustment, 2);
            return $row;
        }, $rows);

        return [
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'items'        => $items,
            'totals'       => [
                'staff_count'          => count($items),
                'completed_bookings'   => array_sum(array_map(static fn (array $i): int   => (int)   $i['completed_count'],      $items)),
                'commissionable_value' => array_sum(array_map(static fn (array $i): float => (float) $i['commissionable_value'], $items)),
                'base_payout'          => array_sum(array_map(static fn (array $i): float => (float) $i['base_payout'],          $items)),
                'adjustment_total'     => array_sum(array_map(static fn (array $i): float => (float) $i['adjustment'],           $items)),
                'net_payout'           => array_sum(array_map(static fn (array $i): float => (float) $i['total_payout'],         $items)),
            ],
        ];
    }

    public static function validateRunPayload(array $payload): array
    {
        $errors      = [];
        $periodStart = trim((string) ($payload['period_start'] ?? ''));
        $periodEnd   = trim((string) ($payload['period_end']   ?? ''));

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

        $preview = self::previewRun($payload);

        if ($preview['items'] === []) {
            $errors['selected_staff'] = 'Choose at least one eligible staff member or widen the period.';
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // CRUD – runs
    // -------------------------------------------------------------------------

    public static function createRun(array $payload, string $createdBy = 'Admin panel'): array
    {
        $preview     = self::previewRun($payload);
        $runId       = self::nextId();
        $reference   = self::nextReference();
        $periodLabel = date('j M', strtotime($preview['period_start'])) . ' - ' . date('j M Y', strtotime($preview['period_end']));
        $label       = trim((string) ($payload['label'] ?? ''));
        $notes       = trim((string) ($payload['notes'] ?? ''));

        $run = [
            'id'           => $runId,
            'reference'    => $reference,
            'label'        => $label !== '' ? $label : 'Payroll run ' . $periodLabel,
            'period_start' => $preview['period_start'],
            'period_end'   => $preview['period_end'],
            'status'       => 'draft',
            'created_by'   => $createdBy,
            'created_at'   => date('Y-m-d H:i:s'),
            'finalized_at' => null,
            'paid_at'      => null,
            'notes'        => $notes,
            'totals'       => $preview['totals'],
            'items'        => array_map(static fn (array $item): array => [
                'staff_id'             => $item['staff_id'],
                'staff_name'           => $item['staff_name'],
                'role_type'            => $item['role_type'],
                'salary_structure'     => $item['salary_structure'],
                'commission_rate'      => $item['commission_rate'],
                'fixed_pay'            => $item['fixed_pay'],
                'completed_count'      => $item['completed_count'],
                'commissionable_value' => $item['commissionable_value'],
                'collected_value'      => $item['collected_value'],
                'commission_total'     => $item['commission_total'],
                'base_payout'          => $item['base_payout'],
                'adjustment'           => $item['adjustment'],
                'adjustment_note'      => $item['adjustment_note'],
                'total_payout'         => $item['total_payout'],
            ], $preview['items']),
            'history' => [[
                'label' => 'Payroll run created',
                'meta'  => $createdBy . ' generated this run from the payroll workspace.',
                'tone'  => 'info',
            ]],
        ];

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = self::prepare($conn,
                    'INSERT INTO payroll_runs
                        (id, reference, label, period_start, period_end, status, created_by, notes,
                         staff_count, completed_bookings, commissionable_value, base_payout, adjustment_total, net_payout, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    'ssssssssiidddd',
                    [
                        $runId, $reference, $run['label'],
                        $preview['period_start'], $preview['period_end'],
                        'draft', $createdBy, $notes,
                        $preview['totals']['staff_count'],
                        $preview['totals']['completed_bookings'],
                        $preview['totals']['commissionable_value'],
                        $preview['totals']['base_payout'],
                        $preview['totals']['adjustment_total'],
                        $preview['totals']['net_payout'],
                    ]
                );
                if (!$stmt instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to insert payroll run.');
                }
                $stmt->close();

                foreach ($run['items'] as $item) {
                    $itemId = self::nextId();
                    $si = self::prepare($conn,
                        'INSERT INTO payroll_run_items
                            (id, run_id, staff_id, staff_name, role_type, salary_structure,
                             commission_rate, fixed_pay, completed_count, commissionable_value,
                             collected_value, commission_total, base_payout, adjustment, adjustment_note, total_payout)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        'ssssssddiiddddsd',
                        [
                            $itemId, $runId,
                            $item['staff_id'] !== '' ? $item['staff_id'] : null,
                            $item['staff_name'], $item['role_type'], $item['salary_structure'],
                            $item['commission_rate'], $item['fixed_pay'],
                            $item['completed_count'], $item['commissionable_value'],
                            $item['collected_value'], $item['commission_total'],
                            $item['base_payout'], $item['adjustment'],
                            $item['adjustment_note'], $item['total_payout'],
                        ]
                    );
                    if (!$si instanceof mysqli_stmt) {
                        throw new RuntimeException('Unable to insert payroll run item.');
                    }
                    $si->close();
                }

                self::insertHistory($conn, $runId, 'Payroll run created', $createdBy . ' generated this run from the payroll workspace.', 'info');

                mysqli_commit($conn);
                self::$runCache = null;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log('Payroll createRun failed: ' . $e->getMessage());
                throw $e;
            }
        }

        return $run;
    }

    public static function transitionRun(string $runId, string $action, string $actor = 'Admin panel'): ?array
    {
        $run = self::find($runId);

        if ($run === null) {
            return null;
        }

        $newStatus    = $run['status'];
        $finalizedAt  = $run['finalized_at'];
        $paidAt       = $run['paid_at'];
        $historyLabel = '';
        $historyMeta  = '';
        $historyTone  = 'info';

        if ($action === 'finalize' && $run['status'] === 'draft') {
            $newStatus    = 'finalized';
            $finalizedAt  = date('Y-m-d H:i:s');
            $historyLabel = 'Run finalized';
            $historyMeta  = $actor . ' locked the run for audit and payout.';
            $historyTone  = 'warning';
        } elseif ($action === 'pay' && in_array($run['status'], ['draft', 'finalized'], true)) {
            $newStatus    = 'paid';
            $paidAt       = date('Y-m-d H:i:s');
            $finalizedAt  = $finalizedAt ?? $paidAt;
            $historyLabel = 'Run marked as paid';
            $historyMeta  = $actor . ' recorded the payout as completed.';
            $historyTone  = 'success';
        } else {
            return $run;
        }

        $conn = self::connection();

        if ($conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = self::prepare($conn,
                    'UPDATE payroll_runs SET status = ?, finalized_at = ?, paid_at = ?, updated_at = NOW() WHERE id = ?',
                    'ssss',
                    [$newStatus, $finalizedAt, $paidAt, $runId]
                );
                if (!$stmt instanceof mysqli_stmt) {
                    throw new RuntimeException('Unable to update payroll run status.');
                }
                $stmt->close();

                self::insertHistory($conn, $runId, $historyLabel, $historyMeta, $historyTone);

                mysqli_commit($conn);
                self::$runCache = null;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log('Payroll transitionRun failed: ' . $e->getMessage());
                throw $e;
            }
        }

        return self::find($runId);
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public static function runs(array $filters = []): array
    {
        $runs   = array_values(self::databaseRuns());
        $status = (string) ($filters['status'] ?? 'all');
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        $runs = array_values(array_filter($runs, static function (array $run) use ($status, $search): bool {
            if ($status !== 'all' && $run['status'] !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            $haystack = strtolower(implode(' ', [$run['reference'], $run['label'], $run['notes']]));
            return str_contains($haystack, $search);
        }));

        usort($runs, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

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

    // -------------------------------------------------------------------------
    // Private – DB reads
    // -------------------------------------------------------------------------

    private static function databaseRuns(): array
    {
        if (self::$runCache !== null) {
            return self::$runCache;
        }

        $conn = self::connection();

        if (!$conn instanceof mysqli) {
            return [];
        }

        $result = $conn->query(
            'SELECT id, reference, label, period_start, period_end, status, created_by,
                    finalized_at, paid_at, notes,
                    staff_count, completed_bookings, commissionable_value,
                    base_payout, adjustment_total, net_payout, created_at
             FROM payroll_runs
             ORDER BY created_at DESC'
        );

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $runs = [];
        while ($row = $result->fetch_assoc()) {
            $id       = (string) $row['id'];
            $runs[$id] = self::normalizeRun($row);
        }
        $result->free();

        // Attach items and history for each run
        foreach (array_keys($runs) as $id) {
            $runs[$id]['items']   = self::databaseRunItems($conn, $id);
            $runs[$id]['history'] = self::databaseRunHistory($conn, $id);
        }

        self::$runCache = $runs;

        return $runs;
    }

    private static function normalizeRun(array $row): array
    {
        return [
            'id'           => (string) $row['id'],
            'reference'    => (string) ($row['reference']  ?? ''),
            'label'        => (string) ($row['label']      ?? ''),
            'period_start' => (string) ($row['period_start'] ?? ''),
            'period_end'   => (string) ($row['period_end']   ?? ''),
            'status'       => (string) ($row['status']     ?? 'draft'),
            'created_by'   => (string) ($row['created_by'] ?? ''),
            'created_at'   => (string) ($row['created_at'] ?? ''),
            'finalized_at' => ($row['finalized_at'] ?? '') !== '' ? (string) $row['finalized_at'] : null,
            'paid_at'      => ($row['paid_at']      ?? '') !== '' ? (string) $row['paid_at']      : null,
            'notes'        => (string) ($row['notes'] ?? ''),
            'totals'       => [
                'staff_count'          => (int)   ($row['staff_count']          ?? 0),
                'completed_bookings'   => (int)   ($row['completed_bookings']   ?? 0),
                'commissionable_value' => (float) ($row['commissionable_value'] ?? 0),
                'base_payout'          => (float) ($row['base_payout']          ?? 0),
                'adjustment_total'     => (float) ($row['adjustment_total']     ?? 0),
                'net_payout'           => (float) ($row['net_payout']           ?? 0),
            ],
            'items'   => [],
            'history' => [],
        ];
    }

    private static function databaseRunItems(mysqli $conn, string $runId): array
    {
        $stmt = self::prepare($conn,
            'SELECT id, staff_id, staff_name, role_type, salary_structure,
                    commission_rate, fixed_pay, completed_count, commissionable_value,
                    collected_value, commission_total, base_payout, adjustment, adjustment_note, total_payout
             FROM payroll_run_items WHERE run_id = ? ORDER BY staff_name ASC',
            's', [$runId]
        );

        if (!$stmt instanceof mysqli_stmt) {
            return [];
        }

        $result = $stmt->get_result();
        $stmt->close();

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'staff_id'             => (string) ($row['staff_id']             ?? ''),
                'staff_name'           => (string) ($row['staff_name']           ?? ''),
                'role_type'            => (string) ($row['role_type']            ?? ''),
                'salary_structure'     => (string) ($row['salary_structure']     ?? ''),
                'commission_rate'      => (float)  ($row['commission_rate']      ?? 0),
                'fixed_pay'            => (float)  ($row['fixed_pay']            ?? 0),
                'completed_count'      => (int)    ($row['completed_count']      ?? 0),
                'commissionable_value' => (float)  ($row['commissionable_value'] ?? 0),
                'collected_value'      => (float)  ($row['collected_value']      ?? 0),
                'commission_total'     => (float)  ($row['commission_total']     ?? 0),
                'base_payout'          => (float)  ($row['base_payout']          ?? 0),
                'adjustment'           => (float)  ($row['adjustment']           ?? 0),
                'adjustment_note'      => (string) ($row['adjustment_note']      ?? ''),
                'total_payout'         => (float)  ($row['total_payout']         ?? 0),
            ];
        }
        $result->free();

        return $items;
    }

    private static function databaseRunHistory(mysqli $conn, string $runId): array
    {
        $stmt = self::prepare($conn,
            'SELECT event_label, event_meta, tone FROM payroll_run_history WHERE run_id = ? ORDER BY created_at ASC',
            's', [$runId]
        );

        if (!$stmt instanceof mysqli_stmt) {
            return [];
        }

        $result = $stmt->get_result();
        $stmt->close();

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'label' => (string) ($row['event_label'] ?? ''),
                'meta'  => (string) ($row['event_meta']  ?? ''),
                'tone'  => (string) ($row['tone']        ?? 'info'),
            ];
        }
        $result->free();

        return $history;
    }

    private static function insertHistory(mysqli $conn, string $runId, string $label, string $meta, string $tone): void
    {
        $stmt = self::prepare($conn,
            'INSERT INTO payroll_run_history (id, run_id, event_label, event_meta, tone, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            'sssss',
            [self::nextId(), $runId, $label, $meta, $tone]
        );
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }

    // -------------------------------------------------------------------------
    // Private – staff helpers
    // -------------------------------------------------------------------------

    private static function eligibleStaff(array $selectedIds = []): array
    {
        $staff          = Staff::all();
        $selectedLookup = array_fill_keys($selectedIds, true);

        return array_values(array_filter($staff, static function (array $m) use ($selectedLookup): bool {
            if ($selectedLookup !== [] && !isset($selectedLookup[$m['id']])) {
                return false;
            }
            return $m['status'] !== 'terminated';
        }));
    }

    private static function normalizeSelectedStaff(mixed $value): array
    {
        if ($value === 'all' || $value === null || $value === '') {
            return [];
        }
        $values = is_array($value) ? $value : [$value];
        $values = array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $values));
        return array_values(array_unique($values));
    }

    // -------------------------------------------------------------------------
    // Private – DB utilities
    // -------------------------------------------------------------------------

    private static function nextId(): string
    {
        return function_exists('uuid_v4') ? uuid_v4() : self::fallbackUuid();
    }

    private static function nextReference(): string
    {
        $conn = self::connection();
        $last = 0;

        if ($conn instanceof mysqli) {
            $result = $conn->query("SELECT reference FROM payroll_runs ORDER BY created_at DESC LIMIT 1");
            if ($result instanceof mysqli_result) {
                $row = $result->fetch_row();
                $result->free();
                if ($row !== null) {
                    $last = (int) preg_replace('/\D+/', '', (string) $row[0]);
                }
            }
        }

        return 'PAYRUN-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function fallbackUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function connection(): ?mysqli
    {
        return function_exists('db_connection') ? db_connection() : null;
    }

    private static function prepare(mysqli $conn, string $sql, string $types, array $params): ?mysqli_stmt
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt instanceof mysqli_stmt) {
            return null;
        }
        if ($types !== '' && $params !== []) {
            $refs = [$types];
            foreach ($params as $i => $v) {
                $refs[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        $stmt->execute();
        return $stmt;
    }
}
