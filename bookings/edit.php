<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('bookings.update');

$bookingId = (string) ($_GET['id'] ?? '');
$booking = $bookingId !== '' ? Booking::find($bookingId) : null;

if ($booking === null) {
    redirect_to('/bookings/list.php');
}

$options = Booking::formOptions();
$activeCustomers = array_values(array_filter($options['customers'], static fn (array $customer): bool => ($customer['status'] ?? 'active') !== 'banned'));
$customerOptions = $activeCustomers;
if ($booking !== null && !array_filter($activeCustomers, static fn (array $customer): bool => $customer['id'] === $booking['customer']['id'])) {
    $customerOptions[] = $booking['customer'];
}
$selectedCustomerId = (string) old_input('customer_id', $booking['customer']['id']);
$customerSearchOptions = array_map(static function (array $customer): array {
    $searchLabelParts = [$customer['name']];
    if (!empty($customer['phone'])) {
        $searchLabelParts[] = $customer['phone'];
    }
    if (($customer['status'] ?? 'active') === 'banned') {
        $searchLabelParts[] = 'banned';
    }

    return [
        'id' => $customer['id'],
        'label' => implode(' · ', $searchLabelParts),
    ];
}, $customerOptions);
$selectedCustomerLabel = '';
foreach ($customerSearchOptions as $customerSearchOption) {
    if ($customerSearchOption['id'] === $selectedCustomerId) {
        $selectedCustomerLabel = $customerSearchOption['label'];
        break;
    }
}
$activeServices = Service::activeOptions();
$serviceOptions = $activeServices;
if ($booking !== null && !array_filter($activeServices, static fn (array $service): bool => $service['id'] === $booking['service']['id'])) {
    $serviceOptions[] = $booking['service'];
}
$selectedServiceId = (string) old_input('service_id', $booking['service']['id']);
$serviceSearchOptions = array_map(static function (array $service): array {
    $searchLabelParts = [
        $service['name'],
        (string) $service['duration'] . ' min',
    ];
    if (empty($service['active'])) {
        $searchLabelParts[] = 'inactive';
    }

    return [
        'id' => $service['id'],
        'label' => implode(' · ', $searchLabelParts),
    ];
}, $serviceOptions);
$selectedServiceLabel = '';
foreach ($serviceSearchOptions as $serviceSearchOption) {
    if ($serviceSearchOption['id'] === $selectedServiceId) {
        $selectedServiceLabel = $serviceSearchOption['label'];
        break;
    }
}
$selectedStaffId = (string) old_input('staff_id', $booking['staff']['id']);
$staffSearchOptions = array_map(static function (array $staff): array {
    return [
        'id' => $staff['id'],
        'label' => $staff['name'],
    ];
}, $options['staff']);
$selectedStaffLabel = '';
foreach ($staffSearchOptions as $staffSearchOption) {
    if ($staffSearchOption['id'] === $selectedStaffId) {
        $selectedStaffLabel = $staffSearchOption['label'];
        break;
    }
}
$channelSearchOptions = array_map(static function (string $method): array {
    return [
        'id' => $method,
        'label' => Payment::methodLabel($method),
    ];
}, Payment::methods());
$selectedChannel = (string) old_input('channel', $booking['channel']);
if (!in_array($selectedChannel, Payment::methods(), true)) {
    $selectedChannel = '';
}
$selectedChannelLabel = '';
foreach ($channelSearchOptions as $channelSearchOption) {
    if ($channelSearchOption['id'] === $selectedChannel) {
        $selectedChannelLabel = $channelSearchOption['label'];
        break;
    }
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

                <?php if (isset($errors['booking'])): ?>
                    <div class="notice-banner notice-banner-danger"><?= e($errors['booking']) ?></div>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/booking-save.php">
                    <input type="hidden" name="id" value="<?= e($booking['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Customer</span>
                            <input id="booking-customer-id" type="hidden" name="customer_id" value="<?= e($selectedCustomerId) ?>">
                            <input
                                id="booking-customer-search"
                                type="text"
                                list="booking-customer-options"
                                value="<?= e($selectedCustomerLabel) ?>"
                                placeholder="Search customer by name"
                                autocomplete="off"
                                required
                                data-searchable-select-input
                                data-searchable-select-target="booking-customer-id"
                                data-searchable-select-empty-message="Select a customer from the list."
                            >
                            <datalist id="booking-customer-options">
                                <?php foreach ($customerSearchOptions as $customer): ?>
                                    <option value="<?= e($customer['label']) ?>" data-searchable-select-id="<?= e($customer['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['customer_id'])): ?><small><?= e($errors['customer_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Service</span>
                            <input id="booking-service-id" type="hidden" name="service_id" value="<?= e($selectedServiceId) ?>">
                            <input
                                id="booking-service-search"
                                type="text"
                                list="booking-service-options"
                                value="<?= e($selectedServiceLabel) ?>"
                                placeholder="Search service by name"
                                autocomplete="off"
                                required
                                data-searchable-select-input
                                data-searchable-select-target="booking-service-id"
                                data-searchable-select-empty-message="Select a service from the list."
                            >
                            <datalist id="booking-service-options">
                                <?php foreach ($serviceSearchOptions as $service): ?>
                                    <option value="<?= e($service['label']) ?>" data-searchable-select-id="<?= e($service['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['service_id'])): ?><small><?= e($errors['service_id']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Therapist</span>
                            <input id="booking-staff-id" type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">
                            <input
                                id="booking-staff-search"
                                type="text"
                                list="booking-staff-options"
                                value="<?= e($selectedStaffLabel) ?>"
                                placeholder="Search therapist by name"
                                autocomplete="off"
                                required
                                data-searchable-select-input
                                data-searchable-select-target="booking-staff-id"
                                data-searchable-select-empty-message="Select a therapist from the list."
                            >
                            <datalist id="booking-staff-options">
                                <?php foreach ($staffSearchOptions as $staff): ?>
                                    <option value="<?= e($staff['label']) ?>" data-searchable-select-id="<?= e($staff['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
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
                            <span>Payment channel</span>
                            <input id="booking-channel-id" type="hidden" name="channel" value="<?= e($selectedChannel) ?>">
                            <input
                                id="booking-channel-search"
                                type="text"
                                list="booking-channel-options"
                                value="<?= e($selectedChannelLabel) ?>"
                                placeholder="Search payment channel"
                                autocomplete="off"
                                required
                                data-searchable-select-input
                                data-searchable-select-target="booking-channel-id"
                                data-searchable-select-empty-message="Select a payment channel from the list."
                            >
                            <datalist id="booking-channel-options">
                                <?php foreach ($channelSearchOptions as $channelOption): ?>
                                    <option value="<?= e($channelOption['label']) ?>" data-searchable-select-id="<?= e($channelOption['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['channel'])): ?><small><?= e($errors['channel']) ?></small><?php endif; ?>
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
