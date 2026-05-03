<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Notification.php';

require_login();
require_permission('notifications.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/notifications/templates.php');
}

$action = trim((string) ($_POST['action'] ?? 'templates'));
$actor = (string) (current_user()['name'] ?? 'Admin panel');

if ($action === 'templates') {
    $templates = is_array($_POST['templates'] ?? null) ? $_POST['templates'] : [];
    $errors = Notification::validateTemplates($templates);

    if ($errors !== []) {
        flash_set('notification_template_errors', $errors);
        remember_old_input(['templates' => $templates]);
        redirect_to('/notifications/templates.php');
    }

    Notification::saveTemplates($templates);
    clear_old_input();
    flash_set('notification_success', 'Notification templates updated successfully.');

    redirect_to('/notifications/templates.php');
}

if ($action === 'reminders') {
    $settings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
    $errors = Notification::validateReminderSettings($settings);

    if ($errors !== []) {
        flash_set('notification_reminder_errors', $errors);
        remember_old_input(['settings' => $settings]);
        redirect_to('/notifications/reminders.php');
    }

    Notification::saveReminderSettings($settings);
    clear_old_input();
    flash_set('notification_success', 'Reminder settings updated successfully.');

    redirect_to('/notifications/reminders.php');
}

if ($action === 'send') {
    $payload = [
        'booking_id' => trim((string) ($_POST['booking_id'] ?? '')),
        'type' => trim((string) ($_POST['type'] ?? 'booking_confirmation')),
        'note' => trim((string) ($_POST['note'] ?? 'Manual dispatch from notifications workspace.')),
    ];

    $errors = Notification::validateManualDispatch($payload);

    if ($errors !== []) {
        flash_set('notification_log_errors', $errors);
        remember_old_input($payload);
        $redirect = '/notifications/logs.php';

        if ($payload['booking_id'] !== '') {
            $redirect .= '?booking_id=' . urlencode($payload['booking_id']);
        }

        redirect_to($redirect);
    }

    Notification::sendManualDispatch($payload, $actor);
    clear_old_input();
    flash_set('notification_success', 'Notification dispatched and logged successfully.');

    $redirect = '/notifications/logs.php';

    if ($payload['booking_id'] !== '') {
        $redirect .= '?booking_id=' . urlencode($payload['booking_id']);
    }

    redirect_to($redirect);
}

redirect_to('/notifications/templates.php');
