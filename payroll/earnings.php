<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payroll.manage');

$filters = [
    'period_start' => (string) ($_GET['period_start'] ?? date('Y-m-01')),
    'period_end' => (string) ($_GET['period_end'] ?? date('Y-m-t')),
    'staff_id' => (string) ($_GET['staff_id'] ?? 'all'),
];

$earnings = Payroll::earnings($filters);
$stats = Payroll::earningsStats($filters);
$staffOptions = Payroll::staffOptions();

$pageTitle = 'Payroll Earnings';
$pageEyebrow = 'Live payout preview';
$currentRoute = 'payroll';
$topbarAction = ['label' => 'Generate payroll run', 'href' => '/payroll/run.php?period_start=' . urlencode($filters['period_start']) . '&period_end=' . urlencode($filters['period_end'])];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Staff earnings</p>
                <h1 class="hero-title">Review live payroll math before you lock it into a run.</h1>
                <p class="hero-copy">This page stays live against eligible bookings, payroll profiles, and approved payroll inputs, so it is the best place to sanity-check gross pay, deductions, and net payout before snapshotting payroll.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payroll/run.php?period_start=<?= e($filters['period_start']) ?>&period_end=<?= e($filters['period_end']) ?>">Use this for a run</a>
                    <a class="action-link is-secondary" href="/staff/list.php">Staff roster</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Filters</p>
                    <h3>Choose the earnings window</h3>
                </div>
            </div>

            <form class="module-form" method="get" action="/payroll/earnings.php">
                <div class="form-grid">
                    <label class="field">
                        <span>Period start</span>
                        <input type="date" name="period_start" value="<?= e($filters['period_start']) ?>">
                    </label>
                    <label class="field">
                        <span>Period end</span>
                        <input type="date" name="period_end" value="<?= e($filters['period_end']) ?>">
                    </label>
                    <label class="field">
                        <span>Therapist</span>
                        <select name="staff_id">
                            <option value="all">All therapists</option>
                            <?php foreach ($staffOptions as $option): ?>
                                <option value="<?= e($option['id']) ?>" <?= $filters['staff_id'] === $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="button-row">
                    <a class="button-muted" href="/payroll/earnings.php">Reset</a>
                    <button class="button-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Live earnings table</p>
                    <h3>What the current period would pay</h3>
                </div>
                <p><?= e((string) count($earnings)) ?> staff rows in scope.</p>
            </div>

            <?php if ($earnings === []): ?>
                <div class="empty-state">
                    <strong>No eligible staff earnings matched this filter.</strong>
                    <p>Try widening the date range or switching back to all therapists.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                            <tr>
                                <th>Therapist</th>
                                <th>Structure</th>
                                <th>Eligible work</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net</th>
                                <th></th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($earnings as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['staff_name']) ?></strong>
                                    <span><?= e(ucfirst($row['role_type'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(ucfirst($row['salary_structure'])) ?></strong>
                                    <span><?= e(ucfirst($row['employment_type'])) ?> · <?= e(Payment::methodLabel((string) $row['payment_method'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $row['commission_eligible_count']) ?> eligible</strong>
                                    <span><?= e((string) $row['completed_count']) ?> completed in period</span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $row['gross_pay'])) ?></strong>
                                    <span>Commission <?= e(format_money((float) $row['commission_total'])) ?> · Overtime <?= e(format_money((float) $row['overtime_total'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $row['total_deductions'])) ?></strong>
                                    <span>Tax <?= e(format_money((float) $row['tax_amount'])) ?> · Advances <?= e(format_money((float) $row['advance_total'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $row['net_pay'])) ?></strong>
                                    <span><?= e($row['commission_source_summary']) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/staff/earnings.php?id=<?= e($row['staff_id']) ?>">Staff detail</a>
                                    <a href="/payroll/run.php?period_start=<?= e(urlencode($filters['period_start'])) ?>&period_end=<?= e(urlencode($filters['period_end'])) ?>&staff_id=<?= e(urlencode($row['staff_id'])) ?>">Build run</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
