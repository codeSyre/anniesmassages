<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
require_permission('bookings.view');

$filters = [
    'status' => (string) ($_GET['status'] ?? 'all'),
    'search' => (string) ($_GET['search'] ?? ''),
];

$hasBookings = Booking::all() !== [];
$bookings = Booking::all($filters);
$totalBookings = count($bookings);
$perPage = 10;
$totalPages = max(1, (int) ceil($totalBookings / $perPage));
$currentPage = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$bookings = array_slice($bookings, ($currentPage - 1) * $perPage, $perPage);
$stats = Booking::stats();
$flashMessage = flash_get('booking_success');

$pageTitle = 'Bookings';
$pageEyebrow = 'Appointment lifecycle management';
$currentRoute = 'bookings';

if ($hasBookings) {
    $topbarActions = [
        ['label' => 'New booking', 'href' => '/bookings/create.php', 'permission' => 'bookings.create'],
        ['label' => 'Open calendar', 'href' => '/bookings/calendar.php', 'permission' => 'bookings.view'],
    ];
} else {
    $topbarAction = ['label' => 'New booking', 'href' => '/bookings/create.php', 'permission' => 'bookings.create'];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasBookings ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasBookings): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Bookings at the center</p>
                    <h1 class="hero-title">Control every appointment from intake to completion.</h1>
                    <p class="hero-copy">Use this module to create appointments, assign therapists, track balances, and keep the day clear of scheduling conflicts.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/bookings/create.php">Create booking</a>
                        <a class="action-link is-secondary" href="/bookings/calendar.php">Open calendar</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasBookings ? ' module-stat-grid-quad' : '' ?>">
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
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/bookings/view.php?id=<?= e($booking['id']) ?>" aria-label="View <?= e($booking['reference']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                        <a class="icon-action-button" href="/bookings/edit.php?id=<?= e($booking['id']) ?>" aria-label="Edit <?= e($booking['reference']) ?>" title="Edit">
                                            <?= action_icon_svg('edit') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a class="pagination-btn" href="/bookings/list.php?<?= e(http_build_query(array_merge($filters, ['page' => $currentPage - 1]))) ?>">Previous</a>
                    <?php else: ?>
                        <span class="pagination-btn is-disabled">Previous</span>
                    <?php endif; ?>

                    <span class="pagination-info"><?= e((string) $currentPage) ?> of <?= e((string) $totalPages) ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="pagination-btn" href="/bookings/list.php?<?= e(http_build_query(array_merge($filters, ['page' => $currentPage + 1]))) ?>">Next</a>
                    <?php else: ?>
                        <span class="pagination-btn is-disabled">Next</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
