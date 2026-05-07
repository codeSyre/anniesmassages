<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('bookings.view');

$weekStart = (string) ($_GET['week'] ?? 'monday this week');
$matrix = Scheduling::weekMatrix($weekStart);
$hasCalendarEntries = (int) ($matrix['stats']['bookings'] ?? 0) > 0
    || (int) ($matrix['stats']['blocked_periods'] ?? 0) > 0;

$pageTitle = 'Scheduling Calendar';
$pageEyebrow = 'Availability engine';
$currentRoute = 'calendar';

if ($hasCalendarEntries) {
    $topbarActions = [
        ['label' => 'Create booking', 'href' => '/bookings/create.php', 'permission' => 'bookings.create'],
        ['label' => 'Manage availability', 'href' => '/scheduling/availability.php', 'permission' => 'bookings.view'],
        ['label' => 'Manage blocked slots', 'href' => '/scheduling/blocked.php', 'permission' => 'bookings.view'],
    ];
} else {
    $topbarAction = ['label' => 'Manage availability', 'href' => '/scheduling/availability.php'];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero<?= $hasCalendarEntries ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasCalendarEntries): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Week view</p>
                    <h1 class="hero-title">See therapist load, open capacity, and blocked periods at a glance.</h1>
                    <p class="hero-copy">This board is the operational spine of the booking engine. It shows where demand is landing and where the schedule still has room for new appointments.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/bookings/create.php">Create booking</a>
                        <a class="action-link is-secondary" href="/scheduling/blocked.php">Manage blocked slots</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasCalendarEntries ? ' module-stat-grid-quad' : '' ?>">
                <article class="mini-stat-card">
                    <span class="badge badge-info">Week bookings</span>
                    <strong><?= e((string) $matrix['stats']['bookings']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Open slots</span>
                    <strong><?= e((string) $matrix['stats']['open_slots']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Blocked periods</span>
                    <strong><?= e((string) $matrix['stats']['blocked_periods']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-danger">Busy therapists</span>
                    <strong><?= e((string) $matrix['stats']['busy_staff']) ?></strong>
                </article>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Weekly schedule matrix</p>
                    <h3>Therapist capacity board</h3>
                </div>
                <p>Each cell shows confirmed workload, remaining slot potential, and any manual availability blocks affecting that day.</p>
            </div>

            <div class="schedule-grid">
                <div class="schedule-grid-header schedule-grid-staff">Therapist</div>
                <?php foreach ($matrix['days'] as $day): ?>
                    <div class="schedule-grid-header">
                        <strong><?= e($day['label']) ?></strong>
                        <span><?= e($day['display']) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($matrix['rows'] as $row): ?>
                    <div class="schedule-grid-staff">
                        <strong><?= e($row['staff']['name']) ?></strong>
                        <span><?= e($row['staff']['specialty']) ?></span>
                    </div>

                    <?php foreach ($row['cells'] as $cell): ?>
                        <div class="schedule-cell<?= !empty($cell['is_leave_day']) ? ' schedule-cell-on-leave' : '' ?>">
                            <div class="schedule-cell-stats">
                                <span><?= e((string) $cell['bookings_count']) ?> bookings</span>
                                <span><?= e((string) $cell['open_slots']) ?> open</span>
                            </div>
                            <div class="schedule-cell-stats">
                                <span><?= e((string) $cell['blocked_count']) ?> blocks</span>
                                <span><?= e((string) $cell['occupied_minutes']) ?> min</span>
                            </div>
                            <?php if (($cell['booking_entries'] ?? []) !== []): ?>
                                <div class="schedule-tags">
                                    <?php foreach ($cell['booking_entries'] as $entry): ?>
                                        <?php
                                        $entryStatusLabel = ucfirst(str_replace('_', ' ', (string) ($entry['status'] ?? 'pending')));
                                        $entryTitle = !empty($entry['is_overdue_pending'])
                                            ? 'Past pending booking. Check whether the session happened. If it did, update it to completed. If it did not go ahead, cancel it. If the guest still wants the session, reschedule it to a new date.'
                                            : $entryStatusLabel;
                                        ?>
                                        <a
                                            class="schedule-tag schedule-tag-link <?= e(status_badge_class((string) ($entry['status'] ?? 'pending'))) ?><?= !empty($entry['is_overdue_pending']) ? ' schedule-tag-overdue' : '' ?>"
                                            href="/bookings/view.php?id=<?= e((string) ($entry['id'] ?? '')) ?>"
                                            title="<?= e($entryTitle) ?>"
                                        >
                                            <?= e((string) ($entry['label'] ?? 'Booking')) ?>
                                            <?php if (!empty($entry['is_overdue_pending'])): ?>
                                                <span class="schedule-tag-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 8.25v4.5" />
                                                        <path d="M12 16.5h.01" />
                                                        <path d="M10.29 4.86 2.82 18a2 2 0 0 0 1.74 3h14.88a2 2 0 0 0 1.74-3L13.71 4.86a2 2 0 0 0-3.42 0Z" />
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif (!empty($cell['is_leave_day'])): ?>
                                <p class="schedule-empty schedule-empty-warning"><?= e((string) ($cell['leave_label'] ?? 'On leave')) ?></p>
                            <?php else: ?>
                                <p class="schedule-empty">Clear runway for new bookings.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
