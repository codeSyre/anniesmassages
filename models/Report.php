<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Inventory.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/Payroll.php';
require_once __DIR__ . '/Staff.php';

final class Report
{
    public static function dashboardOverview(): array
    {
        $today = date('Y-m-d');
        $bookings = array_values(array_filter(Booking::all(), static fn (array $booking): bool => $booking['date'] === $today));
        usort($bookings, static fn (array $left, array $right): int => strcmp($left['sort_key'], $right['sort_key']));

        $reconciliation = Payment::reconciliation($today);
        $lowStockItems = Inventory::lowStockItems();
        $activeStaff = array_values(array_filter(Staff::all(), static fn (array $member): bool => $member['status'] === 'active'));
        $completedToday = array_values(array_filter($bookings, static fn (array $booking): bool => $booking['status'] === 'completed'));
        $pendingToday = array_values(array_filter($bookings, static fn (array $booking): bool => $booking['status'] === 'pending'));
        $scheduledValue = array_sum(array_map(static fn (array $booking): float => (float) $booking['amount_total'], $bookings));
        $capacity = array_sum(array_map(static fn (array $member): int => self::capacityFromLabel((string) ($member['capacity'] ?? '0')), $activeStaff));
        $openSlots = max($capacity - count($bookings), 0);
        $currentPayroll = Payroll::previewRun(Payroll::currentPeriod() + ['selected_staff' => []]);
        $paymentsToday = array_slice(Payment::all(['date_from' => $today, 'date_to' => $today]), 0, 2);

        $operations = array_map(static function (array $booking): array {
            return [
                'time' => $booking['time'],
                'title' => $booking['service']['name'] ?? 'Service booking',
                'customer' => $booking['customer']['name'] ?? 'Guest',
                'staff' => $booking['staff']['name'] ?? 'Unassigned',
                'status' => ucwords(str_replace('_', ' ', $booking['status'])),
                'tone' => match ($booking['status']) {
                    'completed', 'confirmed' => 'success',
                    'pending', 'rescheduled' => 'warning',
                    'cancelled', 'no_show' => 'danger',
                    default => 'info',
                },
            ];
        }, array_slice($bookings, 0, 5));

        $recentActivity = [];
        foreach ($paymentsToday as $payment) {
            $recentActivity[] = [
                'title' => 'Payment recorded',
                'description' => sprintf(
                    '%s settled %s for %s.',
                    $payment['customer']['name'] ?? 'A guest',
                    format_money((float) $payment['amount']),
                    $payment['service']['name'] ?? 'a service'
                ),
                'time' => date('j M Y', strtotime($payment['payment_date'])),
            ];
        }

        foreach (array_slice($lowStockItems, 0, 2) as $item) {
            $recentActivity[] = [
                'title' => 'Inventory alert',
                'description' => sprintf(
                    '%s is at %s %s against a reorder level of %s.',
                    $item['name'],
                    format_quantity((float) $item['on_hand']),
                    $item['unit'],
                    format_quantity((float) $item['reorder_level'])
                ),
                'time' => 'Needs attention',
            ];
        }

        if ($recentActivity === []) {
            $recentActivity[] = [
                'title' => 'System quiet',
                'description' => 'No new finance or stock changes have landed yet today.',
                'time' => 'Just now',
            ];
        }

        return [
            'headline' => [
                'eyebrow' => 'Today at a glance',
                'title' => 'Operational clarity for a calm day of service.',
                'description' => 'Bookings sit at the center of the business. This overview highlights what needs attention now across appointments, cash flow, stock, and therapist workload.',
            ],
            'metrics' => [
                [
                    'label' => "Today's bookings",
                    'value' => (string) count($bookings),
                    'change' => count($completedToday) . ' completed so far today',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Pending bookings',
                    'value' => (string) count($pendingToday),
                    'change' => count($pendingToday) > 0 ? 'Still need active follow-up today' : 'No pending bookings right now',
                    'tone' => count($pendingToday) > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Collected today',
                    'value' => format_money((float) $reconciliation['net_total']),
                    'change' => count($reconciliation['payments']) . ' ledger entries captured',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Low stock items',
                    'value' => (string) count($lowStockItems),
                    'change' => count($lowStockItems) > 0 ? 'Supplies worth review before close' : 'No stock pressure right now',
                    'tone' => count($lowStockItems) > 0 ? 'warning' : 'info',
                ],
            ],
            'operations' => $operations,
            'quickActions' => [
                ['label' => 'New Booking', 'href' => '/bookings/create.php', 'description' => 'Create and assign a new appointment.'],
                ['label' => 'Open Calendar', 'href' => '/scheduling/calendar.php', 'description' => 'Check staff availability and daily slots.'],
                ['label' => 'Record Payment', 'href' => '/payments/create.php', 'description' => 'Capture partial or full payment quickly.'],
                ['label' => 'Check Reports', 'href' => '/reports/dashboard.php', 'description' => 'Open the cross-module analytics workspace.'],
            ],
            'recentActivity' => array_slice($recentActivity, 0, 4),
            'focusPanels' => [
                [
                    'title' => 'Revenue pulse',
                    'value' => self::percent((float) $reconciliation['net_total'], $scheduledValue),
                    'description' => 'Of today’s scheduled value has already been collected.',
                ],
                [
                    'title' => 'Capacity',
                    'value' => (string) $openSlots . ' open slots',
                    'description' => 'Remaining room across active therapists based on roster capacity.',
                ],
                [
                    'title' => 'Payroll exposure',
                    'value' => format_money((float) $currentPayroll['totals']['net_payout']),
                    'description' => 'Projected payout for the current payroll period if it closed right now.',
                ],
            ],
        ];
    }

