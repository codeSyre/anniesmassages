<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payroll.manage');

$stats = Payroll::stats();
$currentPeriod = Payroll::currentPeriod();
$preview = Payroll::previewRun($currentPeriod + ['selected_staff' => []]);
$recentRuns = Payroll::recentRuns();
$flashMessage = flash_get('payroll_success');
$hasPayrollData = ($preview['items'] ?? []) !== [] || $recentRuns !== [];

$pageTitle = 'Payroll';
$pageEyebrow = 'Salary and payout operations';
$currentRoute = 'payroll';

if ($hasPayrollData) {
    $topbarActions = [
        ['label' => 'Generate payroll run', 'href' => '/payroll/run.php', 'permission' => 'payroll.manage'],
        ['label' => 'Payroll history', 'href' => '/payroll/history.php', 'permission' => 'payroll.manage'],
    ];
} else {
    $topbarAction = ['label' => 'Generate payroll run', 'href' => '/payroll/run.php', 'permission' => 'payroll.manage'];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasPayrollData ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasPayrollData): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Payroll dashboard</p>
                    <h1 class="hero-title">Run payroll from approved inputs, eligible bookings, and locked payout snapshots.</h1>
                    <p class="hero-copy">Payroll now combines staff profiles, approved bonuses or deductions, overtime, advance recoveries, and paid completed bookings before you move a run through review, approval, lock, and payout.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/payroll/run.php">Generate payroll run</a>
                        <a class="action-link is-secondary" href="/payroll/history.php">Payroll history</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasPayrollData ? ' module-stat-grid-quad' : '' ?>">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Current cycle preview</p>
                    <h3><?= e(date('j M', strtotime($currentPeriod['period_start']))) ?> - <?= e(date('j M Y', strtotime($currentPeriod['period_end']))) ?></h3>
                <p>Commission only counts completed and fully paid bookings. Gross pay also includes approved bonuses and overtime, while deductions, advances, and statutory withholds reduce net pay.</p>
                </div>
            </div>

                <?php if ($preview['items'] === []): ?>
                    <div class="empty-state">
                        <strong>No payroll items are available for the current period.</strong>
                        <p>Completed bookings or eligible staff are required to build the next payroll run.</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['staff_name']) ?></strong>
                                        <span><?= e(ucfirst($item['role_type'])) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= e(ucfirst($item['salary_structure'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= e((string) $item['commission_eligible_count']) ?> eligible</strong>
                                    </td>
                                    <td>
                                        <strong><?= e(format_money((float) $item['gross_pay'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= e(format_money((float) $item['total_deductions'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= e(format_money((float) $item['net_pay'])) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Recent runs</p>
                        <h3>Locked payroll activity</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($recentRuns as $run): ?>
                        <article class="info-item">
                            <strong><?= e($run['label']) ?></strong>
                            <p><?= e(Payroll::statusLabel((string) $run['status'])) ?> · <?= e(format_money((float) $run['totals']['net_payout'])) ?> · <?= e($run['reference']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Quick actions</p>
                    <h3>Move from forecast to payout</h3>
                </div>
            </div>

            <div class="quick-actions">
                <a class="action-card" href="/payroll/run.php">
                    <strong>Generate next payroll run</strong>
                    <p>Create a draft run from the current or custom date range, review the gross or deduction mix, then move it through review, approval, lock, and payout.</p>
                </a>
                <a class="action-card" href="/payroll/earnings.php">
                    <strong>Review live staff earnings</strong>
                    <p>Check the current payroll picture by staff member before you snapshot a run.</p>
                </a>
                <a class="action-card" href="/payroll/history.php">
                    <strong>Open payroll history</strong>
                    <p>See past runs, statuses, payout values, locked item-level snapshots, payslips, and payroll payment postings.</p>
                </a>
                <a class="action-card" href="/payroll/profiles.php">
                    <strong>Manage payroll profiles</strong>
                    <p>Set employment type, payment method, commission model, statutory settings, and advance limits per employee.</p>
                </a>
                <a class="action-card" href="/payroll/adjustments.php">
                    <strong>Capture payroll inputs</strong>
                    <p>Record bonuses, deductions, advance recoveries, and overtime as first-class payroll records.</p>
                </a>
            </div>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
