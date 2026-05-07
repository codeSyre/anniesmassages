<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payroll.manage');

$filters = [
    'status' => (string) ($_GET['status'] ?? 'all'),
    'search' => (string) ($_GET['search'] ?? ''),
];

$allRuns = Payroll::runs();
$runs = Payroll::runs($filters);
$selectedRunId = (string) ($_GET['id'] ?? '');
$selectedRun = $selectedRunId !== '' ? Payroll::find($selectedRunId) : ($runs[0] ?? null);
$flashMessage = flash_get('payroll_success');
$flashError = flash_get('payroll_error');
$hasPayrollHistory = $allRuns !== [];
$historyStats = [
    ['label' => 'Runs total', 'value' => (string) count($allRuns), 'tone' => 'info'],
    ['label' => 'Open workflow runs', 'value' => (string) count(array_filter($allRuns, static fn (array $run): bool => !in_array($run['status'], ['paid', 'cancelled'], true))), 'tone' => 'warning'],
    ['label' => 'Locked awaiting payout', 'value' => (string) count(array_filter($allRuns, static fn (array $run): bool => $run['status'] === 'locked')), 'tone' => 'info'],
    ['label' => 'Paid value', 'value' => format_money(array_sum(array_map(static function (array $run): float {
        return $run['status'] === 'paid' ? (float) $run['totals']['net_payout'] : 0.0;
    }, $allRuns))), 'tone' => 'success'],
];