    public static function navigation(): array
    {
        return [
            ['label' => 'Overview', 'href' => '/reports/dashboard.php', 'route' => 'reports.dashboard'],
            ['label' => 'Revenue', 'href' => '/reports/revenue.php', 'route' => 'reports.revenue'],
            ['label' => 'Bookings', 'href' => '/reports/bookings.php', 'route' => 'reports.bookings'],
            ['label' => 'Staff', 'href' => '/reports/staff.php', 'route' => 'reports.staff'],
            ['label' => 'Inventory', 'href' => '/reports/inventory.php', 'route' => 'reports.inventory'],
            ['label' => 'Payroll', 'href' => '/reports/payroll.php', 'route' => 'reports.payroll'],
        ];
    }

    public static function defaultDateRange(): array
    {
        return [
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-d'),
        ];
    }

    public static function defaultPayrollPeriod(): array
    {
        return Payroll::currentPeriod();
    }

    public static function dashboard(array $filters): array
    {
        $filters = self::normalizeDateRange($filters);
        $revenue = self::revenue($filters);
        $bookings = self::bookings($filters);
        $staff = self::staff($filters);
        $inventory = self::inventory($filters);
        $payroll = self::payroll([
            'period_start' => $filters['date_from'],
            'period_end' => $filters['date_to'],
        ]);

        $dailyRows = [];
        $revenueByDate = [];
        foreach ($revenue['daily_rows'] as $row) {
            $revenueByDate[$row['date']] = $row;
        }

        foreach ($bookings['daily_rows'] as $row) {
            $revenueRow = $revenueByDate[$row['date']] ?? [
                'collected_value' => 0.0,
                'refund_value' => 0.0,
                'net_value' => 0.0,
            ];

            $dailyRows[] = [
                'date' => $row['date'],
                'label' => $row['label'],
                'bookings' => $row['booking_count'],
                'completed' => $row['completed_count'],
                'scheduled_value' => $row['scheduled_value'],
                'collected_value' => $revenueRow['collected_value'],
                'refund_value' => $revenueRow['refund_value'],
                'net_value' => $revenueRow['net_value'],
            ];
        }

        $alerts = [];
        foreach (array_slice($inventory['low_stock_rows'], 0, 3) as $item) {
            $alerts[] = [
                'title' => 'Low stock: ' . $item['name'],
                'description' => sprintf(
                    '%s %s on hand against a reorder level of %s.',
                    format_quantity((float) $item['on_hand']),
                    $item['unit'],
                    format_quantity((float) $item['reorder_level'])
                ),
            ];
        }

        foreach (array_slice($revenue['outstanding_rows'], 0, 2) as $booking) {
            $alerts[] = [
                'title' => 'Outstanding balance: ' . $booking['reference'],
                'description' => sprintf(
                    '%s still open for %s.',
                    format_money((float) $booking['balance']),
                    $booking['customer']['name'] ?? 'Guest'
                ),
            ];
        }

        if ($payroll['totals']['pending_run_count'] > 0) {
            $alerts[] = [
                'title' => 'Pending payroll runs',
                'description' => $payroll['totals']['pending_run_count'] . ' run(s) still need finalization or payout.',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'title' => 'No major alerts',
                'description' => 'Bookings, stock, and payroll are all within the expected operating band for this period.',
            ];
        }

        $topService = $bookings['service_rows'][0] ?? null;
        $topTherapist = $staff['performance_rows'][0] ?? null;

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Scheduled service value', 'value' => format_money($revenue['totals']['scheduled_value']), 'tone' => 'info'],
                ['label' => 'Net collected', 'value' => format_money($revenue['totals']['net_collected']), 'tone' => 'success'],
                ['label' => 'Completion rate', 'value' => self::percent((float) $bookings['totals']['completed_count'], (float) $bookings['totals']['total_bookings']), 'tone' => 'warning'],
                ['label' => 'Projected payroll', 'value' => format_money($payroll['totals']['projected_payout']), 'tone' => 'danger'],
            ],
            'spotlights' => [
                [
                    'title' => 'Revenue',
                    'value' => format_money($revenue['totals']['net_collected']),
                    'description' => self::percent($revenue['totals']['net_collected'], $revenue['totals']['scheduled_value']) . ' collection rate across the selected range.',
                    'href' => '/reports/revenue.php?date_from=' . $filters['date_from'] . '&date_to=' . $filters['date_to'],
                ],
                [
                    'title' => 'Bookings',
                    'value' => (string) $bookings['totals']['total_bookings'],
                    'description' => $bookings['totals']['completed_count'] . ' completed and ' . $bookings['totals']['attention_count'] . ' needing attention.',
                    'href' => '/reports/bookings.php?date_from=' . $filters['date_from'] . '&date_to=' . $filters['date_to'],
                ],
                [
                    'title' => 'Inventory',
                    'value' => (string) $inventory['totals']['low_stock_count'] . ' flagged',
                    'description' => format_money($inventory['totals']['replenishment_value']) . ' estimated replenishment value.',
                    'href' => '/reports/inventory.php?date_from=' . $filters['date_from'] . '&date_to=' . $filters['date_to'],
                ],
                [
                    'title' => 'Payroll',
                    'value' => format_money($payroll['totals']['projected_payout']),
                    'description' => $payroll['totals']['staff_count'] . ' staff in the current payout projection.',
                    'href' => '/reports/payroll.php?period_start=' . $filters['date_from'] . '&period_end=' . $filters['date_to'],
                ],
            ],
            'daily_rows' => $dailyRows,
            'alerts' => $alerts,
            'highlights' => [
                'top_service' => $topService,
                'top_therapist' => $topTherapist,
                'best_day' => self::highestValueRow($dailyRows, 'net_value'),
                'busiest_day' => self::highestValueRow($dailyRows, 'bookings'),
            ],
        ];
    }

    public static function revenue(array $filters): array
    {
        $filters = self::normalizeDateRange($filters);
        $payments = Payment::all($filters);
        $bookings = self::bookingsInRange($filters['date_from'], $filters['date_to']);
        $dates = self::dateSeries($filters['date_from'], $filters['date_to']);

        $scheduledValue = array_sum(array_map(static fn (array $booking): float => (float) $booking['amount_total'], $bookings));
        $outstandingValue = array_sum(array_map(static function (array $booking): float {
            if ($booking['payment_status'] === 'refunded') {
                return 0.0;
            }

            return (float) $booking['balance'];
        }, $bookings));

        $gross = 0.0;
        $refunds = 0.0;
        $methodRows = [];
        $serviceRows = [];
        $daily = [];

        foreach (Payment::methods() as $method) {
            $methodRows[$method] = [
                'method' => $method,
                'label' => ucwords(str_replace('_', ' ', $method)),
                'gross' => 0.0,
                'refunds' => 0.0,
                'net' => 0.0,
                'share' => '0%',
            ];
        }

        foreach ($dates as $date) {
            $daily[$date] = [
                'date' => $date,
                'label' => date('j M', strtotime($date)),
                'booking_count' => 0,
                'scheduled_value' => 0.0,
                'collected_value' => 0.0,
                'refund_value' => 0.0,
                'net_value' => 0.0,
            ];
        }

        foreach ($bookings as $booking) {
            $date = $booking['date'];
            $serviceName = $booking['service']['name'] ?? 'Service';

            $daily[$date]['booking_count']++;
            $daily[$date]['scheduled_value'] += (float) $booking['amount_total'];

            if (!isset($serviceRows[$serviceName])) {
                $serviceRows[$serviceName] = [
                    'service_name' => $serviceName,
                    'booking_count' => 0,
                    'scheduled_value' => 0.0,
                    'collected_value' => 0.0,
                ];
            }

            $serviceRows[$serviceName]['booking_count']++;
            $serviceRows[$serviceName]['scheduled_value'] += (float) $booking['amount_total'];
            $serviceRows[$serviceName]['collected_value'] += (float) $booking['amount_paid'];
        }

        foreach ($payments as $payment) {
            $method = (string) $payment['method'];
            $date = (string) $payment['payment_date'];
            $amount = (float) $payment['amount'];

            if ($payment['payment_status'] === 'refunded') {
                $refunds += $amount;
                $methodRows[$method]['refunds'] += $amount;
                if (isset($daily[$date])) {
                    $daily[$date]['refund_value'] += $amount;
                    $daily[$date]['net_value'] -= $amount;
                }
                continue;
            }

            $gross += $amount;
            $methodRows[$method]['gross'] += $amount;

            if (isset($daily[$date])) {
                $daily[$date]['collected_value'] += $amount;
                $daily[$date]['net_value'] += $amount;
            }
        }

        $net = $gross - $refunds;

        foreach ($methodRows as &$row) {
            $row['net'] = $row['gross'] - $row['refunds'];
            $row['share'] = self::percent($row['net'], $net);
        }
        unset($row);

        $methodRows = array_values($methodRows);
        usort($methodRows, static fn (array $left, array $right): int => $right['net'] <=> $left['net']);

        $serviceRows = array_values($serviceRows);
        usort($serviceRows, static fn (array $left, array $right): int => $right['scheduled_value'] <=> $left['scheduled_value']);

        $outstandingRows = array_values(array_filter($bookings, static function (array $booking): bool {
            return (float) $booking['balance'] > 0 && $booking['payment_status'] !== 'refunded';
        }));
        usort($outstandingRows, static fn (array $left, array $right): int => $right['balance'] <=> $left['balance']);

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Net collected', 'value' => format_money($net), 'tone' => 'success'],
                ['label' => 'Scheduled service value', 'value' => format_money($scheduledValue), 'tone' => 'info'],
                ['label' => 'Outstanding balance', 'value' => format_money($outstandingValue), 'tone' => 'warning'],
                ['label' => 'Refunds', 'value' => format_money($refunds), 'tone' => 'danger'],
            ],
            'totals' => [
                'gross_collected' => $gross,
                'refunds' => $refunds,
                'net_collected' => $net,
                'scheduled_value' => $scheduledValue,
                'outstanding_value' => $outstandingValue,
                'collection_rate' => $scheduledValue > 0 ? ($net / $scheduledValue) * 100 : 0.0,
            ],
            'daily_rows' => array_values($daily),
            'method_rows' => $methodRows,
            'service_rows' => $serviceRows,
            'outstanding_rows' => $outstandingRows,
        ];
    }

    public static function bookings(array $filters): array
    {
        $filters = self::normalizeDateRange($filters);
        $bookings = self::bookingsInRange($filters['date_from'], $filters['date_to']);
        $dates = self::dateSeries($filters['date_from'], $filters['date_to']);

        $statusRows = [];
        $channelRows = [];
        $serviceRows = [];
        $daily = [];
        $scheduledValue = 0.0;
        $completedCount = 0;
        $attentionCount = 0;
        $cancelledCount = 0;
        $durationTotal = 0;

        foreach ($dates as $date) {
            $daily[$date] = [
                'date' => $date,
                'label' => date('j M', strtotime($date)),
                'booking_count' => 0,
                'completed_count' => 0,
                'attention_count' => 0,
                'cancelled_count' => 0,
                'scheduled_value' => 0.0,
            ];
        }

        foreach ($bookings as $booking) {
            $status = (string) $booking['status'];
            $channel = (string) $booking['channel'];
            $serviceName = $booking['service']['name'] ?? 'Service';
            $value = (float) $booking['amount_total'];
            $date = $booking['date'];

            $scheduledValue += $value;
            $durationTotal += (int) $booking['duration'];
            $daily[$date]['booking_count']++;
            $daily[$date]['scheduled_value'] += $value;

            if (!isset($statusRows[$status])) {
                $statusRows[$status] = [
                    'status' => $status,
                    'label' => ucwords(str_replace('_', ' ', $status)),
                    'count' => 0,
                    'value' => 0.0,
                ];
            }

            if (!isset($channelRows[$channel])) {
                $channelRows[$channel] = [
                    'channel' => $channel,
                    'label' => ucwords(str_replace('_', ' ', $channel)),
                    'count' => 0,
                    'value' => 0.0,
                ];
            }

            if (!isset($serviceRows[$serviceName])) {
                $serviceRows[$serviceName] = [
                    'service_name' => $serviceName,
                    'booking_count' => 0,
                    'completed_count' => 0,
                    'scheduled_value' => 0.0,
                ];
            }

            $statusRows[$status]['count']++;
            $statusRows[$status]['value'] += $value;
            $channelRows[$channel]['count']++;
            $channelRows[$channel]['value'] += $value;
            $serviceRows[$serviceName]['booking_count']++;
            $serviceRows[$serviceName]['scheduled_value'] += $value;

            if ($status === 'completed') {
                $completedCount++;
                $daily[$date]['completed_count']++;
                $serviceRows[$serviceName]['completed_count']++;
            }

            if (in_array($status, ['pending', 'rescheduled'], true)) {
                $attentionCount++;
                $daily[$date]['attention_count']++;
            }

            if (in_array($status, ['cancelled', 'no_show'], true)) {
                $cancelledCount++;
                $daily[$date]['cancelled_count']++;
            }
        }

        $statusRows = array_values($statusRows);
        usort($statusRows, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        $channelRows = array_values($channelRows);
        usort($channelRows, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        $serviceRows = array_values($serviceRows);
        usort($serviceRows, static fn (array $left, array $right): int => $right['scheduled_value'] <=> $left['scheduled_value']);

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Bookings in range', 'value' => (string) count($bookings), 'tone' => 'info'],
                ['label' => 'Completed sessions', 'value' => (string) $completedCount, 'tone' => 'success'],
                ['label' => 'Attention required', 'value' => (string) $attentionCount, 'tone' => 'warning'],
                ['label' => 'Scheduled value', 'value' => format_money($scheduledValue), 'tone' => 'danger'],
            ],
            'totals' => [
                'total_bookings' => count($bookings),
                'completed_count' => $completedCount,
                'attention_count' => $attentionCount,
                'cancelled_count' => $cancelledCount,
                'scheduled_value' => $scheduledValue,
                'average_duration' => count($bookings) > 0 ? $durationTotal / count($bookings) : 0.0,
            ],
            'daily_rows' => array_values($daily),
            'status_rows' => $statusRows,
            'channel_rows' => $channelRows,
            'service_rows' => $serviceRows,
        ];
    }

    public static function staff(array $filters): array
    {
        $filters = self::normalizeDateRange($filters);
        $bookings = self::bookingsInRange($filters['date_from'], $filters['date_to']);
        $members = Staff::all();
        $rows = [];
        $structureRows = [];

        foreach ($members as $member) {
            $rows[$member['id']] = [
                'staff_id' => $member['id'],
                'staff_name' => $member['name'],
                'specialty' => $member['specialty'],
                'role_type' => $member['role_type'],
                'status' => $member['status'],
                'salary_structure' => $member['salary_structure'],
                'booking_count' => 0,
                'completed_count' => 0,
                'cancelled_count' => 0,
                'scheduled_value' => 0.0,
                'collected_value' => 0.0,
                'outstanding_value' => 0.0,
                'average_ticket' => 0.0,
                'service_mix' => [],
            ];
        }

        foreach ($bookings as $booking) {
            $staffId = $booking['staff']['id'] ?? '';
            if ($staffId === '' || !isset($rows[$staffId])) {
                continue;
            }

            $serviceName = $booking['service']['name'] ?? 'Service';
            $rows[$staffId]['booking_count']++;
            $rows[$staffId]['scheduled_value'] += (float) $booking['amount_total'];
            $rows[$staffId]['collected_value'] += (float) $booking['amount_paid'];

            if ($booking['payment_status'] !== 'refunded') {
                $rows[$staffId]['outstanding_value'] += (float) $booking['balance'];
            }

            $rows[$staffId]['service_mix'][$serviceName] = true;

            if ($booking['status'] === 'completed') {
                $rows[$staffId]['completed_count']++;
            }

            if (in_array($booking['status'], ['cancelled', 'no_show'], true)) {
                $rows[$staffId]['cancelled_count']++;
            }
        }

        $activeRoster = 0;
        $completedSessions = 0;
        $collectedValue = 0.0;
        $bookingCount = 0;

        foreach ($rows as &$row) {
            $row['average_ticket'] = $row['booking_count'] > 0 ? $row['scheduled_value'] / $row['booking_count'] : 0.0;
            $row['service_mix'] = array_values(array_keys($row['service_mix']));

            if ($row['status'] === 'active') {
                $activeRoster++;
            }

            $completedSessions += $row['completed_count'];
            $collectedValue += $row['collected_value'];
            $bookingCount += $row['booking_count'];

            $structure = $row['salary_structure'];
            if (!isset($structureRows[$structure])) {
                $structureRows[$structure] = [
                    'salary_structure' => $structure,
                    'label' => ucwords($structure),
                    'staff_count' => 0,
                    'completed_count' => 0,
                    'scheduled_value' => 0.0,
                    'collected_value' => 0.0,
                ];
            }

            $structureRows[$structure]['staff_count']++;
            $structureRows[$structure]['completed_count'] += $row['completed_count'];
            $structureRows[$structure]['scheduled_value'] += $row['scheduled_value'];
            $structureRows[$structure]['collected_value'] += $row['collected_value'];
        }
        unset($row);

        $performanceRows = array_values($rows);
        usort($performanceRows, static function (array $left, array $right): int {
            if ($left['collected_value'] === $right['collected_value']) {
                return $right['completed_count'] <=> $left['completed_count'];
            }

            return $right['collected_value'] <=> $left['collected_value'];
        });

        $structureRows = array_values($structureRows);
        usort($structureRows, static fn (array $left, array $right): int => $right['collected_value'] <=> $left['collected_value']);

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Active roster', 'value' => (string) $activeRoster, 'tone' => 'info'],
                ['label' => 'Completed sessions', 'value' => (string) $completedSessions, 'tone' => 'success'],
                ['label' => 'Collected value', 'value' => format_money($collectedValue), 'tone' => 'warning'],
                ['label' => 'Average booking value', 'value' => format_money($bookingCount > 0 ? $collectedValue / $bookingCount : 0.0), 'tone' => 'danger'],
            ],
            'totals' => [
                'active_roster' => $activeRoster,
                'completed_sessions' => $completedSessions,
                'collected_value' => $collectedValue,
                'average_booking_value' => $bookingCount > 0 ? $collectedValue / $bookingCount : 0.0,
            ],
            'performance_rows' => $performanceRows,
            'structure_rows' => $structureRows,
        ];
    }

    public static function inventory(array $filters): array
    {
        $filters = self::normalizeDateRange($filters);
        $items = Inventory::all();
        $movements = Inventory::movements([
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ]);
        $lowStockItems = Inventory::lowStockItems();
        $stockValue = array_sum(array_map(static fn (array $item): float => (float) $item['stock_value'], $items));
        $replenishmentValue = 0.0;
        $movementTypeRows = [];
        $categoryRows = [];
        $consumptionRows = [];

        foreach (Inventory::movementTypes() as $type) {
            $movementTypeRows[$type] = [
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $type)),
                'count' => 0,
                'quantity' => 0.0,
            ];
        }

        foreach ($items as $item) {
            $category = $item['category'];
            $gap = max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']);
            $replenishmentValue += $gap * (float) $item['cost_per_unit'];

            if (!isset($categoryRows[$category])) {
                $categoryRows[$category] = [
                    'category' => $category,
                    'item_count' => 0,
                    'low_stock_count' => 0,
                    'stock_value' => 0.0,
                ];
            }

            $categoryRows[$category]['item_count']++;
            $categoryRows[$category]['stock_value'] += (float) $item['stock_value'];

            if ($item['stock_status'] === 'low_stock' || $item['stock_status'] === 'out_of_stock') {
                $categoryRows[$category]['low_stock_count']++;
            }
        }

        foreach ($movements as $movement) {
            $type = $movement['type'];
            $quantity = (float) $movement['quantity'];
            $movementTypeRows[$type]['count']++;
            $movementTypeRows[$type]['quantity'] += $quantity;

            if (!in_array($type, ['stock_out', 'wastage', 'service_usage'], true)) {
                continue;
            }

            $itemId = $movement['item_id'];
            if (!isset($consumptionRows[$itemId])) {
                $consumptionRows[$itemId] = [
                    'item_id' => $itemId,
                    'item_name' => $movement['item_name'],
                    'sku' => $movement['sku'],
                    'quantity' => 0.0,
                    'events' => 0,
                    'latest_date' => $movement['movement_date'],
                ];
            }

            $consumptionRows[$itemId]['quantity'] += $quantity;
            $consumptionRows[$itemId]['events']++;
            if ($movement['movement_date'] > $consumptionRows[$itemId]['latest_date']) {
                $consumptionRows[$itemId]['latest_date'] = $movement['movement_date'];
            }
        }

        $movementTypeRows = array_values($movementTypeRows);
        usort($movementTypeRows, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        $categoryRows = array_values($categoryRows);
        usort($categoryRows, static fn (array $left, array $right): int => $right['stock_value'] <=> $left['stock_value']);

        $consumptionRows = array_values($consumptionRows);
        usort($consumptionRows, static fn (array $left, array $right): int => $right['quantity'] <=> $left['quantity']);

        $lowStockRows = array_map(static function (array $item): array {
            return $item + [
                'reorder_gap' => max(0.0, (float) $item['reorder_level'] - (float) $item['on_hand']),
            ];
        }, $lowStockItems);
        usort($lowStockRows, static fn (array $left, array $right): int => $right['reorder_gap'] <=> $left['reorder_gap']);

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Current stock value', 'value' => format_money($stockValue), 'tone' => 'success'],
                ['label' => 'Low stock items', 'value' => (string) count($lowStockItems), 'tone' => 'warning'],
                ['label' => 'Replenishment value', 'value' => format_money($replenishmentValue), 'tone' => 'danger'],
                ['label' => 'Movements in range', 'value' => (string) count($movements), 'tone' => 'info'],
            ],
            'totals' => [
                'stock_value' => $stockValue,
                'low_stock_count' => count($lowStockItems),
                'replenishment_value' => $replenishmentValue,
                'movement_count' => count($movements),
            ],
            'movement_type_rows' => $movementTypeRows,
            'category_rows' => $categoryRows,
            'consumption_rows' => $consumptionRows,
            'low_stock_rows' => $lowStockRows,
            'recent_movements' => array_slice($movements, 0, 8),
        ];
    }

    public static function payroll(array $filters): array
    {
        $filters = self::normalizePayrollPeriod($filters);
        $earningsRows = Payroll::earnings($filters);
        $preview = Payroll::previewRun($filters + ['selected_staff' => []]);
        $runRows = array_values(array_filter(Payroll::runs(), static function (array $run) use ($filters): bool {
            return !($run['period_end'] < $filters['period_start'] || $run['period_start'] > $filters['period_end']);
        }));

        usort($earningsRows, static fn (array $left, array $right): int => $right['total_payout'] <=> $left['total_payout']);

        $structureRows = [];
        foreach ($earningsRows as $row) {
            $structure = $row['salary_structure'];
            if (!isset($structureRows[$structure])) {
                $structureRows[$structure] = [
                    'salary_structure' => $structure,
                    'label' => ucwords($structure),
                    'staff_count' => 0,
                    'completed_count' => 0,
                    'commissionable_value' => 0.0,
                    'total_payout' => 0.0,
                ];
            }

            $structureRows[$structure]['staff_count']++;
            $structureRows[$structure]['completed_count'] += (int) $row['completed_count'];
            $structureRows[$structure]['commissionable_value'] += (float) $row['commissionable_value'];
            $structureRows[$structure]['total_payout'] += (float) $row['total_payout'];
        }

        $structureRows = array_values($structureRows);
        usort($structureRows, static fn (array $left, array $right): int => $right['total_payout'] <=> $left['total_payout']);

        $paidRunValue = array_sum(array_map(static function (array $run): float {
            return $run['status'] === 'paid' ? (float) $run['totals']['net_payout'] : 0.0;
        }, $runRows));
        $pendingRunCount = count(array_filter($runRows, static fn (array $run): bool => $run['status'] !== 'paid'));

        return [
            'filters' => $filters,
            'stats' => [
                ['label' => 'Projected payout', 'value' => format_money((float) $preview['totals']['net_payout']), 'tone' => 'warning'],
                ['label' => 'Staff in scope', 'value' => (string) $preview['totals']['staff_count'], 'tone' => 'info'],
                ['label' => 'Completed sessions', 'value' => (string) $preview['totals']['completed_bookings'], 'tone' => 'success'],
                ['label' => 'Paid run value', 'value' => format_money($paidRunValue), 'tone' => 'danger'],
            ],
            'totals' => [
                'projected_payout' => (float) $preview['totals']['net_payout'],
                'staff_count' => (int) $preview['totals']['staff_count'],
                'completed_bookings' => (int) $preview['totals']['completed_bookings'],
                'paid_run_value' => $paidRunValue,
                'pending_run_count' => $pendingRunCount,
            ],
            'earnings_rows' => $earningsRows,
            'structure_rows' => $structureRows,
            'run_rows' => array_slice($runRows, 0, 6),
        ];
    }

    private static function normalizeDateRange(array $filters, ?string $defaultFrom = null, ?string $defaultTo = null): array
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ($defaultFrom ?? date('Y-m-01'))));
        $dateTo = trim((string) ($filters['date_to'] ?? ($defaultTo ?? date('Y-m-d'))));

        if ($dateFrom === '') {
            $dateFrom = $defaultFrom ?? date('Y-m-01');
        }

        if ($dateTo === '') {
            $dateTo = $defaultTo ?? date('Y-m-d');
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private static function normalizePayrollPeriod(array $filters): array
    {
        $defaults = self::defaultPayrollPeriod();
        $periodStart = trim((string) ($filters['period_start'] ?? $defaults['period_start']));
        $periodEnd = trim((string) ($filters['period_end'] ?? $defaults['period_end']));

        if ($periodStart === '') {
            $periodStart = $defaults['period_start'];
        }

        if ($periodEnd === '') {
            $periodEnd = $defaults['period_end'];
        }

        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    private static function bookingsInRange(string $dateFrom, string $dateTo): array
    {
        return array_values(array_filter(Booking::all(), static function (array $booking) use ($dateFrom, $dateTo): bool {
            return $booking['date'] >= $dateFrom && $booking['date'] <= $dateTo;
        }));
    }

    private static function dateSeries(string $dateFrom, string $dateTo): array
    {
        $dates = [];
        $cursor = new DateTimeImmutable($dateFrom);
        $end = new DateTimeImmutable($dateTo);

        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    private static function percent(float $numerator, float $denominator): string
    {
        if ($denominator <= 0.0) {
            return '0%';
        }

        return number_format(($numerator / $denominator) * 100, 0) . '%';
    }

    private static function highestValueRow(array $rows, string $key): ?array
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static fn (array $left, array $right): int => ($right[$key] ?? 0) <=> ($left[$key] ?? 0));

        return $rows[0] ?? null;
    }

    private static function capacityFromLabel(string $capacity): int
    {
        if (preg_match('/(\d+)/', $capacity, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
