<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Scheduling.php';

$currentUser = require_login();
require_permission('bookings.update');

$blocked = Scheduling::blockedSlots();
$staff = Staff::all();
$errors = flash_get('scheduling_errors', []);
$flashMessage = flash_get('scheduling_success');

$pageTitle = 'Blocked Dates & Times';
$pageEyebrow = 'Manual unavailability';
$currentRoute = 'calendar';
$topbarAction = ['label' => 'Slot settings', 'href' => '/scheduling/slots.php'];

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
                        <p class="section-kicker">Blocked periods</p>
                        <h3>Current manual overrides</h3>
                    </div>
                    <p>Use blocked slots for training, studio turnaround, leave, room prep, or any period that must not be offered in the booking flow.</p>
                </div>

                <div class="blocked-list">
                    <?php foreach ($blocked as $block): ?>
                        <?php $blockedStaff = $block['staff_id'] !== '' ? Staff::find($block['staff_id']) : null; ?>
                        <article class="blocked-item">
                            <div>
                                <strong><?= e(date('D, j M', strtotime($block['date']))) ?> · <?= e($block['start']) ?> - <?= e($block['end']) ?></strong>
                                <span><?= e($block['reason']) ?></span>
                            </div>
                            <em class="<?= e($block['staff_id'] === '' ? 'badge badge-info' : 'badge badge-warning') ?>">
                                <?= e($block['staff_id'] === '' ? 'Global block' : ($blockedStaff['name'] ?? 'Staff block')) ?>
                            </em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Add blocked period</p>
                        <h3>Protect a slot</h3>
                    </div>
                </div>

                <form class="module-form" method="post" action="/process/blocked-slot-save.php">
                    <label class="field">
                        <span>Date</span>
                        <input type="date" name="date" value="<?= e((string) old_input('date', date('Y-m-d'))) ?>">
                        <?php if (isset($errors['date'])): ?><small><?= e($errors['date']) ?></small><?php endif; ?>
                    </label>
                    <div class="form-grid">
                        <label class="field">
                            <span>Start time</span>
                            <input type="time" name="start_time" value="<?= e((string) old_input('start_time', '13:00')) ?>">
                            <?php if (isset($errors['start_time'])): ?><small><?= e($errors['start_time']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>End time</span>
                            <input type="time" name="end_time" value="<?= e((string) old_input('end_time', '14:00')) ?>">
                            <?php if (isset($errors['end_time'])): ?><small><?= e($errors['end_time']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <label class="field">
                        <span>Apply to therapist</span>
                        <select name="staff_id">
                            <option value="">All therapists / global block</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?= e($member['id']) ?>" <?= old_input('staff_id') === $member['id'] ? 'selected' : '' ?>><?= e($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Reason</span>
                        <textarea name="reason" rows="4" placeholder="Why should this time stay unavailable?"><?= e((string) old_input('reason')) ?></textarea>
                        <?php if (isset($errors['reason'])): ?><small><?= e($errors['reason']) ?></small><?php endif; ?>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Add blocked slot</button>
                    </div>
                </form>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
