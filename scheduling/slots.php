<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('bookings.update');

$settings = Scheduling::slotSettings();
$services = Service::all();
$sampleSlots = Scheduling::availableSlots($services[0]['id'], date('Y-m-d'));
$errors = flash_get('scheduling_errors', []);
$flashMessage = flash_get('scheduling_success');

$pageTitle = 'Time Slot Settings';
$pageEyebrow = 'Scheduling engine';
$currentRoute = 'calendar';
$topbarAction = ['label' => 'Blocked slots', 'href' => '/scheduling/blocked.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Slot rules</p>
                        <h3>Engine settings</h3>
                    </div>
                    <p>These settings control how the calendar generates slot starts, how far ahead same-day bookings can be placed, and how room capacity is interpreted.</p>
                </div>

                <form class="module-form" method="post" action="/process/availability-save.php">
                    <input type="hidden" name="form_type" value="slot_settings">

                    <div class="form-grid">
                        <label class="field">
                            <span>Day start</span>
                            <input type="time" name="day_start" value="<?= e((string) old_input('day_start', $settings['day_start'])) ?>">
                            <?php if (isset($errors['day_start'])): ?><small><?= e($errors['day_start']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Day end</span>
                            <input type="time" name="day_end" value="<?= e((string) old_input('day_end', $settings['day_end'])) ?>">
                            <?php if (isset($errors['day_end'])): ?><small><?= e($errors['day_end']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Slot interval</span>
                            <input type="number" min="5" step="5" name="slot_interval" value="<?= e((string) old_input('slot_interval', (string) $settings['slot_interval'])) ?>">
                            <?php if (isset($errors['slot_interval'])): ?><small><?= e($errors['slot_interval']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Default duration</span>
                            <input type="number" min="15" step="15" name="default_duration" value="<?= e((string) old_input('default_duration', (string) $settings['default_duration'])) ?>">
                            <?php if (isset($errors['default_duration'])): ?><small><?= e($errors['default_duration']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Buffer minutes</span>
                            <input type="number" min="0" step="5" name="buffer_minutes" value="<?= e((string) old_input('buffer_minutes', (string) $settings['buffer_minutes'])) ?>">
                            <?php if (isset($errors['buffer_minutes'])): ?><small><?= e($errors['buffer_minutes']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Same-day lead time</span>
                            <input type="number" min="0" step="15" name="same_day_lead_minutes" value="<?= e((string) old_input('same_day_lead_minutes', (string) $settings['same_day_lead_minutes'])) ?>">
                            <?php if (isset($errors['same_day_lead_minutes'])): ?><small><?= e($errors['same_day_lead_minutes']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Parallel rooms</span>
                            <input type="number" min="1" step="1" name="max_parallel_rooms" value="<?= e((string) old_input('max_parallel_rooms', (string) $settings['max_parallel_rooms'])) ?>">
                            <?php if (isset($errors['max_parallel_rooms'])): ?><small><?= e($errors['max_parallel_rooms']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Save slot rules</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Service duration rules</p>
                        <h3>Configured treatments</h3>
                    </div>
                </div>

                <div class="service-rule-list">
                    <?php foreach ($services as $service): ?>
                        <article class="service-rule-card">
                            <strong><?= e($service['name']) ?></strong>
                            <span><?= e((string) $service['duration']) ?> min · buffer <?= e((string) $service['buffer']) ?> min</span>
                            <small><?= e($service['room']) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="sample-slot-list">
                    <p class="section-kicker">Sample open slots</p>
                    <?php foreach (array_slice($sampleSlots, 0, 4) as $slot): ?>
                        <div class="sample-slot-item">
                            <strong><?= e($slot['start']) ?> - <?= e($slot['end']) ?></strong>
                            <span><?= e($slot['staff']['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
