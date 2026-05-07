<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
require_permission('bookings.view');

$bookingId = (string) ($_GET['id'] ?? '');
$booking = $bookingId !== '' ? Booking::find($bookingId) : null;

if ($booking === null) {
    redirect_to('/bookings/list.php');
}

$flashMessage = flash_get('booking_success');
$errors = flash_get('booking_errors', []);
$canCancelBooking = user_can('bookings.update')
    && !in_array($booking['status'], ['completed', 'cancelled', 'no_show'], true);
$pageTitle = 'Booking Details';
$pageEyebrow = $booking['reference'];
$currentRoute = 'bookings';
$topbarAction = ['label' => 'Payment details', 'href' => '/payments/view.php?booking_id=' . urlencode($booking['id']), 'permission' => 'payments.view'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['booking'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['booking']) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Booking profile</p>
                <h1 class="hero-title"><?= e($booking['service']['name']) ?></h1>
                <p class="hero-copy"><?= e($booking['customer']['name']) ?> is scheduled with <?= e($booking['staff']['name']) ?> on <?= e(date('l, j F Y', strtotime($booking['date']))) ?> from <?= e($booking['time']) ?> to <?= e($booking['end_time']) ?>.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/bookings/edit.php?id=<?= e($booking['id']) ?>">Edit booking</a>
                    <a class="action-link is-secondary" href="/payments/create.php?booking_id=<?= e($booking['id']) ?>">Record payment</a>
                    <a class="action-link is-secondary" href="/bookings/list.php">Back to list</a>
                    <?php if ($canCancelBooking): ?>
                        <form
                            class="hero-action-form"
                            method="post"
                            action="/process/booking-save.php"
                            data-confirm-dialog-form
                            data-confirm-title="Cancel booking?"
                            data-confirm-message="Cancel <?= e($booking['reference']) ?> for <?= e($booking['customer']['name']) ?>? Any recorded payments will remain in the ledger and can be refunded separately."
                            data-confirm-submit-label="Cancel booking"
                        >
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="id" value="<?= e($booking['id']) ?>">
                            <button class="button-danger" type="submit">Cancel booking</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($booking['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span>
                    <strong><?= e($booking['reference']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($booking['payment_status'])) ?>"><?= e(ucfirst($booking['payment_status'])) ?></span>
                    <strong><?= e(format_money((float) $booking['amount_total'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Balance</span>
                    <strong><?= e(format_money((float) $booking['balance'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Booking summary</p>
                        <h3>Linked records</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Customer</span><strong><?= e($booking['customer']['name']) ?></strong><small><?= e($booking['customer']['phone']) ?></small></div>
                    <div><span>Service</span><strong><?= e($booking['service']['name']) ?></strong><small><?= e((string) $booking['duration']) ?> minutes</small></div>
                    <div><span>Therapist</span><strong><?= e($booking['staff']['name']) ?></strong><small><?= e($booking['staff']['specialty']) ?></small></div>
                    <div><span>Location</span><strong><?= e($booking['location']) ?></strong><small><?= e($booking['channel']) ?> booking</small></div>
                    <div><span>Schedule</span><strong><?= e(date('D, j M Y', strtotime($booking['date']))) ?></strong><small><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?></small></div>
                    <div><span>Notes</span><strong><?= e($booking['notes'] !== '' ? $booking['notes'] : 'No special notes added.') ?></strong><small><?= e($booking['customer']['preference']) ?></small></div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Workflow timeline</p>
                        <h3>Status history</h3>
                    </div>
                </div>

                <div class="timeline-list">
                    <?php foreach ($booking['history'] as $event): ?>
                        <article class="timeline-item">
                            <span class="<?= e(badge_class($event['tone'])) ?>"><?= e($event['label']) ?></span>
                            <p><?= e($event['meta']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
