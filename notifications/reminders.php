<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Notification.php';

$currentUser = require_login();
require_permission('notifications.view');

$settings = Notification::reminderSettings();
$stats = Notification::stats();
$candidates = Notification::upcomingReminderCandidates();
$errors = flash_get('notification_reminder_errors', []);
$flashMessage = flash_get('notification_success');
$oldSettings = old_input('settings', []);
$oldSettings = is_array($oldSettings) ? $oldSettings : [];

$value = static function (string $key) use ($settings, $oldSettings): mixed {
    return $oldSettings[$key] ?? $settings[$key];
};

$pageTitle = 'Reminder Settings';
$pageEyebrow = 'Notifications & reminders';
$currentRoute = 'notifications';
$topbarAction = ['label' => 'Notification templates', 'href' => '/notifications/templates.php'];

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
                <p class="hero-eyebrow">Reminder engine</p>
                <h1 class="hero-title">Control what gets sent automatically and when it should go out.</h1>
                <p class="hero-copy">Reminder rules determine which booking updates dispatch immediately and which upcoming appointments get scheduled follow-up reminders.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/notifications/templates.php">Edit templates</a>
                    <a class="action-link is-secondary" href="/notifications/logs.php">Open logs</a>
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

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Automation rules</p>
                        <h3>Reminder and send settings</h3>
                    </div>
                    <p>Email is the live channel for MVP, while the toggles below decide which workflow events create customer or therapist communication.</p>
                </div>

                <form class="module-form" method="post" action="/process/notification-save.php">
                    <input type="hidden" name="action" value="reminders">

                    <div class="form-grid">
                        <label class="field">
                            <span>Primary reminder lead time (hours)</span>
                            <input type="number" min="0" step="1" name="settings[reminder_hours_before]" value="<?= e((string) $value('reminder_hours_before')) ?>">
                            <?php if (isset($errors['settings.reminder_hours_before'])): ?><small><?= e($errors['settings.reminder_hours_before']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Same-day reminder lead time (hours)</span>
                            <input type="number" min="0" step="1" name="settings[same_day_reminder_hours]" value="<?= e((string) $value('same_day_reminder_hours')) ?>">
                            <?php if (isset($errors['settings.same_day_reminder_hours'])): ?><small><?= e($errors['settings.same_day_reminder_hours']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <div class="settings-checklist">
                        <?php
                        $toggles = [
                            'booking_confirmation_enabled' => 'Send booking confirmations',
                            'customer_reminder_enabled' => 'Schedule customer reminders',
                            'same_day_reminder_enabled' => 'Allow same-day reminders',
                            'booking_cancellation_enabled' => 'Send cancellation notices',
                            'payment_confirmation_enabled' => 'Send payment confirmations',
                            'staff_assignment_enabled' => 'Notify therapists on assignment',
                        ];
                        ?>
                        <?php foreach ($toggles as $key => $label): ?>
                            <label class="toggle-field">
                                <input type="hidden" name="settings[<?= e($key) ?>]" value="0">
                                <input type="checkbox" name="settings[<?= e($key) ?>]" value="1" <?= ((string) $value($key) === '1' || $value($key) === true) ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label class="field">
                        <span>Front-desk note</span>
                        <textarea name="settings[daily_summary_note]" rows="3"><?= e((string) $value('daily_summary_note')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Save reminder settings</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Upcoming candidates</p>
                        <h3>Next reminders in scope</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($candidates as $candidate): ?>
                        <article class="info-item">
                            <strong><?= e($candidate['customer_name']) ?></strong>
                            <p><?= e($candidate['service_name']) ?> · <?= e(date('j M Y', strtotime($candidate['booking_date']))) ?> at <?= e($candidate['booking_time']) ?></p>
                            <p>Scheduled for <?= e(date('j M Y H:i', strtotime($candidate['scheduled_for']))) ?></p>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($candidates === []): ?>
                        <article class="info-item">
                            <strong>No upcoming reminders currently qualify.</strong>
                            <p>New future bookings with active reminder settings will appear here automatically.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
