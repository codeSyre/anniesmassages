<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Booking.php';

$currentUser = require_login();
require_permission('notifications.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'type' => (string) ($_GET['type'] ?? 'all'),
    'status' => (string) ($_GET['status'] ?? 'all'),
    'channel' => (string) ($_GET['channel'] ?? 'all'),
    'booking_id' => (string) ($_GET['booking_id'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
    'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-d')),
];

$logs = Notification::logs($filters);
$stats = Notification::stats();
$bookings = Booking::all();
$types = Notification::types();
$statuses = Notification::statuses();
$channels = Notification::channels();
$errors = flash_get('notification_log_errors', []);
$flashMessage = flash_get('notification_success');

$pageTitle = 'Notification Logs';
$pageEyebrow = 'Notifications & reminders';
$currentRoute = 'notifications';
$topbarAction = ['label' => 'Reminder settings', 'href' => '/notifications/reminders.php'];

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
                <p class="hero-eyebrow">Notification history</p>
                <h1 class="hero-title">Inspect every message event the admin workflow has generated.</h1>
                <p class="hero-copy">Logs let the front desk verify what was sent, what is still scheduled, and which booking or payment action created the communication.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/notifications/templates.php">Edit templates</a>
                    <a class="action-link is-secondary" href="/notifications/reminders.php">Reminder settings</a>
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
                        <p class="section-kicker">Manual dispatch</p>
                        <h3>Send and log a notification</h3>
                    </div>
                    <p>Use this for manual confirmations, reminders, or payment follow-up when the front desk wants an immediate send from the admin side.</p>
                </div>

                <form class="module-form" method="post" action="/process/notification-save.php">
                    <input type="hidden" name="action" value="send">

                    <div class="form-grid">
                        <label class="field">
                            <span>Booking</span>
                            <select name="booking_id">
                                <option value="">Select booking</option>
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?= e($booking['id']) ?>" <?= (string) old_input('booking_id', $filters['booking_id']) === $booking['id'] ? 'selected' : '' ?>><?= e($booking['reference'] . ' · ' . $booking['customer']['name'] . ' · ' . date('j M', strtotime($booking['date']))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['booking_id'])): ?><small><?= e($errors['booking_id']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Notification type</span>
                            <select name="type">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (string) old_input('type', 'booking_confirmation') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['type'])): ?><small><?= e($errors['type']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Dispatch note</span>
                        <textarea name="note" rows="3"><?= e((string) old_input('note', 'Manual dispatch from notifications workspace.')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Send notification</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Log filters</p>
                        <h3>Trace the right events</h3>
                    </div>
                </div>

                <form class="module-form" method="get" action="/notifications/logs.php">
                    <label class="field">
                        <span>Search</span>
                        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Reference, recipient, subject, booking">
                    </label>
                    <div class="form-grid">
                        <label class="field">
                            <span>Type</span>
                            <select name="type">
                                <option value="all">All types</option>
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <option value="all">All statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Channel</span>
                            <select name="channel">
                                <option value="all">All channels</option>
                                <?php foreach ($channels as $channel): ?>
                                    <option value="<?= e($channel) ?>" <?= $filters['channel'] === $channel ? 'selected' : '' ?>><?= e(ucfirst($channel)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Booking</span>
                            <select name="booking_id">
                                <option value="">All bookings</option>
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?= e($booking['id']) ?>" <?= $filters['booking_id'] === $booking['id'] ? 'selected' : '' ?>><?= e($booking['reference']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Date from</span>
                            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                        </label>
                        <label class="field">
                            <span>Date to</span>
                            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        </label>
                    </div>

                    <div class="button-row">
                        <a class="button-muted" href="/notifications/logs.php">Reset</a>
                        <button class="button-primary" type="submit">Apply filters</button>
                    </div>
                </form>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Notification ledger</p>
                    <h3>Recorded events</h3>
                </div>
                <p><?= e((string) count($logs)) ?> events matched the current filters.</p>
            </div>

            <?php if ($logs === []): ?>
                <div class="empty-state">
                    <strong>No notification logs matched the current filters.</strong>
                    <p>Try widening the date range or dispatch a manual notification above.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Recipient</th>
                            <th>Booking</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= e($log['reference']) ?></strong>
                                    <span><?= e($log['type_label']) ?> · <?= e(date('D, j M Y H:i', strtotime($log['created_at']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($log['recipient_name']) ?></strong>
                                    <span><?= e($log['recipient_contact']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($log['booking_reference'] !== '' ? $log['booking_reference'] : 'No booking link') ?></strong>
                                    <span><?= e($log['scheduled_for'] !== '' ? 'Scheduled ' . date('j M Y H:i', strtotime($log['scheduled_for'])) : 'Immediate event') ?></span>
                                </td>
                                <td>
                                    <strong><?= e($log['subject']) ?></strong>
                                    <span><?= e($log['note'] !== '' ? $log['note'] : $log['created_by']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($log['status'])) ?>"><?= e(ucfirst($log['status'])) ?></span>
                                </td>
                                <td class="row-actions">
                                    <?php if ($log['booking_id'] !== ''): ?>
                                        <a href="/bookings/view.php?id=<?= e($log['booking_id']) ?>">Booking</a>
                                    <?php endif; ?>
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
<?php clear_old_input(); ?>
