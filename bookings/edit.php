<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('bookings.update');

$bookingId = (string) ($_GET['id'] ?? '');
$booking = $bookingId !== '' ? Booking::find($bookingId) : null;

if ($booking === null) {
    redirect_to('/bookings/list.php');
}

$options = Booking::formOptions();
$activeServices = Service::activeOptions();
$serviceOptions = $activeServices;
if ($booking !== null && !array_filter($activeServices, static fn (array $service): bool => $service['id'] === $booking['service']['id'])) {
    $serviceOptions[] = $booking['service'];
}
$errors = flash_get('booking_errors', []);
$pageTitle = 'Edit Booking';
$pageEyebrow = $booking['reference'];
$currentRoute = 'bookings';
$topbarAction = ['label' => 'View booking', 'href' => '/bookings/view.php?id=' . urlencode($booking['id'])];

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
                        <p class="section-kicker">Booking update</p>
                        <h3>Adjust schedule or payment state</h3>
                    </div>
                    <p>Edit the assignment, timing, notes, or payment progress without leaving the operations flow.</p>
                </div>

                <form class="module-form" method="post" action="/process/booking-save.php">
                    <input type="hidden" name="id" value="<?= e($booking['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Customer</span>
                            <select name="customer_id">
                                <?php foreach ($options['customers'] as $customer): ?>
                                    <?php $selected = old_input('customer_id', $booking['customer']['id']) === $customer['id']; ?>
                                    <option value="<?= e($customer['id']) ?>" <?= $selected ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['customer_id'])): ?><small><?= e($errors['customer_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Service</span>
                            <select name="service_id">
                                <?php foreach ($serviceOptions as $service): ?>
                                    <?php $selected = old_input('service_id', $booking['service']['id']) === $service['id']; ?>
                                    <option value="<?= e($service['id']) ?>" <?= $selected ? 'selected' : '' ?>><?= e($service['name']) ?> · <?= e((string) $service['duration']) ?> min<?= empty($service['active']) ? ' · inactive' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['service_id'])): ?><small><?= e($errors['service_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Therapist</span>
                            <select name="staff_id">
                                <?php foreach ($options['staff'] as $staff): ?>
                                    <?php $selected = old_input('staff_id', $booking['staff']['id']) === $staff['id']; ?>
                                    <option value="<?= e($staff['id']) ?>" <?= $selected ? 'selected' : '' ?>><?= e($staff['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['staff_id'])): ?><small><?= e($errors['staff_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="date" value="<?= e((string) old_input('date', $booking['date'])) ?>">
                            <?php if (isset($errors['date'])): ?><small><?= e($errors['date']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Start time</span>
                            <input type="time" name="start_time" value="<?= e((string) old_input('start_time', $booking['time'])) ?>">
                            <?php if (isset($errors['start_time'])): ?><small><?= e($errors['start_time']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Channel</span>
                            <input type="text" name="channel" value="<?= e((string) old_input('channel', $booking['channel'])) ?>">
                        </label>

                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach ($options['statuses'] as $status): ?>
                                    <?php $selected = old_input('status', $booking['status']) === $status; ?>
                                    <option value="<?= e($status) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Payment status</span>
                            <select name="payment_status">
                                <?php foreach ($options['payment_statuses'] as $status): ?>
                                    <?php $selected = old_input('payment_status', $booking['payment_status']) === $status; ?>
                                    <option value="<?= e($status) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['payment_status'])): ?><small><?= e($errors['payment_status']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Amount paid</span>
                            <input type="number" min="0" step="0.01" name="amount_paid" value="<?= e((string) old_input('amount_paid', (string) $booking['amount_paid'])) ?>">
                            <?php if (isset($errors['amount_paid'])): ?><small><?= e($errors['amount_paid']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Notes</span>
                        <textarea name="notes" rows="5"><?= e((string) old_input('notes', $booking['notes'])) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/bookings/view.php?id=<?= e($booking['id']) ?>">Cancel</a>
                        <button class="button-primary" type="submit">Update booking</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Current summary</p>
                        <h3><?= e($booking['reference']) ?></h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e($booking['customer']['name']) ?></strong>
                        <p><?= e($booking['service']['name']) ?> with <?= e($booking['staff']['name']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e(date('l, j F Y', strtotime($booking['date']))) ?></strong>
                        <p><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?> at <?= e($booking['location']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong>Balance <?= e(format_money((float) $booking['balance'])) ?></strong>
                        <p>Status: <?= e(str_replace('_', ' ', $booking['payment_status'])) ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
