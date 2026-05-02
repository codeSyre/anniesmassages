<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

$currentUser = require_login();
require_permission('staff.view');

$staffId = (string) ($_GET['id'] ?? '');
$member = $staffId !== '' ? Staff::find($staffId) : null;

if ($member === null) {
    redirect_to('/staff/list.php');
}

$completedBookings = array_values(array_filter(Staff::bookings($member['id']), static fn (array $booking): bool => $booking['status'] === 'completed'));
$commissionRate = (float) $member['commission_rate'];
$commissionTotal = ((float) $member['commissionable_value']) * ($commissionRate / 100);
$estimatedPayout = match ($member['salary_structure']) {
    'fixed' => (float) $member['fixed_pay'],
    'hybrid' => (float) $member['fixed_pay'] + $commissionTotal,
    default => $commissionTotal,
};

$pageTitle = 'Staff Earnings';
$pageEyebrow = $member['name'];
$currentRoute = 'staff';
$topbarAction = ['label' => 'View profile', 'href' => '/staff/view.php?id=' . urlencode($member['id'])];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Earnings summary</p>
                <h1 class="hero-title"><?= e($member['name']) ?> performance base</h1>
                <p class="hero-copy">This pre-payroll view shows completed-booking value, the configured salary structure, and an estimated payout base before the payroll module takes over.</p>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="badge badge-info">Salary structure</span>
                    <strong><?= e(ucfirst($member['salary_structure'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Commission total</span>
                    <strong><?= e(format_money($commissionTotal)) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Estimated payout</span>
                    <strong><?= e(format_money($estimatedPayout)) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Compensation setup</p>
                        <h3>Current structure</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Commission rate</span><strong><?= e((string) $commissionRate) ?>%</strong><small>Applied to completed-booking value</small></div>
                    <div><span>Fixed pay</span><strong><?= e(format_money((float) $member['fixed_pay'])) ?></strong><small>Used in fixed or hybrid mode</small></div>
                    <div><span>Completed bookings</span><strong><?= e((string) $member['completed_count']) ?></strong><small><?= e(format_money((float) $member['commissionable_value'])) ?> commissionable</small></div>
                    <div><span>Completed-booking value</span><strong><?= e(format_money((float) $member['completed_value'])) ?></strong><small>Collected amount linked to completed work</small></div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Completed sessions</p>
                        <h3>Latest commissionable work</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach (array_slice($completedBookings, 0, 5) as $booking): ?>
                        <article class="info-item">
                            <strong><?= e($booking['customer']['name']) ?></strong>
                            <p><?= e($booking['service']['name']) ?> · <?= e(format_money((float) $booking['amount_total'])) ?> on <?= e(date('j M Y', strtotime($booking['date']))) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
