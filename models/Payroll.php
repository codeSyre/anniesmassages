<?php declare(strict_types=1);

require_once __DIR__ . '/Staff.php';

final class Payroll
{
    public static function statuses(): array
    {
        return ['draft', 'finalized', 'paid'];
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'finalized' => 'info',
            default => 'warning',
        };
    }

    public static function staffOptions(): array
    {
        $staff = array_map(static function (array $member): array {
            return [
                'id' => $member['id'],
                'name' => $member['name'],
                'salary_structure' => $member['salary_structure'],
            ];
        }, self::eligibleStaff());

        usort($staff, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $staff;
    }

    public static function stats(): array
    {
        $currentPeriod = self::currentPeriod();
        $preview = self::previewRun($currentPeriod + ['selected_staff' => []]);
        $runs = self::runs();
        $pendingRuns = array_filter($runs, static fn (array $run): bool => $run['status'] !== 'paid');
        $paidRuns = array_filter($runs, static fn (array $run): bool => $run['status'] === 'paid');
        $pendingValue = array_sum(array_map(static fn (array $run): float => (float) $run['net_payout'], $pendingRuns));
        $paidValue = array_sum(array_map(static fn (array $run): float => (float) $run['net_payout'], $paidRuns));

        return [
            ['label' => 'Current projected payout', 'value' => format_money((float) $preview['totals']['net_payout']), 'tone' => 'warning'],
            ['label' => 'Pending payroll runs', 'value' => (string) count($pendingRuns), 'tone' => 'info'],
            ['label' => 'Paid payroll value', 'value' => format_money($paidValue), 'tone' => 'success'],
            ['label' => 'Unpaid run value', 'value' => format_money($pendingValue), 'tone' => 'danger'],
        ];
    }

    public static function earnings(array $filters = []): array
    {
        $periodStart = (string) ($filters['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($filters['period_end'] ?? date('Y-m-t'));
        $selectedIds = self::normalizeSelectedStaff($filters['selected_staff'] ?? ($filters['staff_id'] ?? 'all'));
        $staff = self::eligibleStaff($selectedIds);
        $rows = [];

        foreach ($staff as $member) {
            $completedBookings = array_values(array_filter(Staff::bookings($member['id']), static function (array $booking) use ($periodStart, $periodEnd): bool {
                return $booking['status'] === 'completed'
                    && $booking['date'] >= $periodStart
                    && $booking['date'] <= $periodEnd;
            }));

            usort($completedBookings, static fn (array $left, array $right): int => strcmp($right['sort_key'], $left['sort_key']));

            $commissionableValue = array_sum(array_map(static fn (array $booking): float => (float) $booking['amount_total'], $completedBookings));
            $collectedValue = array_sum(array_map(static fn (array $booking): float => (float) $booking['amount_paid'], $completedBookings));
            $commissionRate = (float) $member['commission_rate'];
            $commissionTotal = round($commissionableValue * ($commissionRate / 100), 2);
            $basePayout = match ($member['salary_structure']) {
                'fixed' => (float) $member['fixed_pay'],
                'hybrid' => (float) $member['fixed_pay'] + $commissionTotal,
                default => $commissionTotal,
            };

            $rows[] = [
                'staff_id' => $member['id'],
                'staff_name' => $member['name'],
                'role_type' => $member['role_type'],
                'salary_structure' => $member['salary_structure'],
                'commission_rate' => $commissionRate,
                'fixed_pay' => (float) $member['fixed_pay'],
                'completed_count' => count($completedBookings),
                'commissionable_value' => $commissionableValue,
                'collected_value' => $collectedValue,
                'commission_total' => $commissionTotal,
                'base_payout' => round($basePayout, 2),
                'adjustment' => 0.0,
                'adjustment_note' => '',
                'total_payout' => round($basePayout, 2),
                'bookings' => $completedBookings,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strcmp($left['staff_name'], $right['staff_name']));

        return $rows;
    }

    public static function earningsStats(array $filters = []): array
    {
        $rows = self::earnings($filters);
        $net = array_sum(array_map(static fn (array $row): float => (float) $row['total_payout'], $rows));
        $commission = array_sum(array_map(static fn (array $row): float => (float) $row['commission_total'], $rows));
        $fixed = array_sum(array_map(static function (array $row): float {
            return in_array($row['salary_structure'], ['fixed', 'hybrid'], true) ? (float) $row['fixed_pay'] : 0.0;
        }, $rows));
        $completed = array_sum(array_map(static fn (array $row): int => (int) $row['completed_count'], $rows));

        return [
            ['label' => 'Estimated payout', 'value' => format_money($net), 'tone' => 'warning'],
            ['label' => 'Commission total', 'value' => format_money($commission), 'tone' => 'success'],
            ['label' => 'Fixed-pay base', 'value' => format_money($fixed), 'tone' => 'info'],
            ['label' => 'Completed bookings counted', 'value' => (string) $completed, 'tone' => 'info'],
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
            $adjustment = round((float) ($adjustments[$row['staff_id']] ?? 0), 2);
            $note = trim((string) ($staffNotes[$row['staff_id']] ?? ''));

            $row['adjustment'] = $adjustment;
            $row['adjustment_note'] = $note;
            $row['total_payout'] = round((float) $row['base_payout'] + $adjustment, 2);

            return $row;
        }, $rows);

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'items' => $items,
            'totals' => [
                'staff_count' => count($items),
                'completed_bookings' => array_sum(array_map(static fn (array $item): int => (int) $item['completed_count'], $items)),
                'commissionable_value' => array_sum(array_map(static fn (array $item): float => (float) $item['commissionable_value'], $items)),
                'base_payout' => array_sum(array_map(static fn (array $item): float => (float) $item['base_payout'], $items)),
                'adjustment_total' => array_sum(array_map(static fn (array $item): float => (float) $item['adjustment'], $items)),
                'net_payout' => array_sum(array_map(static fn (array $item): float => (float) $item['total_payout'], $items)),
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

        $preview = self::previewRun($payload);

        if ($preview['items'] === []) {
            $errors['selected_staff'] = 'Choose at least one eligible staff member or widen the period.';
        }

        return $errors;
    }

    public static function createRun(array $payload, string $createdBy = 'Admin panel'): array
    {
        $preview = self::previewRun($payload);
        $runs = $_SESSION['payroll_runs'] ?? [];
        $runId = self::nextId();
        $reference = 'PAYRUN-' . preg_replace('/\D+/', '', $runId);
        $periodLabel = date('j M', strtotime($preview['period_start'])) . ' - ' . date('j M Y', strtotime($preview['period_end']));
        $label = trim((string) ($payload['label'] ?? ''));

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
            'paid_at' => null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'totals' => $preview['totals'],
            'items' => array_map(static function (array $item): array {
                return [
                    'staff_id' => $item['staff_id'],
                    'staff_name' => $item['staff_name'],
                    'role_type' => $item['role_type'],
                    'salary_structure' => $item['salary_structure'],
                    'commission_rate' => $item['commission_rate'],
                    'fixed_pay' => $item['fixed_pay'],
                    'completed_count' => $item['completed_count'],
                    'commissionable_value' => $item['commissionable_value'],
                    'collected_value' => $item['collected_value'],
                    'commission_total' => $item['commission_total'],
                    'base_payout' => $item['base_payout'],
                    'adjustment' => $item['adjustment'],
                    'adjustment_note' => $item['adjustment_note'],
                    'total_payout' => $item['total_payout'],
                ];
            }, $preview['items']),
            'history' => [
                [
                    'label' => 'Payroll run created',
                    'meta' => $createdBy . ' generated this run from the payroll workspace.',
                    'tone' => 'info',
                ],
            ],
        ];

        $runs[$runId] = $run;
        $_SESSION['payroll_runs'] = $runs;

        return $run;
    }

    public static function transitionRun(string $runId, string $action, string $actor = 'Admin panel'): ?array
    {
        $runs = $_SESSION['payroll_runs'] ?? [];
        $run = $runs[$runId] ?? self::find($runId);

        if ($run === null) {
            return null;
        }

        if ($action === 'finalize' && $run['status'] === 'draft') {
            $run['status'] = 'finalized';
            $run['finalized_at'] = date('Y-m-d H:i:s');
            $run['history'][] = [
                'label' => 'Run finalized',
                'meta' => $actor . ' locked the run for audit and payout.',
                'tone' => 'warning',
            ];
        }

        if ($action === 'pay' && in_array($run['status'], ['draft', 'finalized'], true)) {
            $run['status'] = 'paid';
            $run['paid_at'] = date('Y-m-d H:i:s');
            if ($run['finalized_at'] === null) {
                $run['finalized_at'] = $run['paid_at'];
            }
            $run['history'][] = [
                'label' => 'Run marked as paid',
                'meta' => $actor . ' recorded the payout as completed.',
                'tone' => 'success',
            ];
        }

        $runs[$runId] = $run;
        $_SESSION['payroll_runs'] = $runs;

        return $run;
    }

    public static function runs(array $filters = []): array
    {
        $runs = array_values(self::mergedRuns());
        $status = (string) ($filters['status'] ?? 'all');
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        $runs = array_values(array_filter($runs, static function (array $run) use ($status, $search): bool {
            if ($status !== 'all' && $run['status'] !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $run['reference'],
                $run['label'],
                $run['notes'],
            ]));

            return str_contains($haystack, $search);
        }));

        usort($runs, static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));

        return $runs;
    }

    public static function find(string $runId): ?array
    {
        return self::mergedRuns()[$runId] ?? null;
    }

    public static function recentRuns(int $limit = 5): array
    {
        return array_slice(self::runs(), 0, $limit);
    }

    public static function currentPeriod(): array
    {
        return [
            'period_start' => date('Y-m-01'),
            'period_end' => date('Y-m-t'),
        ];
    }

    private static function eligibleStaff(array $selectedIds = []): array
    {
        $staff = Staff::all();
        $selectedLookup = array_fill_keys($selectedIds, true);

        return array_values(array_filter($staff, static function (array $member) use ($selectedLookup): bool {
            if ($selectedLookup !== [] && !isset($selectedLookup[$member['id']])) {
                return false;
            }

            return $member['status'] !== 'terminated';
        }));
    }

    private static function normalizeSelectedStaff(mixed $value): array
    {
        if ($value === 'all' || $value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_filter(array_map(static fn (mixed $staffId): string => trim((string) $staffId), $values));

        return array_values(array_unique($values));
    }

    private static function mergedRuns(): array
    {
        $runs = self::baseRuns();

        foreach ($_SESSION['payroll_runs'] ?? [] as $id => $run) {
            $runs[$id] = $run;
        }

        return $runs;
    }

    private static function nextId(): string
    {
        $max = 8000;

        foreach (array_keys(self::mergedRuns()) as $id) {
            $max = max($max, (int) preg_replace('/\D+/', '', $id));
        }

        return 'run-' . ($max + 1);
    }

    private static function baseRuns(): array
    {
        return [
            'run-8001' => [
                'id' => 'run-8001',
                'reference' => 'PAYRUN-8001',
                'label' => 'April Payroll Closeout',
                'period_start' => date('Y-m-01', strtotime('first day of last month')),
                'period_end' => date('Y-m-t', strtotime('last day of last month')),
                'status' => 'paid',
                'created_by' => 'Annie Admin',
                'created_at' => date('Y-m-d H:i:s', strtotime('first day of this month 09:00')),
                'finalized_at' => date('Y-m-d H:i:s', strtotime('first day of this month 12:00')),
                'paid_at' => date('Y-m-d H:i:s', strtotime('first day of this month +1 day 10:00')),
                'notes' => 'Month-end payroll approved after front-desk reconciliation.',
                'totals' => [
                    'staff_count' => 4,
                    'completed_bookings' => 26,
                    'commissionable_value' => 1760.00,
                    'base_payout' => 646.70,
                    'adjustment_total' => 25.00,
                    'net_payout' => 671.70,
                ],
                'items' => [
                    [
                        'staff_id' => 'stf-tariro',
                        'staff_name' => 'Tariro Moyo',
                        'role_type' => 'therapist',
                        'salary_structure' => 'commission',
                        'commission_rate' => 28.0,
                        'fixed_pay' => 0.0,
                        'completed_count' => 7,
                        'commissionable_value' => 420.00,
                        'collected_value' => 420.00,
                        'commission_total' => 117.60,
                        'base_payout' => 117.60,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 117.60,
                    ],
                    [
                        'staff_id' => 'stf-amanda',
                        'staff_name' => 'Amanda Sibanda',
                        'role_type' => 'senior therapist',
                        'salary_structure' => 'hybrid',
                        'commission_rate' => 22.0,
                        'fixed_pay' => 110.0,
                        'completed_count' => 8,
                        'commissionable_value' => 496.00,
                        'collected_value' => 496.00,
                        'commission_total' => 109.12,
                        'base_payout' => 219.12,
                        'adjustment' => 25.0,
                        'adjustment_note' => 'Weekend overtime support.',
                        'total_payout' => 244.12,
                    ],
                    [
                        'staff_id' => 'stf-shamiso',
                        'staff_name' => 'Shamiso Chuma',
                        'role_type' => 'therapist',
                        'salary_structure' => 'commission',
                        'commission_rate' => 30.0,
                        'fixed_pay' => 0.0,
                        'completed_count' => 6,
                        'commissionable_value' => 468.00,
                        'collected_value' => 468.00,
                        'commission_total' => 140.40,
                        'base_payout' => 140.40,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 140.40,
                    ],
                    [
                        'staff_id' => 'stf-kuda',
                        'staff_name' => 'Kuda Mlambo',
                        'role_type' => 'therapist',
                        'salary_structure' => 'commission',
                        'commission_rate' => 25.0,
                        'fixed_pay' => 0.0,
                        'completed_count' => 5,
                        'commissionable_value' => 328.00,
                        'collected_value' => 328.00,
                        'commission_total' => 82.0,
                        'base_payout' => 82.0,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 82.0,
                    ],
                ],
                'history' => [
                    ['label' => 'Payroll run created', 'meta' => 'Month-end run opened by Annie Admin.', 'tone' => 'info'],
                    ['label' => 'Run finalized', 'meta' => 'Approved for payout after review.', 'tone' => 'warning'],
                    ['label' => 'Run marked as paid', 'meta' => 'All staff payouts completed.', 'tone' => 'success'],
                ],
            ],
            'run-8002' => [
                'id' => 'run-8002',
                'reference' => 'PAYRUN-8002',
                'label' => 'May Mid-Cycle Draft',
                'period_start' => date('Y-m-01'),
                'period_end' => date('Y-m-d'),
                'status' => 'draft',
                'created_by' => 'Annie Admin',
                'created_at' => date('Y-m-d H:i:s', strtotime('today 08:00')),
                'finalized_at' => null,
                'paid_at' => null,
                'notes' => 'Working draft before the first weekly payout review.',
                'totals' => [
                    'staff_count' => 3,
                    'completed_bookings' => 3,
                    'commissionable_value' => 201.00,
                    'base_payout' => 111.26,
                    'adjustment_total' => 0.0,
                    'net_payout' => 111.26,
                ],
                'items' => [
                    [
                        'staff_id' => 'stf-tariro',
                        'staff_name' => 'Tariro Moyo',
                        'role_type' => 'therapist',
                        'salary_structure' => 'commission',
                        'commission_rate' => 28.0,
                        'fixed_pay' => 0.0,
                        'completed_count' => 0,
                        'commissionable_value' => 0.0,
                        'collected_value' => 0.0,
                        'commission_total' => 0.0,
                        'base_payout' => 0.0,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 0.0,
                    ],
                    [
                        'staff_id' => 'stf-amanda',
                        'staff_name' => 'Amanda Sibanda',
                        'role_type' => 'senior therapist',
                        'salary_structure' => 'hybrid',
                        'commission_rate' => 22.0,
                        'fixed_pay' => 110.0,
                        'completed_count' => 0,
                        'commissionable_value' => 0.0,
                        'collected_value' => 0.0,
                        'commission_total' => 0.0,
                        'base_payout' => 110.0,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 110.0,
                    ],
                    [
                        'staff_id' => 'stf-shamiso',
                        'staff_name' => 'Shamiso Chuma',
                        'role_type' => 'therapist',
                        'salary_structure' => 'commission',
                        'commission_rate' => 30.0,
                        'fixed_pay' => 0.0,
                        'completed_count' => 1,
                        'commissionable_value' => 78.0,
                        'collected_value' => 78.0,
                        'commission_total' => 23.4,
                        'base_payout' => 23.4,
                        'adjustment' => 0.0,
                        'adjustment_note' => '',
                        'total_payout' => 23.4,
                    ],
                ],
                'history' => [
                    ['label' => 'Payroll run created', 'meta' => 'Draft prepared for weekly review.', 'tone' => 'info'],
                ],
            ],
        ];
    }
}
