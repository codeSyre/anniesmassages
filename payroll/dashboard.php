<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

$currentUser = require_login();
require_permission('payroll.manage');

$stats = Payroll::stats();
$currentPeriod = Payroll::currentPeriod();
$preview = Payroll::previewRun($currentPeriod + ['selected_staff' => []]);
$recentRuns = Payroll::recentRuns();
$flashMessage = flash_get('payroll_success');

$pageTitle = 'Payroll';
$pageEyebrow = 'Salary and payout operations';
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
                <p class="hero-eyebrow">Payroll dashboard</p>
                <h1 class="hero-title">Calculate staff payouts from completed work without losing audit control.</h1>
                <p class="hero-copy">Payroll runs snapshot therapist earnings, preserve manual adjustments, and move cleanly from draft to finalized to paid.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payroll/run.php">Generate payroll run</a>
                    <a class="action-link is-secondary" href="/payroll/history.php">Payroll history</a>
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

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Current cycle preview</p>
                        <h3><?= e(date('j M', strtotime($currentPeriod['period_start']))) ?> - <?= e(date('j M Y', strtotime($currentPeriod['period_end']))) ?></h3>
                    </div>
                    <p>Only completed bookings count toward commission, while fixed and hybrid structures still carry their configured base pay.</p>
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
                                <th>Completed</th>
                                <th>Commission</th>
                                <th>Projected payout</th>
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
                                        <span><?= e((string) $item['commission_rate']) ?>% commission · <?= e(format_money((float) $item['fixed_pay'])) ?> fixed</span>
                                    </td>
                                    <td>
                                        <strong><?= e((string) $item['completed_count']) ?> bookings</strong>
                                        <span><?= e(format_money((float) $item['commissionable_value'])) ?> commissionable</span>
                                    </td>
                                    <td>
                                        <strong><?= e(format_money((float) $item['commission_total'])) ?></strong>
                                        <span><?= e(format_money((float) $item['collected_value'])) ?> collected</span>
                                    </td>
                                    <td>
                                        <strong><?= e(format_money((float) $item['total_payout'])) ?></strong>
                                        <span><?= e('Base ' . format_money((float) $item['base_payout'])) ?></span>
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
                            <p><?= e(ucfirst($run['status'])) ?> · <?= e(format_money((float) $run['totals']['net_payout'])) ?> · <?= e($run['reference']) ?></p>
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
                    <p>Create a draft run from the current or custom date range, then apply manual adjustments before locking it.</p>
                </a>
                <a class="action-card" href="/payroll/earnings.php">
                    <strong>Review live staff earnings</strong>
                    <p>Check the current earnings picture by therapist before you snapshot a run.</p>
                </a>
                <a class="action-card" href="/payroll/history.php">
                    <strong>Open payroll history</strong>
                    <p>See past runs, statuses, payout values, and locked item-level snapshots for audit context.</p>
                </a>
            </div>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
