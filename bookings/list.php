<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
require_permission('bookings.view');

$filters = [
    'status' => (string) ($_GET['status'] ?? 'all'),
    'search' => (string) ($_GET['search'] ?? ''),
];

$bookings = Booking::all($filters);
$stats = Booking::stats();
$flashMessage = flash_get('booking_success');

$pageTitle = 'Bookings';
$pageEyebrow = 'Appointment lifecycle management';
$currentRoute = 'bookings';
$topbarAction = ['label' => 'New booking', 'href' => '/bookings/create.php'];

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
                <p class="hero-eyebrow">Bookings at the center</p>
                <h1 class="hero-title">Control every appointment from intake to completion.</h1>
                <p class="hero-copy">Use this module to create appointments, assign therapists, track balances, and keep the day clear of scheduling conflicts.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/bookings/create.php">Create booking</a>
                    <a class="action-link is-secondary" href="/bookings/calendar.php">Open calendar</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="filter-panel">
            <div class="filter-panel-row">
                <div class="filter-chip-row">
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/bookings/list.php">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'pending')) ?>" href="/bookings/list.php?status=pending">Pending</a>
                    <a class="<?= e(active_filter($filters['status'], 'confirmed')) ?>" href="/bookings/list.php?status=confirmed">Confirmed</a>
                    <a class="<?= e(active_filter($filters['status'], 'completed')) ?>" href="/bookings/list.php?status=completed">Completed</a>
                    <a class="<?= e(active_filter($filters['status'], 'cancelled')) ?>" href="/bookings/list.php?status=cancelled">Cancelled</a>
                </div>

                <form class="inline-search" method="get" action="/bookings/list.php">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search booking, service, staff, or guest">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Booking list</p>
                    <h3>Active appointment queue</h3>
                </div>
                <p>Every booking is linked to the guest, service, therapist, schedule slot, and payment state.</p>
            </div>

            <?php if ($bookings === []): ?>
                <div class="empty-state">
                    <strong>No bookings match the current filter.</strong>
                    <p>Try clearing the search or switch status filters to widen the queue.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Guest</th>
                            <th>Service</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= e($booking['reference']) ?></strong>
                                    <span><?= e($booking['channel']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($booking['customer']['name']) ?></strong>
                                    <span><?= e($booking['customer']['phone']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($booking['service']['name']) ?></strong>
                                    <span><?= e((string) $booking['duration']) ?> min with <?= e($booking['staff']['name']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(date('D, j M', strtotime($booking['date']))) ?></strong>
                                    <span><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?></span>
                                </td>
                                <td><span class="<?= e(status_badge_class($booking['status'])) ?>"><?= e(str_replace('_', ' ', ucfirst($booking['status']))) ?></span></td>
                                <td>
                                    <strong><?= e(format_money((float) $booking['amount_total'])) ?></strong>
                                    <span><?= e(ucfirst($booking['payment_status'])) ?> · Balance <?= e(format_money((float) $booking['balance'])) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/bookings/view.php?id=<?= e($booking['id']) ?>">View</a>
                                    <a href="/bookings/edit.php?id=<?= e($booking['id']) ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
