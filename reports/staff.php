<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$filters = [
    'date_from' => (string) ($_GET['date_from'] ?? Report::defaultDateRange()['date_from']),
    'date_to' => (string) ($_GET['date_to'] ?? Report::defaultDateRange()['date_to']),
];

$report = Report::staff($filters);
$topPerformer = $report['performance_rows'][0] ?? null;

$pageTitle = 'Staff Performance Report';
$pageEyebrow = 'Therapist contribution and earnings context';
$currentRoute = 'reports';
$reportRoute = 'reports.staff';
$topbarAction = ['label' => 'Staff roster', 'href' => '/staff/list.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Staff analytics</p>
                <h1 class="hero-title">Compare therapist output, collections, and service mix over time.</h1>
                <p class="hero-copy">This report ties booking activity back to each therapist so you can review operating contribution before you step into scheduling, coaching, or payroll decisions.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/staff/list.php">Open staff</a>
                    <a class="action-link is-secondary" href="/payroll/earnings.php">Open payroll earnings</a>
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
                    <a class="action-link is-secondary" href="/reports/staff.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Compensation mix</p>
                        <h3>Performance by salary structure</h3>
                    </div>
                    <p class="report-table-note">Useful context before payout reviews or staffing changes.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['structure_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span><?= e((string) $row['staff_count']) ?> staff · <?= e((string) $row['completed_count']) ?> completed sessions</span>
                            </div>
                            <em><?= e(format_money((float) $row['collected_value'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Top performer</p>
                        <h3>Current leader in the range</h3>
                    </div>
                    <p class="report-table-note">Highest collected value in the selected period.</p>
                </div>

                <?php if ($topPerformer === null): ?>
                    <p class="report-empty">No therapist performance data is available for this range yet.</p>
                <?php else: ?>
                    <div class="report-callout">
                        <h4><?= e($topPerformer['staff_name']) ?></h4>
                        <p><?= e($topPerformer['specialty']) ?></p>
                    </div>

                    <div class="report-pairs">
                        <div class="report-pair">
                            <strong>Collected value</strong>
                            <span><?= e(format_money((float) $topPerformer['collected_value'])) ?></span>
                        </div>
                        <div class="report-pair">
                            <strong>Completed sessions</strong>
                            <span><?= e((string) $topPerformer['completed_count']) ?></span>
                        </div>
                        <div class="report-pair">
                            <strong>Average ticket</strong>
                            <span><?= e(format_money((float) $topPerformer['average_ticket'])) ?></span>
                        </div>
                        <div class="report-pair">
                            <strong>Salary structure</strong>
                            <span><?= e(ucwords($topPerformer['salary_structure'])) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Therapist performance</p>
                    <h3>Operational contribution by staff member</h3>
                </div>
                <p class="report-table-note">Compare volume, collections, open balances, and service spread at a glance.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Therapist</th>
                        <th>Bookings</th>
                        <th>Completed</th>
                        <th>Scheduled</th>
                        <th>Collected</th>
                        <th>Open balance</th>
                        <th>Avg ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['performance_rows'] as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['staff_name']) ?></strong>
                                <span><?= e($row['specialty']) ?></span>
                            </td>
                            <td><?= e((string) $row['booking_count']) ?></td>
                            <td><?= e((string) $row['completed_count']) ?></td>
                            <td><?= e(format_money((float) $row['scheduled_value'])) ?></td>
                            <td><?= e(format_money((float) $row['collected_value'])) ?></td>
                            <td><?= e(format_money((float) $row['outstanding_value'])) ?></td>
                            <td><?= e(format_money((float) $row['average_ticket'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