$pageTitle = 'Payroll History';
$pageEyebrow = 'Locked payout runs';
$currentRoute = 'payroll';
if ($hasPayrollHistory) {
    $topbarActions = [
        ['label' => 'Generate payroll run', 'href' => '/payroll/run.php', 'permission' => 'payroll.manage'],
        ['label' => 'Payroll dashboard', 'href' => '/payroll/dashboard.php', 'permission' => 'payroll.manage'],
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
        <?php if (is_string($flashError) && $flashError !== ''): ?>
            <div class="notice-banner notice-banner-warning"><?= e($flashError) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasPayrollHistory ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasPayrollHistory): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Payroll history</p>
                    <h1 class="hero-title">Track every draft, reviewed, locked, and paid payroll batch.</h1>
                    <p class="hero-copy">This history view is the audit trail for payroll. Once a run is locked, the item snapshot stays fixed, payslips are generated, and payout posting creates payroll payment ledger entries.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/payroll/run.php">New payroll run</a>
                        <a class="action-link is-secondary" href="/payroll/dashboard.php">Payroll dashboard</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasPayrollHistory ? ' module-stat-grid-quad' : '' ?>">
                <?php foreach ($historyStats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="filter-panel">
            <div class="filter-panel-row">
                <div class="filter-chip-row">
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/payroll/history.php">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'draft')) ?>" href="/payroll/history.php?status=draft">Draft</a>
                    <a class="<?= e(active_filter($filters['status'], 'under_review')) ?>" href="/payroll/history.php?status=under_review">Under review</a>
                    <a class="<?= e(active_filter($filters['status'], 'approved')) ?>" href="/payroll/history.php?status=approved">Approved</a>
                    <a class="<?= e(active_filter($filters['status'], 'locked')) ?>" href="/payroll/history.php?status=locked">Locked</a>
                    <a class="<?= e(active_filter($filters['status'], 'paid')) ?>" href="/payroll/history.php?status=paid">Paid</a>
                    <a class="<?= e(active_filter($filters['status'], 'cancelled')) ?>" href="/payroll/history.php?status=cancelled">Cancelled</a>
                </div>

                <form class="inline-search" method="get" action="/payroll/history.php">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search run label, reference, or notes">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Run register</p>
                    <h3>Payroll runs in the system</h3>
                </div>
                <p>Select a run below to inspect item-level payouts and audit notes.</p>
            </div>

            <?php if ($runs === []): ?>
                <div class="empty-state">
                    <strong>No payroll runs matched the current filters.</strong>
                    <p>Create a new run to start the payroll trail.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Totals</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <tr>
                                <td>
                                    <strong><?= e($run['label']) ?></strong>
                                    <span><?= e($run['reference']) ?> · <?= e($run['created_by']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(date('j M Y', strtotime($run['period_start']))) ?></strong>
                                    <span><?= e(date('j M Y', strtotime($run['period_end']))) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(badge_class(Payroll::statusTone($run['status']))) ?>"><?= e(Payroll::statusLabel((string) $run['status'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $run['totals']['net_payout'])) ?></strong>
                                    <span><?= e((string) $run['totals']['staff_count']) ?> staff · Gross <?= e(format_money((float) $run['totals']['gross_pay'])) ?></span>
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/payroll/history.php?id=<?= e($run['id']) ?>" aria-label="View <?= e($run['reference']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                    <?php if ($run['status'] === 'draft'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="submit_review">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="icon-action-button" type="submit" aria-label="Send <?= e($run['reference']) ?> for review" title="Send for review">
                                                <?= action_icon_svg('review') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($run['status'] === 'under_review'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="icon-action-button" type="submit" aria-label="Approve <?= e($run['reference']) ?>" title="Approve">
                                                <?= action_icon_svg('approve') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($run['status'] === 'approved'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="lock">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="icon-action-button icon-action-button-warning" type="submit" aria-label="Lock <?= e($run['reference']) ?>" title="Lock">
                                                <?= action_icon_svg('lock') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($run['status'] === 'locked'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="pay">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="icon-action-button" type="submit" aria-label="Post payout for <?= e($run['reference']) ?>" title="Post payout">
                                                <?= action_icon_svg('pay') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($selectedRun !== null): ?>
            <section class="detail-grid section-spaced">
                <article class="table-card">
                    <div class="section-head">
                        <div>
                            <p class="section-kicker">Selected run</p>
                            <h3><?= e($selectedRun['label']) ?></h3>
                        </div>
                        <p><?= e($selectedRun['reference']) ?> · <?= e(Payroll::statusLabel((string) $selectedRun['status'])) ?></p>
                    </div>

                    <div class="detail-pairs">
                        <div><span>Period</span><strong><?= e(date('j M Y', strtotime($selectedRun['period_start']))) ?> - <?= e(date('j M Y', strtotime($selectedRun['period_end']))) ?></strong><small><?= e($selectedRun['created_by']) ?> created this run</small></div>
                        <div><span>Net payout</span><strong><?= e(format_money((float) $selectedRun['totals']['net_payout'])) ?></strong><small>Gross <?= e(format_money((float) $selectedRun['totals']['gross_pay'])) ?> · Deductions <?= e(format_money((float) $selectedRun['totals']['deduction_total'])) ?></small></div>
                        <div><span>Bookings counted</span><strong><?= e((string) $selectedRun['totals']['completed_bookings']) ?></strong><small><?= e((string) $selectedRun['totals']['staff_count']) ?> staff items</small></div>
                        <div><span>Notes</span><strong><?= e($selectedRun['notes'] !== '' ? $selectedRun['notes'] : 'No run notes recorded') ?></strong><small><?= e($selectedRun['paid_at'] !== null ? 'Paid ' . date('j M Y H:i', strtotime($selectedRun['paid_at'])) : ($selectedRun['locked_at'] !== null ? 'Locked ' . date('j M Y H:i', strtotime($selectedRun['locked_at'])) : 'Still in workflow')) ?></small></div>
                    </div>

                    <section class="table-card payroll-preview-card section-spaced">
                        <div class="section-head">
                            <div>
                                <p class="section-kicker">Run items</p>
                                <h3>Locked payout lines</h3>
                            </div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Therapist</th>
                                    <th>Structure</th>
                                    <th>Gross</th>
                                    <th>Deductions</th>
                                    <th>Net</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedRun['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($item['staff_name']) ?></strong>
                                            <span><?= e(ucfirst($item['role_type'])) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= e(ucfirst($item['salary_structure'])) ?></strong>
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
                                        <td class="row-actions-cell">
                                            <div class="row-actions">
                                                <?php if (($selectedRun['status'] === 'locked' || $selectedRun['status'] === 'paid') && ($item['staff_id'] ?? '') !== ''): ?>
                                                    <a class="icon-action-button" href="/payroll/payslip.php?run_id=<?= e(urlencode($selectedRun['id'])) ?>&staff_id=<?= e(urlencode((string) $item['staff_id'])) ?>" aria-label="Open payslip for <?= e($item['staff_name']) ?>" title="Payslip">
                                                        <?= action_icon_svg('payslip') ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (in_array($selectedRun['status'], ['draft', 'under_review', 'approved'], true)): ?>
                            <div class="button-row section-spaced">
                                <form method="post" action="/process/payroll-save.php">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="run_id" value="<?= e($selectedRun['id']) ?>">
                                    <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($selectedRun['id'])) ?>">
                                    <button class="button-danger" type="submit">Cancel run</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if (($selectedRun['payment_entries'] ?? []) !== []): ?>
                        <section class="table-card payroll-preview-card section-spaced">
                            <div class="section-head">
                                <div>
                                    <p class="section-kicker">Payroll ledger</p>
                                    <h3>Posted payroll payment entries</h3>
                                </div>
                            </div>

                            <table>
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Staff</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($selectedRun['payment_entries'] as $entry): ?>
                                        <tr>
                                            <td><strong><?= e($entry['reference']) ?></strong><span><?= e($entry['payment_date']) ?></span></td>
                                            <td><strong><?= e($entry['staff_name']) ?></strong><span><?= e($entry['recorded_by']) ?></span></td>
                                            <td><strong><?= e(Payment::methodLabel((string) $entry['payment_method'])) ?></strong></td>
                                            <td><strong><?= e(format_money((float) $entry['amount'])) ?></strong></td>
                                            <td><span class="<?= e(status_badge_class((string) $entry['payment_status'])) ?>"><?= e(ucfirst((string) $entry['payment_status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php endif; ?>
                </article>

                <aside class="activity-card">
                    <div class="section-head">
                        <div>
                            <p class="section-kicker">Audit trail</p>
                            <h3>Status history</h3>
                        </div>
                    </div>

                    <div class="timeline-list">
                        <?php foreach ($selectedRun['history'] as $event): ?>
                            <article class="timeline-item">
                                <span class="<?= e(badge_class($event['tone'])) ?>"><?= e($event['label']) ?></span>
                                <p><?= e($event['meta']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>
        <?php endif; ?>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
