<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payments.view');

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
$reconciliation = Payment::reconciliation($date);

$pageTitle = 'Daily Reconciliation';
$pageEyebrow = 'Payments ledger';
$currentRoute = 'payments';
$topbarAction = ['label' => 'Back to ledger', 'href' => '/payments/ledger.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Daily reconciliation</p>
                <h1 class="hero-title"><?= e(date('l, j F Y', strtotime($date))) ?></h1>
                <p class="hero-copy">Review everything captured on the day by payment method, compare it to outstanding bookings, and keep the finance handoff clean.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payments/create.php">Record payment</a>
                    <a class="action-link is-secondary" href="/payments/export.php?date_from=<?= e($date) ?>&date_to=<?= e($date) ?>">Export day CSV</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="badge badge-success">Net collected</span>
                    <strong><?= e(format_money((float) $reconciliation['net_total'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Gross captured</span>
                    <strong><?= e(format_money((float) $reconciliation['gross_total'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Refunds</span>
                    <strong><?= e(format_money((float) $reconciliation['refund_total'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Day filter</p>
                    <h3>Choose another day</h3>
                </div>
            </div>

            <form class="module-form" method="get" action="/payments/reconciliation.php">
                <div class="form-grid">
                    <label class="field">
                        <span>Reconciliation date</span>
                        <input type="date" name="date" value="<?= e($date) ?>">
                    </label>
                </div>

                <div class="button-row">
                    <button class="button-primary" type="submit">Load day</button>
                </div>
            </form>
        </section>

        <section class="detail-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Method totals</p>
                        <h3>Captured by channel</h3>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reconciliation['method_totals'] as $method => $total): ?>
                            <tr>
                                <td><strong><?= e(ucwords(str_replace('_', ' ', $method))) ?></strong></td>
                                <td><strong><?= e(format_money((float) $total)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Staff contribution</p>
                        <h3>Collected by therapist bookings</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($reconciliation['staff_totals'] as $staffName => $total): ?>
                        <article class="info-item">
                            <strong><?= e($staffName) ?></strong>
                            <p><?= e(format_money((float) $total)) ?> linked to their bookings on this date.</p>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($reconciliation['staff_totals'] === []): ?>
                        <article class="info-item">
                            <strong>No collections posted</strong>
                            <p>No non-refund payments were recorded for this day.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Transaction log</p>
                    <h3>Payments recorded on the day</h3>
                </div>
                <p><?= e((string) count($reconciliation['payments'])) ?> entries.</p>
            </div>

            <?php if ($reconciliation['payments'] === []): ?>
                <div class="empty-state">
                    <strong>No payments were posted on this date.</strong>
                    <p>Use the payment recording screen to start the ledger for the selected day.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Booking</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reconciliation['payments'] as $payment): ?>
                            <tr>
                                <td>
                                    <strong><?= e($payment['reference']) ?></strong>
                                    <span><?= e($payment['customer']['name'] ?? 'Guest') ?></span>
                                </td>
                                <td>
                                    <strong><?= e($payment['booking_reference']) ?></strong>
                                    <span><?= e($payment['service']['name'] ?? 'Service') ?></span>
                                </td>
                                <td><strong><?= e(ucwords(str_replace('_', ' ', $payment['method']))) ?></strong></td>
                                <td><strong><?= e(format_money((float) $payment['amount'])) ?></strong></td>
                                <td><span class="<?= e(status_badge_class($payment['payment_status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $payment['payment_status']))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="activity-card activity-card-standalone">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Outstanding on the day</p>
                    <h3>Bookings still carrying balance</h3>
                </div>
            </div>

            <?php if ($reconciliation['outstanding_bookings'] === []): ?>
                <div class="empty-state">
                    <strong>No bookings from this day are carrying an outstanding balance.</strong>
                    <p>That day is financially clean from the booking side.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Guest</th>
                            <th>Therapist</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reconciliation['outstanding_bookings'] as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= e($booking['reference']) ?></strong>
                                    <span><?= e($booking['service']['name']) ?></span>
                                </td>
                                <td><strong><?= e($booking['customer']['name']) ?></strong></td>
                                <td><strong><?= e($booking['staff']['name']) ?></strong></td>
                                <td><strong><?= e(format_money((float) $booking['balance'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
