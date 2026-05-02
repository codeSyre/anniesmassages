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

$pageTitle = 'Staff / Therapists';
$pageEyebrow = 'Team management';
$currentRoute = 'staff';
$topbarAction = ['label' => 'New therapist', 'href' => '/staff/create.php'];

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
                <p class="hero-eyebrow">Therapist management</p>
                <h1 class="hero-title">Manage therapists, workload, status, and payroll links from one place.</h1>
                <p class="hero-copy">Profiles here feed bookings, scheduling, and later payroll runs. Keep specialties, availability expectations, and compensation structure accurate.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/staff/create.php">Create profile</a>
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
                            <th>Therapist</th>
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
                                <td class="row-actions">
                                    <a href="/staff/view.php?id=<?= e($member['id']) ?>">View</a>
                                    <a href="/staff/edit.php?id=<?= e($member['id']) ?>">Edit</a>
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
