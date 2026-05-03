<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$filters = [
    'date_from' => (string) ($_GET['date_from'] ?? Report::defaultDateRange()['date_from']),
    'date_to' => (string) ($_GET['date_to'] ?? Report::defaultDateRange()['date_to']),
];

$report = Report::dashboard($filters);

$pageTitle = 'Reports & Analytics';
$pageEyebrow = 'Cross-module performance view';
$currentRoute = 'reports';
$reportRoute = 'reports.dashboard';
$topbarAction = ['label' => 'Revenue report', 'href' => '/reports/revenue.php?date_from=' . $report['filters']['date_from'] . '&date_to=' . $report['filters']['date_to']];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Reports overview</p>
                <h1 class="hero-title">See revenue, bookings, stock, staff, and payroll as one operating picture.</h1>
                <p class="hero-copy">This view rolls the key admin modules into one reporting layer so you can spot cash pressure, demand shifts, therapist performance, and supply risk without jumping between pages.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/reports/revenue.php?date_from=<?= e($report['filters']['date_from']) ?>&date_to=<?= e($report['filters']['date_to']) ?>">Open revenue</a>
                    <a class="action-link is-secondary" href="/reports/payroll.php?period_start=<?= e($report['filters']['date_from']) ?>&period_end=<?= e($report['filters']['date_to']) ?>">Open payroll</a>
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
                    <a class="action-link is-secondary" href="/reports/dashboard.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Daily operating pulse</p>
                        <h3>Bookings and cash by day</h3>
                    </div>
                    <p class="report-table-note">A combined operating line for demand and collections across the selected range.</p>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bookings</th>
                            <th>Completed</th>
                            <th>Scheduled</th>
                            <th>Net collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['daily_rows'] as $row): ?>
                            <tr>
                                <td><strong><?= e($row['label']) ?></strong></td>
                                <td><?= e((string) $row['bookings']) ?></td>
                                <td><?= e((string) $row['completed']) ?></td>
                                <td><?= e(format_money((float) $row['scheduled_value'])) ?></td>
                                <td><?= e(format_money((float) $row['net_value'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Watch list</p>
                        <h3>Alerts worth attention</h3>
                    </div>
                    <p class="report-table-note">Signals pulled from inventory, revenue, and payroll.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['alerts'] as $alert): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($alert['title']) ?></strong>
                                <span><?= e($alert['description']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Module spotlights</p>
                        <h3>Jump into the next question</h3>
                    </div>
                    <p class="report-table-note">Each spotlight opens the deeper report for that operating area.</p>
                </div>

                <div class="report-mini-grid">
                    <?php foreach ($report['spotlights'] as $spotlight): ?>
                        <a class="report-card" href="<?= e($spotlight['href']) ?>">
                            <span class="badge badge-info"><?= e($spotlight['title']) ?></span>
                            <strong><?= e($spotlight['value']) ?></strong>
                            <p><?= e($spotlight['description']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Highlights</p>
                        <h3>Best and busiest moments</h3>
                    </div>
                    <p class="report-table-note">Quick leadership talking points from the selected window.</p>
                </div>

                <div class="report-pairs">
                    <div class="report-pair">
                        <strong>Top service</strong>
                        <span><?= e($report['highlights']['top_service']['service_name'] ?? 'No data yet') ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Top therapist</strong>
                        <span><?= e($report['highlights']['top_therapist']['staff_name'] ?? 'No data yet') ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Best net day</strong>
                        <span><?= e($report['highlights']['best_day']['label'] ?? 'No data yet') ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Busiest day</strong>
                        <span><?= e($report['highlights']['busiest_day']['label'] ?? 'No data yet') ?></span>
                    </div>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
