<?php declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/models/Report.php';

$currentUser = require_login();
require_permission('dashboard.view');

$selectedRevenueMonth = trim((string) ($_GET['revenue_month'] ?? ''));
$pageTitle = 'Dashboard Overview';
$pageEyebrow = 'Operations command center';
$currentRoute = 'dashboard';
$dashboard = Report::dashboardOverview($selectedRevenueMonth);
$hasBookings = (bool) ($dashboard['hasBookings'] ?? false);
$incomeTrend = $dashboard['incomeTrend'] ?? [
    'selected_month' => date('Y-m'),
    'selected_label' => date('F Y'),
    'available_months' => [],
    'daily_rows' => [],
    'totals' => [
        'net_collected' => 0.0,
        'gross_collected' => 0.0,
        'refunds' => 0.0,
        'average_daily_net' => 0.0,
        'peak_daily_net' => 0.0,
        'active_days' => 0,
    ],
    'best_day' => null,
];
$incomeRows = is_array($incomeTrend['daily_rows'] ?? null) ? $incomeTrend['daily_rows'] : [];
$incomeChartHeight = 280;
$incomeChartWidth = 960;
$incomeChartPaddingX = 18.0;
$incomeChartPaddingY = 20.0;
$incomeMaxNet = max(1.0, ...array_map(static fn (array $row): float => (float) ($row['net_value'] ?? 0.0), $incomeRows !== [] ? $incomeRows : [['net_value' => 0.0]]));
$incomeAxisPeakDisplay = (float) ($incomeTrend['totals']['peak_daily_net'] ?? 0.0);
$incomeAxisMidDisplay = $incomeAxisPeakDisplay / 2;
$incomePointCount = count($incomeRows);
$incomeChartPoints = [];
$incomeLinePath = '';
$incomeAreaPath = '';
$incomeInteractivePayload = '[]';

foreach ($incomeRows as $index => $row) {
    $x = $incomePointCount <= 1
        ? $incomeChartWidth / 2
        : $incomeChartPaddingX + (($incomeChartWidth - ($incomeChartPaddingX * 2)) * $index / max($incomePointCount - 1, 1));
    $yRatio = min(max(((float) ($row['net_value'] ?? 0.0)) / $incomeMaxNet, 0.0), 1.0);
    $y = ($incomeChartHeight - $incomeChartPaddingY) - (($incomeChartHeight - ($incomeChartPaddingY * 2)) * $yRatio);

    $incomeChartPoints[] = [
        'x' => $x,
        'y' => $y,
    ];
}

if ($incomeChartPoints !== []) {
    $incomeLineSegments = ['M ' . number_format((float) $incomeChartPoints[0]['x'], 2, '.', '') . ' ' . number_format((float) $incomeChartPoints[0]['y'], 2, '.', '')];

    if (count($incomeChartPoints) === 1) {
        $incomeLineSegments[] = 'L ' . number_format((float) $incomeChartPoints[0]['x'], 2, '.', '') . ' ' . number_format((float) $incomeChartPoints[0]['y'], 2, '.', '');
    } elseif (count($incomeChartPoints) === 2) {
        $incomeLineSegments[] = 'L ' . number_format((float) $incomeChartPoints[1]['x'], 2, '.', '') . ' ' . number_format((float) $incomeChartPoints[1]['y'], 2, '.', '');
    } else {
        for ($index = 1; $index < count($incomeChartPoints) - 1; $index++) {
            $controlX = (float) $incomeChartPoints[$index]['x'];
            $controlY = (float) $incomeChartPoints[$index]['y'];
            $nextX = (((float) $incomeChartPoints[$index]['x']) + ((float) $incomeChartPoints[$index + 1]['x'])) / 2;
            $nextY = (((float) $incomeChartPoints[$index]['y']) + ((float) $incomeChartPoints[$index + 1]['y'])) / 2;
            $incomeLineSegments[] = 'Q '
                . number_format($controlX, 2, '.', '') . ' '
                . number_format($controlY, 2, '.', '') . ' '
                . number_format($nextX, 2, '.', '') . ' '
                . number_format($nextY, 2, '.', '');
        }

        $lastPoint = $incomeChartPoints[count($incomeChartPoints) - 1];
        $incomeLineSegments[] = 'Q '
            . number_format((float) $lastPoint['x'], 2, '.', '') . ' '
            . number_format((float) $lastPoint['y'], 2, '.', '') . ' '
            . number_format((float) $lastPoint['x'], 2, '.', '') . ' '
            . number_format((float) $lastPoint['y'], 2, '.', '');
    }

    $incomeLinePath = implode(' ', $incomeLineSegments);
    $bottomY = number_format($incomeChartHeight - $incomeChartPaddingY, 2, '.', '');
    $firstX = number_format((float) $incomeChartPoints[0]['x'], 2, '.', '');
    $lastX = number_format((float) $incomeChartPoints[count($incomeChartPoints) - 1]['x'], 2, '.', '');
    $incomeAreaPath = $incomeLinePath . ' L ' . $lastX . ' ' . $bottomY . ' L ' . $firstX . ' ' . $bottomY . ' Z';
    $incomeInteractivePayload = json_encode(array_map(
        static function (array $point, array $row): array {
            return [
                'x' => round((float) $point['x'], 2),
                'y' => round((float) $point['y'], 2),
                'label' => (string) ($row['label'] ?? ''),
                'value' => format_money((float) ($row['net_value'] ?? 0.0)),
            ];
        },
        $incomeChartPoints,
        $incomeRows
    ), JSON_THROW_ON_ERROR);
}

