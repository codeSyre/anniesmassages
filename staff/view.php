<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';
require_once __DIR__ . '/../models/Staff.php';

$currentUser = require_login();
require_permission('staff.view');

$staffId = (string) ($_GET['id'] ?? '');
$member = $staffId !== '' ? Staff::find($staffId) : null;

if ($member === null) {
    redirect_to('/staff/list.php');
}

$recentBookings = array_slice(Staff::bookings($member['id']), 0, 4);
$leaveBlocks = Scheduling::leaveBlocksForStaff($member['id']);
$address = trim(implode(', ', array_filter([
    $member['address_line_1'],
    $member['address_line_2'],
    $member['city_town'],
    $member['country'],
])));
$flashMessage = flash_get('staff_success');
$errors = flash_get('staff_errors', []);

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

        <?php if (isset($errors['staff'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['staff']) ?></div>
        <?php endif; ?>

        <?php if (($member['status'] ?? '') === 'on_leave'): ?>
            <div class="notice-banner notice-banner-warning">
                <?= e($member['name']) ?> is currently on leave<?= $leaveBlocks !== [] ? ': ' . e(implode(', ', array_map(static fn (array $block): string => date('D, j M Y', strtotime((string) ($block['date'] ?? ''))), $leaveBlocks))) : '.' ?>
            </div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Therapist profile</p>
                <h1 class="hero-title"><?= e($member['name']) ?></h1>
                <p class="hero-copy"><?= e($member['bio']) ?> This therapist is currently <?= e(str_replace('_', ' ', $member['status'])) ?> and specializes in <?= e($member['specialty']) ?>.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/staff/calendar.php?id=<?= e($member['id']) ?>">View calendar</a>
                    <a class="action-link is-secondary" href="/staff/earnings.php?id=<?= e($member['id']) ?>">Earnings summary</a>
                    <?php if (($member['status'] ?? '') !== 'suspended'): ?>
                        <form
                            class="hero-action-form"
                            method="post"
                            action="/process/staff-save.php"
                            data-confirm-dialog-form
                            data-confirm-title="Suspend staff member?"
                            data-confirm-message="Suspend <?= e($member['name']) ?>? They will no longer be able to sign in until reactivated."
                            data-confirm-submit-label="Suspend member"
                        >
                            <input type="hidden" name="action" value="suspend_staff">
                            <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                            <button class="button-danger" type="submit">Suspend</button>
                        </form>
                    <?php endif; ?>
                    <?php if (($member['can_delete'] ?? false) === true): ?>
                        <form
                            class="hero-action-form"
                            method="post"
                            action="/process/staff-save.php"
                            data-confirm-dialog-form
                            data-confirm-title="Delete staff member?"
                            data-confirm-message="Delete <?= e($member['name']) ?> permanently? This action cannot be undone."
                            data-confirm-submit-label="Delete member"
                        >
                            <input type="hidden" name="action" value="delete_staff">
                            <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                            <button class="button-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
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
                    <?php if (($member['status'] ?? '') === 'on_leave'): ?>
                        <div><span>Leave days</span><strong><?= e((string) count($leaveBlocks)) ?> day<?= count($leaveBlocks) === 1 ? '' : 's' ?> scheduled</strong><small><?= $leaveBlocks !== [] ? e(date('D, j M Y', strtotime((string) ($leaveBlocks[0]['date'] ?? '')))) : 'No leave blocks recorded' ?></small></div>
                    <?php endif; ?>
                    <div><span>Compensation</span><strong><?= e(ucfirst($member['salary_structure'])) ?></strong><small><?= e((string) $member['commission_rate']) ?>% commission · <?= e(format_money((float) $member['fixed_pay'])) ?> fixed</small></div>
                    <div><span>Profile picture path</span><strong><?= e($member['profile_picture_path'] !== '' ? $member['profile_picture_path'] : 'Not provided') ?></strong><small>Upload integration placeholder</small></div>
                </div>

                <?php if (($member['status'] ?? '') === 'on_leave' && $leaveBlocks !== []): ?>
                    <div class="tag-row">
                        <?php foreach ($leaveBlocks as $block): ?>
                            <span class="tag-pill"><?= e(date('D, j M Y', strtotime((string) ($block['date'] ?? '')))) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
