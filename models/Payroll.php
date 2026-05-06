<?php declare(strict_types=1);

require_once __DIR__ . '/Staff.php';
require_once __DIR__ . '/PayrollEngine.php';

final class Payroll
{
    private static ?array $runCache = null;

    // -------------------------------------------------------------------------
    // Static lookup helpers
    // -------------------------------------------------------------------------

    public static function statuses(): array
    {
        return PayrollEngine::statuses();
    }

    public static function statusLabel(string $status): string
    {
        return PayrollEngine::statusLabel($status);
    }

    public static function statusTone(string $status): string
    {
        return PayrollEngine::statusTone($status);
    }

    public static function adjustmentTypes(): array
    {
        return PayrollEngine::adjustmentTypes();
    }

    public static function adjustmentTypeLabel(string $type): string
    {
        return PayrollEngine::adjustmentTypeLabel($type);
    }

    public static function adjustmentStatuses(): array
    {
        return PayrollEngine::adjustmentStatuses();
    }

    public static function employmentTypes(): array
    {
        return PayrollEngine::employmentTypes();
    }

    public static function commissionModels(): array
    {
        return PayrollEngine::commissionModels();
    }

    public static function staffOptions(): array
    {
        return PayrollEngine::staffOptions();
    }

    public static function currentPeriod(): array
    {
        return PayrollEngine::currentPeriod();
    }

    // -------------------------------------------------------------------------
    // Stats / summaries
    // -------------------------------------------------------------------------

    public static function stats(): array
    {
        return PayrollEngine::stats();
    }

    // -------------------------------------------------------------------------
    // Earnings calculation (live, not snapshotted)
    // -------------------------------------------------------------------------

    public static function earnings(array $filters = []): array
    {
        return PayrollEngine::earnings($filters);
    }

    public static function earningsStats(array $filters = []): array
    {
        return PayrollEngine::earningsStats($filters);
    }

    // -------------------------------------------------------------------------
    // Preview / validate
    // -------------------------------------------------------------------------

    public static function previewRun(array $payload): array
    {
        return PayrollEngine::previewRun($payload);
    }

    public static function validateRunPayload(array $payload): array
    {
        return PayrollEngine::validateRunPayload($payload);
    }

    // -------------------------------------------------------------------------
    // CRUD – runs
    // -------------------------------------------------------------------------

    public static function createRun(array $payload, string $createdBy = 'Admin panel'): array
    {
        return PayrollEngine::createRun($payload, $createdBy);
    }

    public static function transitionRun(string $runId, string $action, string $actor = 'Admin panel', string $reason = ''): ?array
    {
        return PayrollEngine::transitionRun($runId, $action, $actor, $reason);
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public static function runs(array $filters = []): array
    {
        return PayrollEngine::runs($filters);
    }

    public static function find(string $runId): ?array
    {
        return PayrollEngine::find($runId);
    }

    public static function recentRuns(int $limit = 5): array
    {
        return PayrollEngine::recentRuns($limit);
    }

    public static function profiles(array $filters = []): array
    {
        return PayrollEngine::profiles($filters);
    }

    public static function profileForStaff(string $staffId): array
    {
        return PayrollEngine::profileForStaff($staffId);
    }

    public static function validateProfilePayload(array $payload): array
    {
        return PayrollEngine::validateProfilePayload($payload);
    }

    public static function saveProfile(array $payload, string $actor = 'Admin panel'): ?array
    {
        return PayrollEngine::saveProfile($payload, $actor);
    }

    public static function adjustments(array $filters = []): array
    {
        return PayrollEngine::adjustments($filters);
    }

    public static function findAdjustment(string $id): ?array
    {
        return PayrollEngine::findAdjustment($id);
    }

    public static function validateAdjustmentPayload(array $payload): array
    {
        return PayrollEngine::validateAdjustmentPayload($payload);
    }

    public static function saveAdjustment(array $payload, string $actor = 'Admin panel'): ?array
    {
        return PayrollEngine::saveAdjustment($payload, $actor);
    }

    public static function transitionAdjustment(string $adjustmentId, string $action, string $actor = 'Admin panel'): ?array
    {
        return PayrollEngine::transitionAdjustment($adjustmentId, $action, $actor);
    }

    public static function payslipForRunStaff(string $runId, string $staffId): ?array
    {
        return PayrollEngine::payslipForRunStaff($runId, $staffId);
    }

    public static function paymentEntriesForRun(string $runId): array
    {
        return PayrollEngine::paymentEntriesForRun($runId);
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
