<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$defaults = Report::defaultPayrollPeriod();
$filters = [
    'period_start' => (string) ($_GET['period_start'] ?? $defaults['period_start']),
    'period_end' => (string) ($_GET['period_end'] ?? $defaults['period_end']),
];

$report = Report::payroll($filters);

$pageTitle = 'Payroll Report';
$pageEyebrow = 'Projected payout and run history';
$currentRoute = 'reports';
$reportRoute = 'reports.payroll';
$topbarAction = ['label' => 'Payroll workspace', 'href' => '/payroll/dashboard.php', 'permission' => 'payroll.manage'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Payroll analytics</p>
                <h1 class="hero-title">Review projected payout, salary mix, and recent payroll run history.</h1>
                <p class="hero-copy">This report combines completed-booking earnings with saved payroll runs so you can compare projected payout pressure against what has already been finalized or paid.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payroll/dashboard.php">Open payroll</a>
                    <a class="action-link is-secondary" href="/payroll/history.php">Run history</a>
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
                    Period start
                    <input type="date" name="period_start" value="<?= e($report['filters']['period_start']) ?>">
                </label>
                <label>
                    Period end
                    <input type="date" name="period_end" value="<?= e($report['filters']['period_end']) ?>">
                </label>
                <div class="report-filter-actions">
                    <button class="topbar-cta" type="submit">Refresh report</button>
                    <a class="action-link is-secondary" href="/reports/payroll.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Salary structure mix</p>
                        <h3>Where payout weight is coming from</h3>
                    </div>
                    <p class="report-table-note">Grouped by compensation model across the selected period.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['structure_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span><?= e((string) $row['staff_count']) ?> staff · <?= e((string) $row['completed_count']) ?> completed sessions</span>
                            </div>
                            <em><?= e(format_money((float) $row['total_payout'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Recent run history</p>
                        <h3>Saved payroll runs in range</h3>
                    </div>
                    <p class="report-table-note">A compact view of what has already been drafted, finalized, or paid.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['run_rows'] as $run): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($run['reference']) ?></strong>
                                <span><?= e($run['label']) ?> · <?= e(date('j M', strtotime($run['period_start']))) ?> to <?= e(date('j M Y', strtotime($run['period_end']))) ?></span>
                            </div>
                            <em><?= e(ucfirst($run['status'])) ?> · <?= e(format_money((float) $run['totals']['net_payout'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Projected payout by therapist</p>
                    <h3>Earnings preview for the selected period</h3>
                </div>
                <p class="report-table-note">This table reflects the same earnings logic the payroll workspace uses before adjustments.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Therapist</th>
                        <th>Structure</th>
                        <th>Completed</th>
                        <th>Commissionable</th>
                        <th>Commission</th>
                        <th>Base payout</th>
                        <th>Total payout</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['earnings_rows'] as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['staff_name']) ?></strong>
                                <span><?= e($row['role_type']) ?></span>
                            </td>
                            <td><?= e(ucwords($row['salary_structure'])) ?></td>
                            <td><?= e((string) $row['completed_count']) ?></td>
                            <td><?= e(format_money((float) $row['commissionable_value'])) ?></td>
                            <td><?= e(format_money((float) $row['commission_total'])) ?></td>
                            <td><?= e(format_money((float) $row['base_payout'])) ?></td>
                            <td><?= e(format_money((float) $row['total_payout'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
