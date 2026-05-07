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

$history = Staff::bookings($member['id']);

$pageTitle = 'Staff Booking History';
$pageEyebrow = $member['name'];
$currentRoute = 'staff';
$topbarAction = ['label' => 'View profile', 'href' => '/staff/view.php?id=' . urlencode($member['id'])];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Booking history</p>
                    <h3><?= e($member['name']) ?> assignment timeline</h3>
                </div>
                <p>Every appointment linked to this therapist, including guest, service, status, and payment state.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Guest</th>
                        <th>Service</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $booking): ?>
                        <tr>
                            <td><strong><?= e($booking['reference']) ?></strong><span><?= e($booking['channel']) ?></span></td>
                            <td><strong><?= e($booking['customer']['name']) ?></strong><span><?= e($booking['customer']['phone']) ?></span></td>
                            <td><strong><?= e($booking['service']['name']) ?></strong><span><?= e((string) $booking['duration']) ?> min</span></td>
                            <td><strong><?= e(date('D, j M Y', strtotime($booking['date']))) ?></strong><span><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?></span></td>
                            <td><span class="<?= e(status_badge_class($booking['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span></td>
                            <td><strong><?= e(format_money((float) $booking['amount_paid'])) ?></strong><span><?= e(ucfirst($booking['payment_status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