if ($hasBookings) {
    $topbarActions = [
        ['label' => 'Create booking', 'href' => '/bookings/create.php', 'permission' => 'bookings.create'],
        ['label' => 'Review ledger', 'href' => '/payments/ledger.php', 'permission' => 'payments.view'],
    ];
}

require __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="hero<?= $hasBookings ? ' hero-compact' : '' ?>">
            <?php if (!$hasBookings): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow"><?= e($dashboard['headline']['eyebrow']) ?></p>
                    <h1 class="hero-title"><?= e($dashboard['headline']['title']) ?></h1>
                    <p class="hero-copy"><?= e($dashboard['headline']['description']) ?></p>

                    <div class="hero-actions">
                        <a class="action-link" href="/bookings/create.php">Create booking</a>
                        <a class="action-link is-secondary" href="/payments/ledger.php">Review ledger</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="focus-stack<?= $hasBookings ? ' focus-stack-grid' : '' ?>" aria-label="Operational focus panels">
                <?php foreach ($dashboard['focusPanels'] as $panel): ?>
                    <section class="focus-card">
                        <p class="card-label"><?= e($panel['title']) ?></p>
                        <strong><?= e($panel['value']) ?></strong>
                        <p><?= e($panel['description']) ?></p>
                    </section>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="table-card section-spaced income-trend-card">
            <div class="section-head income-trend-head">
                <div>
                    <p class="section-kicker">Revenue trend</p>
                    <h3>Daily income generation</h3>
                    <p>Daily net revenue from payments in <?= e((string) ($incomeTrend['selected_label'] ?? date('F Y'))) ?>, sourced from the ledger.</p>
                </div>

                <form class="inline-search income-trend-filter" method="get" data-income-trend-filter>
                    <label class="income-trend-filter-field">
                        <span>Month</span>
                        <input
                            type="search"
                            name="revenue_month"
                            list="dashboard-revenue-months"
                            value="<?= e((string) ($incomeTrend['selected_month'] ?? '')) ?>"
                            placeholder="Search month"
                            aria-label="Choose revenue month"
                            data-income-trend-month
                        >
                        <datalist id="dashboard-revenue-months">
                            <?php foreach (($incomeTrend['available_months'] ?? []) as $monthOption): ?>
                                <option value="<?= e((string) ($monthOption['value'] ?? '')) ?>" label="<?= e((string) ($monthOption['label'] ?? '')) ?>"><?= e((string) ($monthOption['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>
                    <button type="submit">Show month</button>
                </form>
            </div>

            <div class="income-trend-grid">
            <div class="income-trend-summary">
                    <article class="mini-stat-card">
                        <span class="badge badge-success">Net collected</span>
                        <strong><?= e(format_money((float) ($incomeTrend['totals']['net_collected'] ?? 0.0))) ?></strong>
                        <p><?= e((string) ($incomeTrend['selected_label'] ?? date('F Y'))) ?> total after refunds.</p>
                    </article>
                    <article class="mini-stat-card">
                        <span class="badge badge-info">Average day</span>
                        <strong><?= e(format_money((float) ($incomeTrend['totals']['average_daily_net'] ?? 0.0))) ?></strong>
                        <p><?= e((string) ($incomeTrend['totals']['active_days'] ?? 0)) ?> active collection day(s) in the month.</p>
                    </article>
                    <article class="mini-stat-card">
                        <span class="badge badge-warning">Best day</span>
                        <strong><?= e(format_money((float) ($incomeTrend['totals']['peak_daily_net'] ?? 0.0))) ?></strong>
                        <p>
                            <?= e((string) (($incomeTrend['best_day']['label'] ?? null) !== null ? $incomeTrend['best_day']['label'] : 'No revenue spikes yet')) ?>
                        </p>
                    </article>
                    </div>
                <div class="income-trend-visual">
                    <div
                        class="income-trend-chart-shell"
                        data-income-trend-chart
                        data-income-trend-points="<?= e($incomeInteractivePayload) ?>"
                        data-income-trend-chart-height="<?= e((string) $incomeChartHeight) ?>"
                        data-income-trend-chart-top="<?= e((string) $incomeChartPaddingY) ?>"
                        data-income-trend-chart-bottom="<?= e((string) ($incomeChartHeight - $incomeChartPaddingY)) ?>"
                    >
                        <div class="income-trend-axis income-trend-axis-top"><?= e(format_money($incomeAxisPeakDisplay)) ?></div>
                        <div class="income-trend-axis income-trend-axis-middle"><?= e(format_money($incomeAxisMidDisplay)) ?></div>
                        <div class="income-trend-axis income-trend-axis-bottom">$0.00</div>

                        <svg class="income-trend-chart" viewBox="0 0 <?= e((string) $incomeChartWidth) ?> <?= e((string) $incomeChartHeight) ?>" role="img" aria-label="Daily income generation line graph for <?= e((string) ($incomeTrend['selected_label'] ?? date('F Y'))) ?>">
                            <line x1="<?= e((string) $incomeChartPaddingX) ?>" y1="<?= e((string) $incomeChartPaddingY) ?>" x2="<?= e((string) ($incomeChartWidth - $incomeChartPaddingX)) ?>" y2="<?= e((string) $incomeChartPaddingY) ?>" class="income-trend-grid-line" />
                            <line x1="<?= e((string) $incomeChartPaddingX) ?>" y1="<?= e((string) ($incomeChartHeight / 2)) ?>" x2="<?= e((string) ($incomeChartWidth - $incomeChartPaddingX)) ?>" y2="<?= e((string) ($incomeChartHeight / 2)) ?>" class="income-trend-grid-line income-trend-grid-line-mid" />
                            <line x1="<?= e((string) $incomeChartPaddingX) ?>" y1="<?= e((string) ($incomeChartHeight - $incomeChartPaddingY)) ?>" x2="<?= e((string) ($incomeChartWidth - $incomeChartPaddingX)) ?>" y2="<?= e((string) ($incomeChartHeight - $incomeChartPaddingY)) ?>" class="income-trend-grid-line" />
                            <line x1="<?= e((string) $incomeChartPaddingX) ?>" y1="<?= e((string) $incomeChartPaddingY) ?>" x2="<?= e((string) $incomeChartPaddingX) ?>" y2="<?= e((string) ($incomeChartHeight - $incomeChartPaddingY)) ?>" class="income-trend-hover-guide" data-income-trend-hover-guide />

                            <?php if ($incomeAreaPath !== '' && $incomeLinePath !== ''): ?>
                                <path d="<?= e($incomeAreaPath) ?>" class="income-trend-area" />
                                <path d="<?= e($incomeLinePath) ?>" class="income-trend-line" />
                            <?php endif; ?>
                        </svg>
                        <div class="income-trend-tooltip" data-income-trend-tooltip hidden>
                            <strong data-income-trend-tooltip-value></strong>
                            <span data-income-trend-tooltip-label></span>
                        </div>
                    </div>

                    <div class="income-trend-xaxis" style="--income-day-count: <?= e((string) max(count($incomeRows), 1)) ?>;" aria-hidden="true">
                        <?php foreach ($incomeRows as $row): ?>
                            <span><?= e((string) ($row['day'] ?? '')) ?></span>
                        <?php endforeach; ?>
                    </div>

                    
                </div>
            </div>
        </section>

        <section class="metrics-grid" aria-label="Dashboard summary metrics">
            <?php foreach ($dashboard['metrics'] as $metric): ?>
                <article class="card">
                    <span class="<?= e(badge_class($metric['tone'])) ?>"><?= e($metric['label']) ?></span>
                    <h3 class="card-value"><?= e($metric['value']) ?></h3>
                    <p class="card-note"><?= e($metric['change']) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Today’s operations</p>
                        <h3>Booking flow</h3>
                    <p>Pending appointments and service handoffs scheduled for today.</p>
                    </div>
                    <a class="topbar-link" href="/bookings/list.php">All bookings</a>
                </div>

                <?php if (($dashboard['operations'] ?? []) === []): ?>
                    <div class="empty-state">
                        <strong>No pending bookings scheduled for today.</strong>
                        <p>New bookings or status changes will appear here once they need follow-up today.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Assignment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard['operations'] as $operation): ?>
                                <tr
                                    class="table-row-link"
                                    data-row-link
                                    data-row-link-href="<?= e((string) ($operation['href'] ?? '#')) ?>"
                                    tabindex="0"
                                    role="link"
                                    aria-label="Open booking <?= e((string) ($operation['reference'] ?? $operation['title'])) ?>"
                                >
                                    <td><strong><?= e($operation['time']) ?></strong></td>
                                    <td>
                                        <strong><?= e($operation['title']) ?></strong>
                                        <span><?= e($operation['customer']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= e($operation['staff']) ?></strong>
                                        <span>Assigned therapist</span>
                                    </td>
                                    <td><span class="<?= e(badge_class($operation['tone'])) ?>"><?= e($operation['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </article>

            <aside class="activity-column">
                <section class="activity-card">
                    <div class="section-head">
                        <div>
                            <p class="section-kicker">Quick actions</p>
                            <h3>Move fast</h3>
                        <p>One-click jumps into the busiest daily workflows.</p>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <?php foreach ($dashboard['quickActions'] as $action): ?>
                            <a class="action-card" href="<?= e($action['href']) ?>">
                                <strong><?= e($action['label']) ?></strong>
                                <p><?= e($action['description']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </aside>
        </section>

        <section class="table-card activity-card-standalone">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Inventory attention</p>
                        <h3>Stock items to watch</h3>
                    <p>Low-stock items appear first, with near-threshold supplies following behind them.</p>
                    </div>
                    <a class="topbar-link" href="/inventory/low-stock.php">Low-stock report</a>
                </div>

            <?php if (($dashboard['inventoryAttention'] ?? []) === []): ?>
                <div class="empty-state">
                    <strong>No inventory items need attention right now.</strong>
                    <p>Tracked supplies will surface here as soon as they approach or fall below their reorder point.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>On hand</th>
                            <th>Reorder point</th>
                            <th>Service impact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['inventoryAttention'] as $item): ?>
                            <tr
                                class="table-row-link"
                                data-row-link
                                data-row-link-href="<?= e((string) ($item['href'] ?? '#')) ?>"
                                tabindex="0"
                                role="link"
                                aria-label="Open inventory item <?= e((string) ($item['name'] ?? '')) ?>"
                            >
                                <td>
                                    <strong><?= e((string) ($item['name'] ?? 'Inventory item')) ?></strong>
                                    <span><?= e((string) ($item['category'] ?? 'Uncategorized')) ?> · <?= e((string) ($item['location'] ?? 'No location recorded')) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) ($item['on_hand'] ?? 0.0))) ?> <?= e((string) ($item['unit'] ?? 'units')) ?></strong>
                                    <span><?= e((string) (($item['supplier'] ?? '') !== '' ? $item['supplier'] : 'No supplier recorded')) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) ($item['reorder_level'] ?? 0.0))) ?> <?= e((string) ($item['unit'] ?? 'units')) ?></strong>
                                    <span><?= e(format_money((float) ($item['top_up_cost'] ?? 0.0))) ?> estimated top-up</span>
                                </td>
                                <td>
                                    <strong><?= e((string) (($item['linked_services'] ?? []) !== [] ? implode(' · ', (array) $item['linked_services']) : 'No linked services')) ?></strong>
                                    <span><?= e((string) ($item['attention_note'] ?? 'Needs review')) ?></span>
                                </td>
                                <td><span class="<?= e(badge_class((string) ($item['tone'] ?? 'info'))) ?>"><?= e((string) ($item['status'] ?? 'Info')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
