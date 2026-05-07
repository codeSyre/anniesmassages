<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$filters = [
    'date_from' => (string) ($_GET['date_from'] ?? Report::defaultDateRange()['date_from']),
    'date_to' => (string) ($_GET['date_to'] ?? Report::defaultDateRange()['date_to']),
];

$report = Report::revenue($filters);

$pageTitle = 'Revenue Report';
$pageEyebrow = 'Payments, balances, and collection health';
$currentRoute = 'reports';
$reportRoute = 'reports.revenue';
$topbarAction = ['label' => 'Payments ledger', 'href' => '/payments/ledger.php', 'permission' => 'payments.view'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Revenue analytics</p>
                <h1 class="hero-title">Understand what was sold, what was collected, and what is still open.</h1>
                <p class="hero-copy">This report keeps booked service value, actual cash collection, method mix, and outstanding balances in one place so finance decisions stay grounded in the ledger.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payments/ledger.php">Open ledger</a>
                    <a class="action-link is-secondary" href="/payments/reconciliation.php?date=<?= e(date('Y-m-d')) ?>">Daily reconciliation</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <?php foreach ($report['stats'] as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/reports-nav.php'; ?>

        <section class="filter-panel section-spaced">
            <form class="report-filter-grid" method="get">
                <label>
                    Date from
                    <input type="date" name="date_from" value="<?= e($report['filters']['date_from']) ?>">
                </label>
                <label>
                    Date to
                    <input type="date" name="date_to" value="<?= e($report['filters']['date_to']) ?>">
                </label>
                <div class="report-filter-actions">
                    <button class="topbar-cta" type="submit">Refresh report</button>
                    <a class="action-link is-secondary" href="/reports/revenue.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Payment channels</p>
                        <h3>Collected by method</h3>
                    </div>
                    <p class="report-table-note">Net contribution by payment method across the selected range.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['method_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span>Gross <?= e(format_money((float) $row['gross'])) ?> · Refunds <?= e(format_money((float) $row['refunds'])) ?></span>
                            </div>
                            <em><?= e(format_money((float) $row['net'])) ?> · <?= e($row['share']) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Service yield</p>
                        <h3>Best earning services</h3>
                    </div>
                    <p class="report-table-note">Booked value compared with what has already been collected.</p>
                </div>

                <div class="report-list">
                    <?php foreach (array_slice($report['service_rows'], 0, 6) as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['service_name']) ?></strong>
                                <span><?= e((string) $row['booking_count']) ?> bookings</span>
                            </div>
                            <em><?= e(format_money((float) $row['scheduled_value'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Daily trend</p>
                    <h3>Revenue across the selected range</h3>
                </div>
                <p class="report-table-note">Net collected is payments minus refunds for each day.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bookings</th>
                        <th>Scheduled</th>
                        <th>Collected</th>
                        <th>Refunds</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['daily_rows'] as $row): ?>
                        <tr>
                            <td><strong><?= e($row['label']) ?></strong></td>
                            <td><?= e((string) $row['booking_count']) ?></td>
                            <td><?= e(format_money((float) $row['scheduled_value'])) ?></td>
                            <td><?= e(format_money((float) $row['collected_value'])) ?></td>
                            <td><?= e(format_money((float) $row['refund_value'])) ?></td>
                            <td><?= e(format_money((float) $row['net_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Outstanding balances</p>
                    <h3>Bookings still carrying open value</h3>
                </div>
                <p class="report-table-note">These rows are useful for follow-up and payment reminders.</p>
            </div>

            <?php if ($report['outstanding_rows'] === []): ?>
                <p class="report-empty">No outstanding balances were found in the selected date range.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Guest</th>
                            <th>Service</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['outstanding_rows'] as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= e($booking['reference']) ?></strong>
                                    <span><?= e(date('j M Y', strtotime($booking['date']))) ?></span>
                                </td>
                                <td><?= e($booking['customer']['name'] ?? 'Guest') ?></td>
                                <td><?= e($booking['service']['name'] ?? 'Service') ?></td>
                                <td><?= e(format_money((float) $booking['amount_total'])) ?></td>
                                <td><?= e(format_money((float) $booking['amount_paid'])) ?></td>
                                <td><?= e(format_money((float) $booking['balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
