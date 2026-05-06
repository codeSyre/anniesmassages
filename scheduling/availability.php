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
$leaveBlocks = $selectedStaffId !== '' ? Scheduling::leaveBlocksForStaff($selectedStaffId) : [];
$selectedStaff = null;
$staffSearchOptions = array_map(static function (array $item): array {
    return [
        'id' => (string) ($item['staff']['id'] ?? ''),
        'label' => trim((string) ($item['staff']['name'] ?? 'Therapist'))
            . (
                trim((string) ($item['staff']['specialty'] ?? '')) !== ''
                    ? ' · ' . trim((string) ($item['staff']['specialty'] ?? ''))
                    : ''
            ),
    ];
}, $overview);
$selectedStaffLabel = '';
foreach ($overview as $index => $item) {
    if (($item['staff']['id'] ?? '') === $selectedStaffId) {
        $selectedStaff = $item['staff'];
        $selectedStaffLabel = $staffSearchOptions[$index]['label'] ?? '';
        break;
    }
}
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

        <?php if (isset($errors['staff'])): ?>
            <div class="notice-banner notice-banner-warning"><?= e($errors['staff']) ?></div>
        <?php endif; ?>

        <section class="availability-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Team availability</p>
                        <h3>Weekly working windows</h3>
                    <p>Pick a therapist, choose the weekdays to update, and set the hours they should be bookable.</p>
                    </div>
                </div>

                <form method="get" action="/scheduling/availability.php">
                    <label class="field">
                        <span>Select therapist</span>
                        <input id="availability-staff-id" type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">
                        <input
                            id="availability-staff-search"
                            type="text"
                            list="availability-staff-options"
                            value="<?= e($selectedStaffLabel) ?>"
                            placeholder="Search therapist by name"
                            autocomplete="off"
                            required
                            data-searchable-select-input
                            data-searchable-select-target="availability-staff-id"
                            data-searchable-select-empty-message="Select a therapist from the list."
                            data-searchable-select-submit="true"
                        >
                        <datalist id="availability-staff-options">
                            <?php foreach ($staffSearchOptions as $staff): ?>
                                <option value="<?= e($staff['label']) ?>" data-searchable-select-id="<?= e($staff['id']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>
                </form>

                <?php if ($selectedProfile !== null): ?>
                    <?php if (($selectedStaff['status'] ?? '') === 'on_leave'): ?>
                        <div class="notice-banner notice-banner-warning">This therapist is currently on leave. Weekly hours remain saved, but they should not be treated as bookable until they return.</div>
                    <?php endif; ?>

                    <?php if ($selectedStaff !== null): ?>
                        <?php if (($selectedStaff['status'] ?? '') === 'on_leave'): ?>
                            <div class="section-head leave-planner-head">
                                <div>
                                    <p class="section-kicker">Return to Schedule</p>
                                    <h3>Bring therapist back from leave</h3>
                                    <p>Restore this therapist to active scheduling and clear their current and upcoming leave blocks so they can be booked again.</p>
                                </div>
                            </div>

                            <form class="module-form" method="post" action="/process/availability-save.php">
                                <input type="hidden" name="form_type" value="return_from_leave">
                                <input type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">

                                <div class="button-row">
                                    <button class="button-primary" type="submit">Return From Leave</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="section-head leave-planner-head">
                                <div>
                                    <p class="section-kicker">Leave planner</p>
                                    <h3>Block a leave period</h3>
                                    <p>Use this for scheduled leave. It creates full-day blocked periods for the selected therapist while keeping their normal weekly hours on file.</p>
                                </div>
                            </div>

                            <form class="module-form" method="post" action="/process/availability-save.php">
                                <input type="hidden" name="form_type" value="leave_period">
                                <input type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Leave start</span>
                                        <input type="date" name="start_date" value="<?= e((string) old_input('start_date', date('Y-m-d'))) ?>">
                                        <?php if (isset($errors['start_date'])): ?><small><?= e($errors['start_date']) ?></small><?php endif; ?>
                                    </label>

                                    <label class="field">
                                        <span>Leave end</span>
                                        <input type="date" name="end_date" value="<?= e((string) old_input('end_date', date('Y-m-d'))) ?>">
                                        <?php if (isset($errors['end_date'])): ?><small><?= e($errors['end_date']) ?></small><?php endif; ?>
                                    </label>
                                </div>

                                <label class="field">
                                    <span>Leave note</span>
                                    <input type="text" name="leave_note" value="<?= e((string) old_input('leave_note')) ?>" placeholder="Optional context, e.g. annual leave or medical leave">
                                </label>

                                <div class="button-row">
                                    <button class="button-warning" type="submit">Block Leave Period</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

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
                            <p>
                                <?php if ((($selectedDay['staff']['status'] ?? '') === 'on_leave')): ?>
                                    Currently on leave
                                <?php else: ?>
                                    <?= (bool) $selectedDay['window']['enabled'] ? e($selectedDay['window']['start'] . ' - ' . $selectedDay['window']['end']) : 'Unavailable all day' ?>
                                <?php endif; ?>
                            </p>
                        </article>
                        <article class="info-item">
                            <strong><?= e((string) count($selectedDay['bookings'])) ?> scheduled bookings</strong>
                            <p><?= e((string) count($selectedDay['blocked'])) ?> blocked windows affecting this day.</p>
                        </article>
                        <article class="info-item">
                            <strong><?= e((string) count($selectedDay['open_slots'])) ?> open starting slots</strong>
                            <p>Computed from current hours, existing bookings, and blocked periods.</p>
                        </article>
                        <article class="info-item">
                            <strong><?= e((string) count($leaveBlocks)) ?> leave block<?= count($leaveBlocks) === 1 ? '' : 's' ?> on record</strong>
                            <p><?= $leaveBlocks !== [] ? e(date('D, j M', strtotime($leaveBlocks[0]['date'])) . ' · ' . $leaveBlocks[0]['reason']) : 'No leave periods have been scheduled yet.' ?></p>
                        </article>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
