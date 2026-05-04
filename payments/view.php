<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payments.view');

$bookingId = (string) ($_GET['booking_id'] ?? '');
$details = $bookingId !== '' ? Payment::bookingDetails($bookingId) : null;

if ($details === null) {
    redirect_to('/payments/ledger.php');
}

$booking = $details['booking'];
$payments = $details['payments'];
$totals = $details['totals'];
$flashMessage = flash_get('payment_success');

$pageTitle = 'Booking Payment Details';
$pageEyebrow = $booking['reference'];
$currentRoute = 'payments';
$topbarAction = ['label' => 'Record payment', 'href' => '/payments/create.php?booking_id=' . urlencode($booking['id']), 'permission' => 'payments.create'];

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
                <p class="hero-eyebrow">Booking payment details</p>
                <h1 class="hero-title"><?= e($booking['customer']['name']) ?></h1>
                <p class="hero-copy"><?= e($booking['service']['name']) ?> with <?= e($booking['staff']['name']) ?> on <?= e(date('l, j F Y', strtotime($booking['date']))) ?>. This is the single finance view for how the booking has been settled so far.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payments/create.php?booking_id=<?= e($booking['id']) ?>">Record another payment</a>
                    <a class="action-link is-secondary" href="/notifications/logs.php?booking_id=<?= e($booking['id']) ?>">Notification logs</a>
                    <a class="action-link is-secondary" href="/bookings/view.php?id=<?= e($booking['id']) ?>">Open booking</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($totals['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $totals['status']))) ?></span>
                    <strong><?= e(format_money((float) $booking['amount_total'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Captured</span>
                    <strong><?= e(format_money((float) $totals['captured'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Outstanding</span>
                    <strong><?= e(format_money((float) $totals['balance'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Booking finance summary</p>
                        <h3>What this payment trail belongs to</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Booking</span><strong><?= e($booking['reference']) ?></strong><small><?= e($booking['status']) ?> booking</small></div>
                    <div><span>Guest</span><strong><?= e($booking['customer']['name']) ?></strong><small><?= e($booking['customer']['phone']) ?></small></div>
                    <div><span>Service</span><strong><?= e($booking['service']['name']) ?></strong><small><?= e(format_money((float) $booking['amount_total'])) ?> total</small></div>
                    <div><span>Therapist</span><strong><?= e($booking['staff']['name']) ?></strong><small><?= e(date('D, j M Y', strtotime($booking['date']))) ?> at <?= e($booking['time']) ?></small></div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Method breakdown</p>
                        <h3>How the booking was paid</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($details['method_breakdown'] as $method => $total): ?>
                        <?php if ((float) $total <= 0): continue; endif; ?>
                        <article class="info-item">
                            <strong><?= e(Payment::methodLabel((string) $method)) ?></strong>
                            <p><?= e(format_money((float) $total)) ?> posted through this channel.</p>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($payments === []): ?>
                        <article class="info-item">
                            <strong>No payment records yet</strong>
                            <p>This booking still needs its first ledger entry.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Transaction history</p>
                    <h3>All payments linked to this booking</h3>
                </div>
                <p><?= e((string) $totals['payment_count']) ?> entries recorded.</p>
            </div>

            <?php if ($payments === []): ?>
                <div class="empty-state">
                    <strong>No payment entries are linked to this booking yet.</strong>
                    <p>Record the first payment to start the ledger trail and update the booking balance.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong><?= e($payment['reference']) ?></strong>
                                    <span><?= e(date('D, j M Y', strtotime($payment['payment_date']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(Payment::methodLabel((string) $payment['method'])) ?></strong>
                                    <span><?= e($payment['recorded_by']) ?></span>
                                </td>
                                <td><strong><?= e(format_money((float) $payment['amount'])) ?></strong></td>
                                <td><span class="<?= e(status_badge_class($payment['payment_status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $payment['payment_status']))) ?></span></td>
                                <td><strong><?= e($payment['note'] !== '' ? $payment['note'] : 'No note added') ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
