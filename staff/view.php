<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

$currentUser = require_login();
require_permission('staff.view');

$staffId = (string) ($_GET['id'] ?? '');
$member = $staffId !== '' ? Staff::find($staffId) : null;

if ($member === null) {
    redirect_to('/staff/list.php');
}

$recentBookings = array_slice(Staff::bookings($member['id']), 0, 4);
$address = trim(implode(', ', array_filter([
    $member['address_line_1'],
    $member['address_line_2'],
    $member['city_town'],
    $member['country'],
])));
$flashMessage = flash_get('staff_success');

$pageTitle = 'Staff Profile';
$pageEyebrow = 'Therapist management';
$currentRoute = 'staff';
$topbarAction = ['label' => 'Edit profile', 'href' => '/staff/edit.php?id=' . urlencode($member['id'])];

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
                <p class="hero-eyebrow">Therapist profile</p>
                <h1 class="hero-title"><?= e($member['name']) ?></h1>
                <p class="hero-copy"><?= e($member['bio']) ?> This therapist is currently <?= e(str_replace('_', ' ', $member['status'])) ?> and specializes in <?= e($member['specialty']) ?>.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/staff/calendar.php?id=<?= e($member['id']) ?>">View calendar</a>
                    <a class="action-link is-secondary" href="/staff/earnings.php?id=<?= e($member['id']) ?>">Earnings summary</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($member['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $member['status']))) ?></span>
                    <strong><?= e($member['today_window']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Today</span>
                    <strong><?= e((string) $member['today_booking_count']) ?> bookings</strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Completed value</span>
                    <strong><?= e(format_money((float) $member['completed_value'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Profile details</p>
                        <h3>Role, contact, and structure</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Role / type</span><strong><?= e(ucfirst($member['role_type'])) ?></strong><small><?= e($member['specialty']) ?></small></div>
                    <div><span>Contact</span><strong><?= e($member['phone']) ?></strong><small><?= e($member['email']) ?></small></div>
                    <div><span>Address</span><strong><?= e($address !== '' ? $address : 'Not provided') ?></strong><small>Staff location context</small></div>
                    <div><span>Capacity</span><strong><?= e($member['capacity']) ?></strong><small><?= e((string) $member['enabled_days']) ?> active days</small></div>
                    <div><span>Compensation</span><strong><?= e(ucfirst($member['salary_structure'])) ?></strong><small><?= e((string) $member['commission_rate']) ?>% commission · <?= e(format_money((float) $member['fixed_pay'])) ?> fixed</small></div>
                    <div><span>Profile picture path</span><strong><?= e($member['profile_picture_path'] !== '' ? $member['profile_picture_path'] : 'Not provided') ?></strong><small>Upload integration placeholder</small></div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Recent work</p>
                        <h3>Latest bookings</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($recentBookings as $booking): ?>
                        <article class="info-item">
                            <strong><?= e($booking['customer']['name']) ?></strong>
                            <p><?= e($booking['service']['name']) ?> · <?= e(date('D, j M Y', strtotime($booking['date']))) ?> at <?= e($booking['time']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
