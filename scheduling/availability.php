<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('bookings.update');

$overview = Scheduling::availabilityOverview();
$profiles = Scheduling::weeklyAvailabilityProfiles();
$selectedStaffId = (string) ($_GET['staff_id'] ?? ($overview[0]['staff']['id'] ?? ''));
$selectedProfile = $profiles[$selectedStaffId] ?? null;
$selectedDate = (string) ($_GET['date'] ?? date('Y-m-d'));
$selectedDay = $selectedStaffId !== '' ? Scheduling::staffAvailability($selectedStaffId, $selectedDate) : null;
$errors = flash_get('scheduling_errors', []);
$flashMessage = flash_get('scheduling_success');

$pageTitle = 'Staff Availability';
$pageEyebrow = 'Scheduling rules';
$currentRoute = 'calendar';
$topbarAction = ['label' => 'Calendar board', 'href' => '/scheduling/calendar.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="availability-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Team availability</p>
                        <h3>Weekly working windows</h3>
                    </div>
                    <p>Pick a therapist, choose the weekdays to update, and set the hours they should be bookable.</p>
                </div>

                <div class="staff-overview-list">
                    <?php foreach ($overview as $item): ?>
                        <a class="staff-overview-card <?= $selectedStaffId === $item['staff']['id'] ? 'is-active' : '' ?>" href="/scheduling/availability.php?staff_id=<?= e($item['staff']['id']) ?>">
                            <strong><?= e($item['staff']['name']) ?></strong>
                            <span><?= e($item['summary']) ?></span>
                            <small>Today: <?= e($item['today']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedProfile !== null): ?>
                    <form class="module-form availability-form" method="post" action="/process/availability-save.php">
                        <input type="hidden" name="form_type" value="availability_profile">
                        <input type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">

                        <div class="field">
                            <span>Update mode</span>
                            <select name="mode">
                                <option value="available">Mark selected days available</option>
                                <option value="unavailable">Mark selected days unavailable</option>
                            </select>
                        </div>

                        <div class="weekday-grid">
                            <?php foreach ($selectedProfile['days'] as $weekday => $window): ?>
                                <label class="weekday-card">
                                    <input type="checkbox" name="weekdays[]" value="<?= e($weekday) ?>" <?= (bool) $window['enabled'] ? 'checked' : '' ?>>
                                    <strong><?= e(ucfirst($weekday)) ?></strong>
                                    <span><?= (bool) $window['enabled'] ? e($window['start'] . ' - ' . $window['end']) : 'Unavailable' ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['weekdays'])): ?><p class="inline-error"><?= e($errors['weekdays']) ?></p><?php endif; ?>

                        <div class="form-grid">
                            <label class="field">
                                <span>Start time</span>
                                <input type="time" name="start_time" value="<?= e((string) old_input('start_time', '08:00')) ?>">
                                <?php if (isset($errors['start_time'])): ?><small><?= e($errors['start_time']) ?></small><?php endif; ?>
                            </label>

                            <label class="field">
                                <span>End time</span>
                                <input type="time" name="end_time" value="<?= e((string) old_input('end_time', '17:00')) ?>">
                                <?php if (isset($errors['end_time'])): ?><small><?= e($errors['end_time']) ?></small><?php endif; ?>
                            </label>
                        </div>

                        <div class="button-row">
                            <button class="button-primary" type="submit">Save availability</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Selected day</p>
                        <h3><?= $selectedDay !== null ? e($selectedDay['staff']['name']) : 'No staff selected' ?></h3>
                    </div>
                </div>

                <?php if ($selectedDay !== null): ?>
                    <div class="info-list">
                        <article class="info-item">
                            <strong><?= e(date('l, j F', strtotime($selectedDay['date']))) ?></strong>
                            <p><?= (bool) $selectedDay['window']['enabled'] ? e($selectedDay['window']['start'] . ' - ' . $selectedDay['window']['end']) : 'Unavailable all day' ?></p>
                        </article>
                        <article class="info-item">
                            <strong><?= e((string) count($selectedDay['bookings'])) ?> scheduled bookings</strong>
                            <p><?= e((string) count($selectedDay['blocked'])) ?> blocked windows affecting this day.</p>
                        </article>
                        <article class="info-item">
                            <strong><?= e((string) count($selectedDay['open_slots'])) ?> open starting slots</strong>
                            <p>Computed from current hours, existing bookings, and blocked periods.</p>
                        </article>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
