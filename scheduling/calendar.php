<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('bookings.view');

$weekStart = (string) ($_GET['week'] ?? 'monday this week');
$matrix = Scheduling::weekMatrix($weekStart);

$pageTitle = 'Scheduling Calendar';
$pageEyebrow = 'Availability engine';
$currentRoute = 'calendar';
$topbarAction = ['label' => 'Manage availability', 'href' => '/scheduling/availability.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Week view</p>
                <h1 class="hero-title">See therapist load, open capacity, and blocked periods at a glance.</h1>
                <p class="hero-copy">This board is the operational spine of the booking engine. It shows where demand is landing and where the schedule still has room for new appointments.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/bookings/create.php">Create booking</a>
                    <a class="action-link is-secondary" href="/scheduling/blocked.php">Manage blocked slots</a>
                </div>
            </article>

            <aside class="module-stat-grid">
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
                        <div class="schedule-cell">
                            <div class="schedule-cell-stats">
                                <span><?= e((string) $cell['bookings_count']) ?> bookings</span>
                                <span><?= e((string) $cell['open_slots']) ?> open</span>
                            </div>
                            <div class="schedule-cell-stats">
                                <span><?= e((string) $cell['blocked_count']) ?> blocks</span>
                                <span><?= e((string) $cell['occupied_minutes']) ?> min</span>
                            </div>
                            <?php if ($cell['labels'] !== []): ?>
                                <div class="schedule-tags">
                                    <?php foreach ($cell['labels'] as $label): ?>
                                        <span class="schedule-tag"><?= e($label) ?></span>
                                    <?php endforeach; ?>
                                </div>
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
