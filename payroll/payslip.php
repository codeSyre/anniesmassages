<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payroll.manage');

$runId = trim((string) ($_GET['run_id'] ?? ''));
$staffId = trim((string) ($_GET['staff_id'] ?? ''));
$run = $runId !== '' ? Payroll::find($runId) : null;
$item = null;

if (is_array($run)) {
    foreach ($run['items'] as $row) {
        if ((string) ($row['staff_id'] ?? '') === $staffId) {
            $item = $row;
            break;
        }
    }
}

if (!is_array($run) || !is_array($item)) {
    redirect_to('/payroll/history.php');
}

$payslip = Payroll::payslipForRunStaff($runId, $staffId);

$pageTitle = 'Payslip';
$pageEyebrow = 'Locked payroll document';
$currentRoute = 'payroll';
$topbarAction = ['label' => 'Back to run', 'href' => '/payroll/history.php?id=' . urlencode($runId)];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow"><?= e($payslip['reference'] ?? $run['reference']) ?></p>
                <h1 class="hero-title"><?= e($item['staff_name']) ?> payslip</h1>
                <p class="hero-copy"><?= e(date('j M Y', strtotime($run['period_start']))) ?> to <?= e(date('j M Y', strtotime($run['period_end']))) ?> · <?= e(Payroll::statusLabel((string) $run['status'])) ?></p>

                <div class="hero-actions">
                    <a class="action-link" href="/payroll/history.php?id=<?= e($runId) ?>">Back to payroll run</a>
                    <a class="action-link is-secondary" href="/payroll/profiles.php?staff_id=<?= e($staffId) ?>">Open payroll profile</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="badge badge-info">Gross pay</span>
                    <strong><?= e(format_money((float) $item['gross_pay'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-danger">Deductions</span>
                    <strong><?= e(format_money((float) $item['total_deductions'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Net pay</span>
                    <strong><?= e(format_money((float) $item['net_pay'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Payment method</span>
                    <strong><?= e(Payment::methodLabel((string) $item['payment_method'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Earnings breakdown</p>
                        <h3>What built this payslip</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Base payroll</span><strong><?= e(format_money((float) $item['base_payout'])) ?></strong><small><?= e(ucfirst((string) $item['salary_structure'])) ?> structure</small></div>
                    <div><span>Commission</span><strong><?= e(format_money((float) $item['commission_total'])) ?></strong><small><?= e((string) ($item['commission_eligible_count'] ?? $item['completed_count'])) ?> eligible bookings</small></div>
                    <div><span>Bonus</span><strong><?= e(format_money((float) ($item['bonus_total'] ?? 0))) ?></strong><small>Approved additions</small></div>
                    <div><span>Overtime</span><strong><?= e(format_money((float) ($item['overtime_total'] ?? 0))) ?></strong><small><?= e(format_quantity((float) ($item['overtime_hours'] ?? 0))) ?> hours</small></div>
                    <div><span>Tax</span><strong><?= e(format_money((float) ($item['tax_amount'] ?? 0))) ?></strong><small>Statutory</small></div>
                    <div><span>Pension</span><strong><?= e(format_money((float) ($item['pension_amount'] ?? 0))) ?></strong><small>Statutory</small></div>
                    <div><span>NSSA</span><strong><?= e(format_money((float) ($item['nssa_amount'] ?? 0))) ?></strong><small>Statutory</small></div>
                    <div><span>Medical aid</span><strong><?= e(format_money((float) ($item['medical_aid_amount'] ?? 0))) ?></strong><small>Flat deduction</small></div>
                    <div><span>Advance recovery</span><strong><?= e(format_money((float) ($item['advance_total'] ?? 0))) ?></strong><small>Recovered this run</small></div>
                    <div><span>Other deductions</span><strong><?= e(format_money((float) ($item['deduction_total_manual'] ?? 0))) ?></strong><small>Approved manual deductions</small></div>
                    <div><span>Manual adjustment</span><strong><?= e(format_money((float) ($item['adjustment'] ?? 0))) ?></strong><small><?= e((string) ($item['adjustment_note'] ?? 'No note')) ?></small></div>
                    <div><span>Net pay</span><strong><?= e(format_money((float) $item['net_pay'])) ?></strong><small><?= e($item['commission_source_summary']) ?></small></div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Run context</p>
                        <h3>Document trace</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e($run['reference']) ?></strong>
                        <p><?= e($run['label']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($payslip['reference'] ?? 'Pending payslip reference') ?></strong>
                        <p><?= e($payslip !== null ? 'Generated ' . date('j M Y H:i', strtotime((string) $payslip['generated_at'])) : 'Payslip record not generated yet') ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($run['created_by']) ?></strong>
                        <p>Run owner</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
