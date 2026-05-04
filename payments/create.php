<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payments.create');

$bookingId = (string) ($_GET['booking_id'] ?? old_input('booking_id', ''));
$booking = $bookingId !== '' ? Booking::find($bookingId) : null;
$bookingOptions = Payment::bookingOptions();
$errors = flash_get('payment_errors', []);

$pageTitle = 'Record Payment';
$pageEyebrow = 'Payments ledger';
$currentRoute = 'payments';
$topbarAction = ['label' => 'Back to ledger', 'href' => '/payments/ledger.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Payment intake</p>
                        <h3>Record a booking payment</h3>
                    </div>
                    <p>Each payment links back to one booking so balances, booking payment status, and reconciliation all stay aligned.</p>
                </div>

                <?php if (isset($errors['payment'])): ?>
                    <div class="notice-banner notice-banner-danger"><?= e($errors['payment']) ?></div>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/payment-save.php">
                    <input type="hidden" name="recorded_by" value="<?= e($currentUser['name'] ?? 'Admin panel') ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Booking</span>
                            <select name="booking_id">
                                <option value="">Select a booking</option>
                                <?php foreach ($bookingOptions as $option): ?>
                                    <option value="<?= e($option['id']) ?>" <?= (string) old_input('booking_id', $bookingId) === $option['id'] ? 'selected' : '' ?>>
                                        <?= e($option['reference'] . ' · ' . $option['customer']['name'] . ' · ' . date('j M', strtotime($option['date'])) . ' · Balance ' . format_money((float) $option['balance'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['booking_id'])): ?><small><?= e($errors['booking_id']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Payment date</span>
                            <input type="date" name="payment_date" value="<?= e((string) old_input('payment_date', date('Y-m-d'))) ?>">
                            <?php if (isset($errors['payment_date'])): ?><small><?= e($errors['payment_date']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Method</span>
                            <select name="method">
                                <?php foreach (Payment::methods() as $method): ?>
                                    <option value="<?= e($method) ?>" <?= (string) old_input('method', 'cash') === $method ? 'selected' : '' ?>><?= e(Payment::methodLabel($method)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['method'])): ?><small><?= e($errors['method']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Amount</span>
                            <input type="number" min="0.01" step="0.01" name="amount" value="<?= e((string) old_input('amount', $booking !== null ? number_format((float) $booking['balance'], 2, '.', '') : '')) ?>" placeholder="0.00">
                            <?php if (isset($errors['amount'])): ?><small><?= e($errors['amount']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Note</span>
                        <textarea name="note" rows="4" placeholder="Optional context for reconciliation, proof, or cashier notes..."><?= e((string) old_input('note')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="<?= e($booking !== null ? '/payments/view.php?booking_id=' . urlencode($booking['id']) : '/payments/ledger.php') ?>">Cancel</a>
                        <button class="button-primary" type="submit">Record payment</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Booking context</p>
                        <h3><?= e($booking !== null ? $booking['reference'] : 'Select a booking') ?></h3>
                    </div>
                </div>

                <?php if ($booking === null): ?>
                    <div class="info-list">
                        <article class="info-item">
                            <strong>Pick the booking first</strong>
                            <p>Choose a booking to review the guest, therapist, total due, and current balance before posting a payment.</p>
                        </article>
                        <article class="info-item">
                            <strong>Partial payments are supported</strong>
                            <p>Deposits and staged settlements keep the booking open with a partial status until the balance reaches zero.</p>
                        </article>
                    </div>
                <?php else: ?>
                    <div class="detail-pairs">
                        <div><span>Guest</span><strong><?= e($booking['customer']['name']) ?></strong><small><?= e($booking['customer']['phone']) ?></small></div>
                        <div><span>Service</span><strong><?= e($booking['service']['name']) ?></strong><small><?= e($booking['staff']['name']) ?></small></div>
                        <div><span>Total due</span><strong><?= e(format_money((float) $booking['amount_total'])) ?></strong><small><?= e(ucfirst(str_replace('_', ' ', $booking['payment_status']))) ?></small></div>
                        <div><span>Outstanding</span><strong><?= e(format_money((float) $booking['balance'])) ?></strong><small><?= e(date('D, j M Y', strtotime($booking['date']))) ?> at <?= e($booking['time']) ?></small></div>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
