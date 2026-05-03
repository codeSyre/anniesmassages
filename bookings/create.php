<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('bookings.create');

$options = Booking::formOptions();
$activeServices = Service::activeOptions();
$activeCustomers = array_values(array_filter($options['customers'], static fn (array $customer): bool => ($customer['status'] ?? 'active') !== 'banned'));
$errors = flash_get('booking_errors', []);
$pageTitle = 'Create Booking';
$pageEyebrow = 'New appointment';
$currentRoute = 'bookings';
$topbarAction = ['label' => 'Back to bookings', 'href' => '/bookings/list.php'];

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
                        <p class="section-kicker">Booking intake</p>
                        <h3>Create a new booking</h3>
                    </div>
                    <p>Bookings need a guest, service, therapist, date, and time before they can be placed on the calendar.</p>
                </div>

                <?php if (isset($errors['booking'])): ?>
                    <div class="notice-banner notice-banner-danger"><?= e($errors['booking']) ?></div>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/booking-save.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Customer</span>
                            <select name="customer_id">
                                <option value="">Select customer</option>
                                <?php foreach ($activeCustomers as $customer): ?>
                                    <option value="<?= e($customer['id']) ?>" <?= old_input('customer_id') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['customer_id'])): ?><small><?= e($errors['customer_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Service</span>
                            <select name="service_id">
                                <option value="">Select service</option>
                                <?php foreach ($activeServices as $service): ?>
                                    <option value="<?= e($service['id']) ?>" <?= old_input('service_id') === $service['id'] ? 'selected' : '' ?>><?= e($service['name']) ?> · <?= e((string) $service['duration']) ?> min</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['service_id'])): ?><small><?= e($errors['service_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Therapist</span>
                            <select name="staff_id">
                                <option value="">Assign therapist</option>
                                <?php foreach ($options['staff'] as $staff): ?>
                                    <option value="<?= e($staff['id']) ?>" <?= old_input('staff_id') === $staff['id'] ? 'selected' : '' ?>><?= e($staff['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['staff_id'])): ?><small><?= e($errors['staff_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="date" value="<?= e((string) old_input('date', date('Y-m-d'))) ?>">
                            <?php if (isset($errors['date'])): ?><small><?= e($errors['date']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Start time</span>
                            <input type="time" name="start_time" value="<?= e((string) old_input('start_time', '09:00')) ?>">
                            <?php if (isset($errors['start_time'])): ?><small><?= e($errors['start_time']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Channel</span>
                            <input type="text" name="channel" value="<?= e((string) old_input('channel', 'front desk')) ?>" placeholder="front desk, web, phone">
                        </label>

                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach ($options['statuses'] as $status): ?>
                                    <?php $selected = old_input('status', 'pending') === $status; ?>
                                    <option value="<?= e($status) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Payment status</span>
                            <select name="payment_status">
                                <?php foreach ($options['payment_statuses'] as $status): ?>
                                    <?php $selected = old_input('payment_status', 'unpaid') === $status; ?>
                                    <option value="<?= e($status) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['payment_status'])): ?><small><?= e($errors['payment_status']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Amount paid</span>
                            <input type="number" min="0" step="0.01" name="amount_paid" value="<?= e((string) old_input('amount_paid', '0')) ?>" placeholder="0.00">
                            <?php if (isset($errors['amount_paid'])): ?><small><?= e($errors['amount_paid']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Notes</span>
                        <textarea name="notes" rows="5" placeholder="Preferences, room prep, private notes..."><?= e((string) old_input('notes')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/bookings/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save booking</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Booking rules</p>
                        <h3>Guard rails</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Service duration controls slot length</strong>
                        <p>The selected service determines how long the appointment occupies the therapist calendar.</p>
                    </article>
                    <article class="info-item">
                        <strong>Double-booking should be blocked</strong>
                        <p>Live conflict checks now compare therapist workload and blocked periods before the booking is accepted.</p>
                    </article>
                    <article class="info-item">
                        <strong>Status drives operations</strong>
                        <p>Use pending for unconfirmed appointments, confirmed for locked schedules, and completed for payroll/reporting inclusion.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
