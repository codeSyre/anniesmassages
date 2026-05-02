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

$recentBookings = array_slice(Customer::bookings($customer['id']), 0, 4);
$paymentHistory = array_slice(Customer::paymentHistory($customer['id']), 0, 4);
$flashMessage = flash_get('customer_success');

$pageTitle = 'Customer Profile';
$pageEyebrow = 'Customer management';
$currentRoute = 'customers';
$topbarAction = ['label' => 'Edit customer', 'href' => '/customers/edit.php?id=' . urlencode($customer['id'])];

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
                <p class="hero-eyebrow">Guest profile</p>
                <h1 class="hero-title"><?= e($customer['name']) ?></h1>
                <p class="hero-copy"><?= e($customer['preference'] !== '' ? $customer['preference'] : 'No preference saved yet.') ?> Keep this profile current so future bookings carry the right context from the first click.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/customers/history.php?id=<?= e($customer['id']) ?>">Booking history</a>
                    <a class="action-link is-secondary" href="/customers/notes.php?id=<?= e($customer['id']) ?>">Notes & preferences</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="badge badge-info">Bookings</span>
                    <strong><?= e((string) $customer['booking_count']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Total spend</span>
                    <strong><?= e(format_money((float) $customer['total_spent'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Next visit</span>
                    <strong><?= e($customer['next_visit'] !== null ? date('j M', strtotime($customer['next_visit'])) : 'None') ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Profile details</p>
                        <h3>Contact and context</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Phone</span><strong><?= e($customer['phone']) ?></strong><small><?= e($customer['source']) ?> source</small></div>
                    <div><span>Email</span><strong><?= e($customer['email'] !== '' ? $customer['email'] : 'No email recorded') ?></strong><small><?= e($customer['location']) ?></small></div>
                    <div><span>Preference</span><strong><?= e($customer['preference'] !== '' ? $customer['preference'] : 'No preference recorded') ?></strong><small>Used during booking intake</small></div>
                    <div><span>Admin notes</span><strong><?= e($customer['admin_notes'] !== '' ? $customer['admin_notes'] : 'No internal notes yet') ?></strong><small>Private to admin staff</small></div>
                </div>

                <div class="tag-row">
                    <?php foreach ($customer['tags'] as $tag): ?>
                        <span class="tag-pill"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Recent bookings</p>
                        <h3>Service flow</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($recentBookings as $booking): ?>
                        <article class="info-item">
                            <strong><?= e($booking['service']['name']) ?></strong>
                            <p><?= e(date('D, j M Y', strtotime($booking['date']))) ?> · <?= e($booking['time']) ?> with <?= e($booking['staff']['name']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="activity-card activity-card-standalone">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Payment history snapshot</p>
                    <h3>Recent payment-linked visits</h3>
                </div>
                <p>Payments are derived from the bookings module until the dedicated ledger module is fully wired to the database.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <tr>
                            <td><strong><?= e($payment['reference']) ?></strong></td>
                            <td><strong><?= e(date('j M Y', strtotime($payment['date']))) ?></strong></td>
                            <td><strong><?= e($payment['service']) ?></strong></td>
                            <td><strong><?= e(format_money((float) $payment['paid'])) ?></strong></td>
                            <td><strong><?= e(format_money((float) $payment['balance'])) ?></strong></td>
                            <td><span class="<?= e(status_badge_class($payment['status'])) ?>"><?= e(ucfirst($payment['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
