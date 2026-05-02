<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
require_permission('bookings.view');

$days = Booking::calendarDays();

$pageTitle = 'Booking Calendar';
$pageEyebrow = 'Availability view';
$currentRoute = 'bookings';
$topbarAction = ['label' => 'New booking', 'href' => '/bookings/create.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Calendar board</p>
                    <h3>Day-by-day booking load</h3>
                </div>
                <p>Use the timeline below to spot busy windows, open capacity, and appointments that still need confirmation.</p>
            </div>

            <div class="calendar-board">
                <?php foreach ($days as $date => $events): ?>
                    <article class="calendar-day">
                        <header>
                            <p><?= e(date('D', strtotime($date))) ?></p>
                            <h4><?= e(date('j M', strtotime($date))) ?></h4>
                        </header>

                        <div class="calendar-events">
                            <?php foreach ($events as $event): ?>
                                <a class="calendar-event" href="/bookings/view.php?id=<?= e($event['id']) ?>">
                                    <strong><?= e($event['time']) ?> · <?= e($event['service']['name']) ?></strong>
                                    <span><?= e($event['customer']['name']) ?> with <?= e($event['staff']['name']) ?></span>
                                    <em class="<?= e(status_badge_class($event['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $event['status']))) ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
