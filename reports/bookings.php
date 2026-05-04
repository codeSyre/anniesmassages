<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$filters = [
    'date_from' => (string) ($_GET['date_from'] ?? Report::defaultDateRange()['date_from']),
    'date_to' => (string) ($_GET['date_to'] ?? Report::defaultDateRange()['date_to']),
];

$report = Report::bookings($filters);

$pageTitle = 'Bookings Report';
$pageEyebrow = 'Demand, status mix, and service traction';
$currentRoute = 'reports';
$reportRoute = 'reports.bookings';
$topbarAction = ['label' => 'Booking list', 'href' => '/bookings/list.php', 'permission' => 'bookings.view'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Booking analytics</p>
                <h1 class="hero-title">See demand patterns, confirmation pressure, and service momentum.</h1>
                <p class="hero-copy">This report breaks bookings into daily volume, operational status, booking channels, and service demand so you can see where the front desk and schedule are tightening up.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/bookings/list.php">Open bookings</a>
                    <a class="action-link is-secondary" href="/scheduling/calendar.php">Open calendar</a>
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
                    <a class="action-link is-secondary" href="/reports/bookings.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Status mix</p>
                        <h3>How bookings moved</h3>
                    </div>
                    <p class="report-table-note">Count and scheduled value by operational status.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['status_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span><?= e((string) $row['count']) ?> bookings</span>
                            </div>
                            <em><?= e(format_money((float) $row['value'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Channel mix</p>
                        <h3>Where bookings came from</h3>
                    </div>
                    <p class="report-table-note">Use this to spot how demand is arriving at the desk.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['channel_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span><?= e((string) $row['count']) ?> bookings</span>
                            </div>
                            <em><?= e(format_money((float) $row['value'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Daily trend</p>
                    <h3>Booking load by day</h3>
                </div>
                <p class="report-table-note">Attention count combines pending and rescheduled bookings.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Completed</th>
                        <th>Attention</th>
                        <th>Cancelled</th>
                        <th>Scheduled value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['daily_rows'] as $row): ?>
                        <tr>
                            <td><strong><?= e($row['label']) ?></strong></td>
                            <td><?= e((string) $row['booking_count']) ?></td>
                            <td><?= e((string) $row['completed_count']) ?></td>
                            <td><?= e((string) $row['attention_count']) ?></td>
                            <td><?= e((string) $row['cancelled_count']) ?></td>
                            <td><?= e(format_money((float) $row['scheduled_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Service demand</p>
                    <h3>Which treatments are pulling volume</h3>
                </div>
                <p class="report-table-note">A practical view for service planning and marketing follow-up.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Bookings</th>
                        <th>Completed</th>
                        <th>Scheduled value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['service_rows'] as $row): ?>
                        <tr>
                            <td><strong><?= e($row['service_name']) ?></strong></td>
                            <td><?= e((string) $row['booking_count']) ?></td>
                            <td><?= e((string) $row['completed_count']) ?></td>
                            <td><?= e(format_money((float) $row['scheduled_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
