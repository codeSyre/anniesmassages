<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

$currentUser = require_login();
require_permission('staff.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'all'),
];

$staffMembers = Staff::all($filters);
$stats = Staff::stats();
$flashMessage = flash_get('staff_success');
$errors = flash_get('staff_errors', []);

$pageTitle = 'Staff';
$pageEyebrow = 'Team management';
$currentRoute = 'staff';
$topbarAction = ['label' => 'New Staff', 'href' => '/staff/create.php', 'permission' => 'staff.create'];

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

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Staff management</p>
                <h1 class="hero-title">Manage staff members, workload, status, and payroll links from one place.</h1>
                <p class="hero-copy">Profiles here feed bookings, scheduling, and later payroll runs. Keep specialties, availability expectations, and compensation structure accurate.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/staff/create.php">Add staff</a>
                    <a class="action-link is-secondary" href="/scheduling/availability.php">Open availability</a>
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
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/staff/list.php">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'active')) ?>" href="/staff/list.php?status=active">Active</a>
                    <a class="<?= e(active_filter($filters['status'], 'inactive')) ?>" href="/staff/list.php?status=inactive">Inactive</a>
                    <a class="<?= e(active_filter($filters['status'], 'suspended')) ?>" href="/staff/list.php?status=suspended">Suspended</a>
                    <a class="<?= e(active_filter($filters['status'], 'on_leave')) ?>" href="/staff/list.php?status=on_leave">On leave</a>
                </div>

                <form class="inline-search" method="get" action="/staff/list.php">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search therapist, specialty, role, or phone">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Roster</p>
                    <h3>Current staff profiles</h3>
                </div>
                <p>Track therapist status, current load, specialization, and earnings-linked work at the roster level.</p>
            </div>

            <?php if ($staffMembers === []): ?>
                <div class="empty-state">
                    <strong>No staff profiles matched the current filter.</strong>
                    <p>Try clearing the search or create a new therapist profile.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Availability</th>
                            <th>Workload</th>
                            <th>Earnings base</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffMembers as $member): ?>
                            <tr>
                                <td>
                                    <strong><?= e($member['name']) ?></strong>
                                    <span><?= e($member['specialty']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(ucfirst($member['role_type'])) ?></strong>
                                    <span><?= e($member['phone']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($member['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $member['status']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($member['today_window']) ?></strong>
                                    <span><?= e((string) $member['enabled_days']) ?> active days this week</span>
                                </td>
                                <td>
                                    <strong><?= e((string) $member['today_booking_count']) ?> today</strong>
                                    <span><?= e((string) $member['upcoming_count']) ?> upcoming · <?= e($member['capacity']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(ucfirst($member['salary_structure'])) ?></strong>
                                    <span><?= e((string) $member['completed_count']) ?> completed · <?= e(format_money((float) $member['completed_value'])) ?></span>
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/staff/view.php?id=<?= e($member['id']) ?>" aria-label="View <?= e($member['name']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                        <a class="icon-action-button" href="/staff/edit.php?id=<?= e($member['id']) ?>" aria-label="Edit <?= e($member['name']) ?>" title="Edit">
                                            <?= action_icon_svg('edit') ?>
                                        </a>
                                        <?php if (($member['status'] ?? '') !== 'suspended'): ?>
                                            <form
                                                class="inline-action-form inline-action-form-danger"
                                                method="post"
                                                action="/process/staff-save.php"
                                                data-confirm-dialog-form
                                                data-confirm-title="Suspend staff member?"
                                                data-confirm-message="Suspend <?= e($member['name']) ?>? They will no longer be able to sign in until reactivated."
                                                data-confirm-submit-label="Suspend member"
                                            >
                                                <input type="hidden" name="action" value="suspend_staff">
                                                <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                                                <button class="icon-action-button icon-action-button-danger" type="submit" aria-label="Suspend <?= e($member['name']) ?>" title="Suspend">
                                                    <?= action_icon_svg('suspend') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (($member['can_delete'] ?? false) === true): ?>
                                            <form
                                                class="inline-action-form inline-action-form-danger"
                                                method="post"
                                                action="/process/staff-save.php"
                                                data-confirm-dialog-form
                                                data-confirm-title="Delete staff member?"
                                                data-confirm-message="Delete <?= e($member['name']) ?> permanently? This action cannot be undone."
                                                data-confirm-submit-label="Delete member"
                                            >
                                                <input type="hidden" name="action" value="delete_staff">
                                                <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                                                <button class="icon-action-button icon-action-button-danger" type="submit" aria-label="Delete <?= e($member['name']) ?>" title="Delete">
                                                    <?= action_icon_svg('delete') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
