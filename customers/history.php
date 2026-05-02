<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

$currentUser = require_login();
require_permission('customers.view');

$customerId = (string) ($_GET['id'] ?? '');
$customer = $customerId !== '' ? Customer::find($customerId) : null;

if ($customer === null) {
    redirect_to('/customers/list.php');
}

$history = Customer::bookings($customer['id']);

$pageTitle = 'Customer Booking History';
$pageEyebrow = $customer['name'];
$currentRoute = 'customers';
$topbarAction = ['label' => 'View profile', 'href' => '/customers/view.php?id=' . urlencode($customer['id'])];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Customer history</p>
                    <h3><?= e($customer['name']) ?> booking timeline</h3>
                </div>
                <p>Past and future appointments for this guest, including therapist assignment, status, and outstanding balance.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Therapist</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $booking): ?>
                        <tr>
                            <td><strong><?= e($booking['reference']) ?></strong><span><?= e($booking['channel']) ?></span></td>
                            <td><strong><?= e(date('D, j M Y', strtotime($booking['date']))) ?></strong><span><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?></span></td>
                            <td><strong><?= e($booking['service']['name']) ?></strong><span><?= e((string) $booking['duration']) ?> min</span></td>
                            <td><strong><?= e($booking['staff']['name']) ?></strong><span><?= e($booking['staff']['specialty']) ?></span></td>
                            <td><span class="<?= e(status_badge_class($booking['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span></td>
                            <td><strong><?= e(format_money((float) $booking['amount_paid'])) ?></strong><span>Balance <?= e(format_money((float) $booking['balance'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
