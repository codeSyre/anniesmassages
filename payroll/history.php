<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

$currentUser = require_login();
require_permission('payroll.manage');

$filters = [
    'status' => (string) ($_GET['status'] ?? 'all'),
    'search' => (string) ($_GET['search'] ?? ''),
];

$runs = Payroll::runs($filters);
$selectedRunId = (string) ($_GET['id'] ?? '');
$selectedRun = $selectedRunId !== '' ? Payroll::find($selectedRunId) : ($runs[0] ?? null);
$flashMessage = flash_get('payroll_success');

$pageTitle = 'Payroll History';
$pageEyebrow = 'Locked payout runs';
$currentRoute = 'payroll';
$topbarAction = ['label' => 'Generate payroll run', 'href' => '/payroll/run.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Payroll history</p>
                <h1 class="hero-title">Track every draft, finalized run, and paid payout batch.</h1>
                <p class="hero-copy">This history view is the audit trail for payroll. Once a run is finalized, the item snapshot stays fixed even if bookings change later.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payroll/run.php">New payroll run</a>
                    <a class="action-link is-secondary" href="/payroll/dashboard.php">Payroll dashboard</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="badge badge-info">Runs total</span>
                    <strong><?= e((string) count($runs)) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Draft or finalized</span>
                    <strong><?= e((string) count(array_filter($runs, static fn (array $run): bool => $run['status'] !== 'paid'))) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Paid value</span>
                    <strong><?= e(format_money(array_sum(array_map(static function (array $run): float {
                        return $run['status'] === 'paid' ? (float) $run['totals']['net_payout'] : 0.0;
                    }, $runs)))) ?></strong>
                </article>
            </aside>
        </section>

        <section class="filter-panel">
            <div class="filter-panel-row">
                <div class="filter-chip-row">
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/payroll/history.php">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'draft')) ?>" href="/payroll/history.php?status=draft">Draft</a>
                    <a class="<?= e(active_filter($filters['status'], 'finalized')) ?>" href="/payroll/history.php?status=finalized">Finalized</a>
                    <a class="<?= e(active_filter($filters['status'], 'paid')) ?>" href="/payroll/history.php?status=paid">Paid</a>
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
                                    <span class="<?= e(badge_class(Payroll::statusTone($run['status']))) ?>"><?= e(ucfirst($run['status'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $run['totals']['net_payout'])) ?></strong>
                                    <span><?= e((string) $run['totals']['staff_count']) ?> staff · <?= e((string) $run['totals']['completed_bookings']) ?> bookings</span>
                                </td>
                                <td class="row-actions">
                                    <a href="/payroll/history.php?id=<?= e($run['id']) ?>">View</a>
                                    <?php if ($run['status'] === 'draft'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="finalize">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="button-link" type="submit">Finalize</button>
                                        </form>
                                    <?php elseif ($run['status'] === 'finalized'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="pay">
                                            <input type="hidden" name="run_id" value="<?= e($run['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/history.php?id=' . urlencode($run['id'])) ?>">
                                            <button class="button-link" type="submit">Mark paid</button>
                                        </form>
                                    <?php endif; ?>
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
                        <p><?= e($selectedRun['reference']) ?> · <?= e(ucfirst($selectedRun['status'])) ?></p>
                    </div>

                    <div class="detail-pairs">
                        <div><span>Period</span><strong><?= e(date('j M Y', strtotime($selectedRun['period_start']))) ?> - <?= e(date('j M Y', strtotime($selectedRun['period_end']))) ?></strong><small><?= e($selectedRun['created_by']) ?> created this run</small></div>
                        <div><span>Net payout</span><strong><?= e(format_money((float) $selectedRun['totals']['net_payout'])) ?></strong><small><?= e(format_money((float) $selectedRun['totals']['adjustment_total'])) ?> adjustments</small></div>
                        <div><span>Bookings counted</span><strong><?= e((string) $selectedRun['totals']['completed_bookings']) ?></strong><small><?= e((string) $selectedRun['totals']['staff_count']) ?> staff items</small></div>
                        <div><span>Notes</span><strong><?= e($selectedRun['notes'] !== '' ? $selectedRun['notes'] : 'No run notes recorded') ?></strong><small><?= e($selectedRun['paid_at'] !== null ? 'Paid ' . date('j M Y H:i', strtotime($selectedRun['paid_at'])) : ($selectedRun['finalized_at'] !== null ? 'Finalized ' . date('j M Y H:i', strtotime($selectedRun['finalized_at'])) : 'Still editable draft')) ?></small></div>
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
                                    <th>Completed</th>
                                    <th>Adjustment</th>
                                    <th>Total payout</th>
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
                                            <span><?= e((string) $item['commission_rate']) ?>% commission · <?= e(format_money((float) $item['fixed_pay'])) ?> fixed</span>
                                        </td>
                                        <td>
                                            <strong><?= e((string) $item['completed_count']) ?> completed</strong>
                                            <span><?= e(format_money((float) $item['commission_total'])) ?> commission</span>
                                        </td>
                                        <td>
                                            <strong><?= e(format_money((float) $item['adjustment'])) ?></strong>
                                            <span><?= e($item['adjustment_note'] !== '' ? $item['adjustment_note'] : 'No note') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= e(format_money((float) $item['total_payout'])) ?></strong>
                                            <span>Base <?= e(format_money((float) $item['base_payout'])) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
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
