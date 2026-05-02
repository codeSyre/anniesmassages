<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('staff.view');

$staffId = (string) ($_GET['id'] ?? '');
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
$member = $staffId !== '' ? Staff::find($staffId) : null;

if ($member === null) {
    redirect_to('/staff/list.php');
}

$availability = Scheduling::staffAvailability($member['id'], $date);

$pageTitle = 'Staff Calendar';
$pageEyebrow = $member['name'];
$currentRoute = 'staff';
$topbarAction = ['label' => 'Manage availability', 'href' => '/scheduling/availability.php?staff_id=' . urlencode($member['id'])];

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
                        <p class="section-kicker">Daily schedule</p>
                        <h3><?= e(date('l, j F Y', strtotime($availability['date']))) ?></h3>
                    </div>
                    <p>This view shows the therapist’s working window, assigned bookings, manual blocks, and open starting slots for the selected day.</p>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Working window</strong>
                        <p><?= (bool) $availability['window']['enabled'] ? e($availability['window']['start'] . ' - ' . $availability['window']['end']) : 'Unavailable all day' ?></p>
                    </article>
                    <article class="info-item">
                        <strong>Assigned bookings</strong>
                        <p><?= e((string) count($availability['bookings'])) ?> bookings on the calendar.</p>
                    </article>
                </div>

                <div class="schedule-column">
                    <?php foreach ($availability['bookings'] as $booking): ?>
                        <article class="schedule-column-item">
                            <strong><?= e($booking['time']) ?> - <?= e($booking['end_time']) ?></strong>
                            <p><?= e($booking['customer']['name']) ?> · <?= e($booking['service']['name']) ?></p>
                            <span class="<?= e(status_badge_class($booking['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Open capacity</p>
                        <h3><?= e((string) count($availability['open_slots'])) ?> open slots</h3>
                    </div>
                </div>

                <div class="sample-slot-list">
                    <?php foreach (array_slice($availability['open_slots'], 0, 6) as $slot): ?>
                        <div class="sample-slot-item">
                            <strong><?= e($slot['start']) ?> - <?= e($slot['end']) ?></strong>
                            <span><?= e($slot['service']['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-head section-head-compact">
                    <div>
                        <p class="section-kicker">Blocked periods</p>
                    </div>
                </div>
                <div class="info-list">
                    <?php foreach ($availability['blocked'] as $block): ?>
                        <article class="info-item">
                            <strong><?= e($block['start']) ?> - <?= e($block['end']) ?></strong>
                            <p><?= e($block['reason']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
