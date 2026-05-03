<?php declare(strict_types=1);

require_once __DIR__ . '/Booking.php';

final class Notification
{
    public static function channels(): array
    {
        return ['email'];
    }

    public static function types(): array
    {
        return [
            'booking_confirmation' => 'Booking confirmation',
            'booking_reminder' => 'Booking reminder',
            'booking_cancellation' => 'Booking cancellation',
            'payment_confirmation' => 'Payment confirmation',
            'staff_assignment' => 'Staff assignment notification',
        ];
    }

    public static function statuses(): array
    {
        return ['scheduled', 'sent', 'skipped', 'failed'];
    }

    public static function templates(): array
    {
        $templates = self::baseTemplates();

        foreach ($_SESSION['notification_templates'] ?? [] as $key => $template) {
            $templates[$key] = array_merge($templates[$key] ?? [], $template);
        }

        return $templates;
    }

    public static function reminderSettings(): array
    {
        return array_merge([
            'booking_confirmation_enabled' => true,
            'customer_reminder_enabled' => true,
            'same_day_reminder_enabled' => true,
            'booking_cancellation_enabled' => true,
            'payment_confirmation_enabled' => true,
            'staff_assignment_enabled' => true,
            'reminder_hours_before' => 24,
            'same_day_reminder_hours' => 3,
            'channel' => 'email',
            'daily_summary_note' => 'Reminder digests are reviewed by the front desk at opening.',
        ], $_SESSION['notification_settings'] ?? []);
    }

    public static function stats(): array
    {
        $logs = self::logs();
        $today = date('Y-m-d');
        $todaySent = array_filter($logs, static function (array $log) use ($today): bool {
            return $log['status'] === 'sent' && str_starts_with($log['created_at'], $today);
        });
        $scheduled = array_filter($logs, static fn (array $log): bool => $log['status'] === 'scheduled');
        $failed = array_filter($logs, static fn (array $log): bool => $log['status'] === 'failed');
        $upcoming = self::upcomingReminderCandidates();

        return [
            ['label' => 'Sent today', 'value' => (string) count($todaySent), 'tone' => 'success'],
            ['label' => 'Scheduled reminders', 'value' => (string) count($scheduled), 'tone' => 'warning'],
            ['label' => 'Upcoming reminder candidates', 'value' => (string) count($upcoming), 'tone' => 'info'],
            ['label' => 'Failed events', 'value' => (string) count($failed), 'tone' => 'danger'],
        ];
    }

    public static function logs(array $filters = []): array
    {
        $logs = array_map(static fn (array $log): array => self::normalizeLog($log), array_values(self::mergedLogs()));
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $type = (string) ($filters['type'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');
        $channel = (string) ($filters['channel'] ?? 'all');
        $bookingId = (string) ($filters['booking_id'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $logs = array_values(array_filter($logs, static function (array $log) use ($search, $type, $status, $channel, $bookingId, $dateFrom, $dateTo): bool {
            if ($type !== 'all' && $log['type'] !== $type) {
                return false;
            }

            if ($status !== 'all' && $log['status'] !== $status) {
                return false;
            }

            if ($channel !== 'all' && $log['channel'] !== $channel) {
                return false;
            }

            if ($bookingId !== '' && $log['booking_id'] !== $bookingId) {
                return false;
            }

            if ($dateFrom !== '' && substr($log['created_at'], 0, 10) < $dateFrom) {
                return false;
            }

            if ($dateTo !== '' && substr($log['created_at'], 0, 10) > $dateTo) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $log['reference'],
                $log['booking_reference'],
                $log['recipient_name'],
                $log['recipient_contact'],
                $log['subject'],
                $log['type_label'],
                $log['note'],
            ]));

            return str_contains($haystack, $search);
        }));

        usort($logs, static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));

        return $logs;
    }

    public static function upcomingReminderCandidates(int $limit = 10): array
    {
        $settings = self::reminderSettings();

        if (!(bool) $settings['customer_reminder_enabled']) {
            return [];
        }

        $candidates = [];
        $now = time();

        foreach (Booking::all() as $booking) {
            if (!in_array($booking['status'], ['pending', 'confirmed', 'rescheduled'], true)) {
                continue;
            }

            $startAt = strtotime($booking['date'] . ' ' . $booking['time']);

            if ($startAt === false || $startAt <= $now) {
                continue;
            }

            $scheduledAt = $startAt - (((int) $settings['reminder_hours_before']) * 3600);

            if ((bool) $settings['same_day_reminder_enabled']) {
                $sameDayAt = $startAt - (((int) $settings['same_day_reminder_hours']) * 3600);
                if ($sameDayAt > $now && date('Y-m-d', $sameDayAt) === date('Y-m-d', $startAt)) {
                    $scheduledAt = min($scheduledAt, $sameDayAt);
                }
            }

            $candidates[] = [
                'booking_id' => $booking['id'],
                'reference' => $booking['reference'],
                'customer_name' => $booking['customer']['name'],
                'customer_contact' => $booking['customer']['phone'] ?? '',
                'service_name' => $booking['service']['name'],
                'staff_name' => $booking['staff']['name'],
                'booking_date' => $booking['date'],
                'booking_time' => $booking['time'],
                'scheduled_for' => date('Y-m-d H:i:s', $scheduledAt),
            ];
        }

        usort($candidates, static fn (array $left, array $right): int => strcmp($left['scheduled_for'], $right['scheduled_for']));

        return array_slice($candidates, 0, $limit);
    }

    public static function validateTemplates(array $templates): array
    {
        $errors = [];

        foreach (self::types() as $key => $label) {
            $subject = trim((string) ($templates[$key]['subject'] ?? ''));
            $body = trim((string) ($templates[$key]['body'] ?? ''));

            if ($subject === '') {
                $errors['templates.' . $key . '.subject'] = $label . ' subject is required.';
            }

            if ($body === '') {
                $errors['templates.' . $key . '.body'] = $label . ' body is required.';
            }
        }

        return $errors;
    }

    public static function saveTemplates(array $templates): array
    {
        $stored = [];

        foreach (self::types() as $key => $label) {
            $existing = self::templates()[$key] ?? self::baseTemplates()[$key];
            $stored[$key] = [
                'key' => $key,
                'label' => $label,
                'channel' => (string) ($templates[$key]['channel'] ?? $existing['channel']),
                'audience' => (string) ($existing['audience'] ?? 'customer'),
                'subject' => trim((string) ($templates[$key]['subject'] ?? $existing['subject'])),
                'body' => trim((string) ($templates[$key]['body'] ?? $existing['body'])),
                'active' => (($templates[$key]['active'] ?? '0') === '1'),
            ];
        }

        $_SESSION['notification_templates'] = $stored;

        return self::templates();
    }

    public static function validateReminderSettings(array $settings): array
    {
        $errors = [];

        foreach (['reminder_hours_before', 'same_day_reminder_hours'] as $field) {
            if (!is_numeric((string) ($settings[$field] ?? '')) || (int) ($settings[$field] ?? 0) < 0) {
                $errors['settings.' . $field] = 'Enter a valid number that is zero or greater.';
            }
        }

        return $errors;
    }

    public static function saveReminderSettings(array $settings): array
    {
        $_SESSION['notification_settings'] = [
            'booking_confirmation_enabled' => ($settings['booking_confirmation_enabled'] ?? '0') === '1',
            'customer_reminder_enabled' => ($settings['customer_reminder_enabled'] ?? '0') === '1',
            'same_day_reminder_enabled' => ($settings['same_day_reminder_enabled'] ?? '0') === '1',
            'booking_cancellation_enabled' => ($settings['booking_cancellation_enabled'] ?? '0') === '1',
            'payment_confirmation_enabled' => ($settings['payment_confirmation_enabled'] ?? '0') === '1',
            'staff_assignment_enabled' => ($settings['staff_assignment_enabled'] ?? '0') === '1',
            'reminder_hours_before' => (int) ($settings['reminder_hours_before'] ?? 24),
            'same_day_reminder_hours' => (int) ($settings['same_day_reminder_hours'] ?? 3),
            'channel' => 'email',
            'daily_summary_note' => trim((string) ($settings['daily_summary_note'] ?? '')),
        ];

        return self::reminderSettings();
    }

    public static function validateManualDispatch(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['booking_id'] ?? '')) === '') {
            $errors['booking_id'] = 'Select a booking.';
        } elseif (Booking::find((string) $payload['booking_id']) === null) {
            $errors['booking_id'] = 'Select a valid booking.';
        }

        if (!isset(self::types()[(string) ($payload['type'] ?? '')])) {
            $errors['type'] = 'Choose a valid notification type.';
        }

        return $errors;
    }

    public static function sendManualDispatch(array $payload, string $actor = 'Admin panel'): ?array
    {
        $booking = Booking::find((string) $payload['booking_id']);

        if ($booking === null) {
            return null;
        }

        return self::queueForBooking(
            $booking,
            (string) $payload['type'],
            [
                'actor' => $actor,
                'status' => 'sent',
                'note' => trim((string) ($payload['note'] ?? 'Manual dispatch from notifications workspace.')),
            ]
        );
    }

    public static function syncBookingNotifications(array $booking, ?array $previousBooking = null, string $actor = 'Admin panel'): void
    {
        $statusChanged = $previousBooking === null || (($previousBooking['status'] ?? '') !== $booking['status']);
        $staffChanged = $previousBooking === null || (($previousBooking['staff']['id'] ?? '') !== ($booking['staff']['id'] ?? ''));

        if ($previousBooking === null || ($statusChanged && in_array($booking['status'], ['pending', 'confirmed', 'rescheduled'], true))) {
            self::queueForBooking($booking, 'booking_confirmation', [
                'actor' => $actor,
                'status' => 'sent',
                'note' => 'Confirmation queued from booking workflow.',
            ]);
        }

        if ($statusChanged && $booking['status'] === 'cancelled') {
            self::queueForBooking($booking, 'booking_cancellation', [
                'actor' => $actor,
                'status' => 'sent',
                'note' => 'Cancellation notice queued from booking workflow.',
            ]);
        }

        if ($staffChanged && $booking['status'] !== 'cancelled') {
            self::queueForBooking($booking, 'staff_assignment', [
                'actor' => $actor,
                'status' => 'sent',
                'recipient' => 'staff',
                'note' => 'Staff assignment notification queued from booking workflow.',
            ]);
        }

        if (in_array($booking['status'], ['pending', 'confirmed', 'rescheduled'], true)) {
            self::queueScheduledReminder($booking, $actor);
        }
    }

    public static function logPaymentConfirmation(string $bookingId, string $paymentReference, string $actor = 'Admin panel'): ?array
    {
        $booking = Booking::find($bookingId);

        if ($booking === null) {
            return null;
        }

        return self::queueForBooking($booking, 'payment_confirmation', [
            'actor' => $actor,
            'status' => 'sent',
            'note' => 'Payment confirmation sent after ledger entry ' . $paymentReference . '.',
        ]);
    }

    private static function queueScheduledReminder(array $booking, string $actor = 'System'): ?array
    {
        $settings = self::reminderSettings();
        $template = self::templates()['booking_reminder'] ?? null;

        if ($template === null || !(bool) $template['active'] || !(bool) $settings['customer_reminder_enabled']) {
            return null;
        }

        $scheduledAt = strtotime($booking['date'] . ' ' . $booking['time'] . ' -' . (int) $settings['reminder_hours_before'] . ' hours');

        if ($scheduledAt === false || $scheduledAt <= time()) {
            return null;
        }

        foreach (self::logs(['booking_id' => $booking['id'], 'type' => 'booking_reminder']) as $existingLog) {
            if ($existingLog['scheduled_for'] === date('Y-m-d H:i:s', $scheduledAt)) {
                return null;
            }
        }

        return self::queueForBooking($booking, 'booking_reminder', [
            'actor' => $actor,
            'status' => 'scheduled',
            'scheduled_for' => date('Y-m-d H:i:s', $scheduledAt),
            'note' => 'Reminder scheduled from booking workflow.',
        ]);
    }

    private static function queueForBooking(array $booking, string $type, array $options = []): ?array
    {
        $settings = self::reminderSettings();
        $template = self::templates()[$type] ?? null;

        if ($template === null || !(bool) $template['active']) {
            return null;
        }

        if (!self::isTypeEnabled($type, $settings)) {
            return null;
        }

        $recipientMode = (string) ($options['recipient'] ?? ($template['audience'] ?? 'customer'));
        $recipientName = $recipientMode === 'staff'
            ? (string) ($booking['staff']['name'] ?? 'Assigned therapist')
            : (string) ($booking['customer']['name'] ?? 'Guest');
        $recipientContact = $recipientMode === 'staff'
            ? (string) ($booking['staff']['specialty'] ?? 'Staff inbox')
            : (string) ($booking['customer']['phone'] ?? 'Customer inbox');

        return self::logEvent([
            'type' => $type,
            'channel' => (string) $template['channel'],
            'status' => (string) ($options['status'] ?? 'sent'),
            'booking_id' => (string) $booking['id'],
            'booking_reference' => (string) $booking['reference'],
            'recipient_name' => $recipientName,
            'recipient_contact' => $recipientContact,
            'subject' => self::renderTemplate($template['subject'], $booking),
            'body' => self::renderTemplate($template['body'], $booking),
            'created_by' => (string) ($options['actor'] ?? 'System'),
            'note' => (string) ($options['note'] ?? ''),
            'scheduled_for' => (string) ($options['scheduled_for'] ?? ''),
            'sent_at' => ($options['status'] ?? 'sent') === 'sent' ? date('Y-m-d H:i:s') : '',
        ]);
    }

    private static function logEvent(array $payload): array
    {
        $logs = $_SESSION['notification_logs'] ?? [];
        $id = self::nextLogId();

        $logs[$id] = [
            'id' => $id,
            'reference' => 'NTF-' . preg_replace('/\D+/', '', $id),
            'type' => (string) $payload['type'],
            'channel' => (string) ($payload['channel'] ?? 'email'),
            'status' => (string) ($payload['status'] ?? 'sent'),
            'booking_id' => (string) ($payload['booking_id'] ?? ''),
            'booking_reference' => (string) ($payload['booking_reference'] ?? ''),
            'recipient_name' => (string) ($payload['recipient_name'] ?? 'Recipient'),
            'recipient_contact' => (string) ($payload['recipient_contact'] ?? ''),
            'subject' => trim((string) ($payload['subject'] ?? '')),
            'body' => trim((string) ($payload['body'] ?? '')),
            'created_by' => (string) ($payload['created_by'] ?? 'System'),
            'created_at' => date('Y-m-d H:i:s'),
            'scheduled_for' => trim((string) ($payload['scheduled_for'] ?? '')),
            'sent_at' => trim((string) ($payload['sent_at'] ?? '')),
            'note' => trim((string) ($payload['note'] ?? '')),
        ];

        $_SESSION['notification_logs'] = $logs;

        return self::normalizeLog($logs[$id]);
    }

    private static function normalizeLog(array $log): array
    {
        $type = (string) ($log['type'] ?? 'booking_confirmation');

        return [
            'id' => (string) $log['id'],
            'reference' => (string) ($log['reference'] ?? ('NTF-' . preg_replace('/\D+/', '', (string) $log['id']))),
            'type' => $type,
            'type_label' => self::types()[$type] ?? 'Notification',
            'channel' => (string) ($log['channel'] ?? 'email'),
            'status' => (string) ($log['status'] ?? 'sent'),
            'booking_id' => (string) ($log['booking_id'] ?? ''),
            'booking_reference' => (string) ($log['booking_reference'] ?? ''),
            'recipient_name' => (string) ($log['recipient_name'] ?? 'Recipient'),
            'recipient_contact' => (string) ($log['recipient_contact'] ?? ''),
            'subject' => (string) ($log['subject'] ?? ''),
            'body' => (string) ($log['body'] ?? ''),
            'created_by' => (string) ($log['created_by'] ?? 'System'),
            'created_at' => (string) ($log['created_at'] ?? date('Y-m-d H:i:s')),
            'scheduled_for' => (string) ($log['scheduled_for'] ?? ''),
            'sent_at' => (string) ($log['sent_at'] ?? ''),
            'note' => (string) ($log['note'] ?? ''),
        ];
    }

    private static function renderTemplate(string $content, array $booking): string
    {
        $replacements = [
            '{{customer_name}}' => (string) ($booking['customer']['name'] ?? 'Guest'),
            '{{service_name}}' => (string) ($booking['service']['name'] ?? 'Service'),
            '{{booking_date}}' => date('j M Y', strtotime((string) $booking['date'])),
            '{{booking_time}}' => (string) ($booking['time'] ?? ''),
            '{{staff_name}}' => (string) ($booking['staff']['name'] ?? 'Therapist'),
            '{{booking_reference}}' => (string) ($booking['reference'] ?? ''),
        ];

        return strtr($content, $replacements);
    }

    private static function isTypeEnabled(string $type, array $settings): bool
    {
        return match ($type) {
            'booking_confirmation' => (bool) $settings['booking_confirmation_enabled'],
            'booking_reminder' => (bool) $settings['customer_reminder_enabled'],
            'booking_cancellation' => (bool) $settings['booking_cancellation_enabled'],
            'payment_confirmation' => (bool) $settings['payment_confirmation_enabled'],
            'staff_assignment' => (bool) $settings['staff_assignment_enabled'],
            default => true,
        };
    }

    private static function mergedLogs(): array
    {
        $logs = self::baseLogs();

        foreach ($_SESSION['notification_logs'] ?? [] as $id => $log) {
            $logs[$id] = $log;
        }

        return $logs;
    }

    private static function nextLogId(): string
    {
        $max = 9000;

        foreach (array_keys(self::mergedLogs()) as $id) {
            $max = max($max, (int) preg_replace('/\D+/', '', $id));
        }

        return 'log-' . ($max + 1);
    }

    private static function baseTemplates(): array
    {
        return [
            'booking_confirmation' => [
                'key' => 'booking_confirmation',
                'label' => 'Booking confirmation',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => 'Your Annie’s Massages booking is confirmed',
                'body' => 'Hello {{customer_name}}, your {{service_name}} booking is set for {{booking_date}} at {{booking_time}} with {{staff_name}}. Reference: {{booking_reference}}.',
                'active' => true,
            ],
            'booking_reminder' => [
                'key' => 'booking_reminder',
                'label' => 'Booking reminder',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => 'Reminder: your massage is coming up soon',
                'body' => 'Hello {{customer_name}}, this is a reminder for your {{service_name}} booking on {{booking_date}} at {{booking_time}}. Reference: {{booking_reference}}.',
                'active' => true,
            ],
            'booking_cancellation' => [
                'key' => 'booking_cancellation',
                'label' => 'Booking cancellation',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => 'Update on your Annie’s Massages booking',
                'body' => 'Hello {{customer_name}}, your booking {{booking_reference}} scheduled for {{booking_date}} at {{booking_time}} has been cancelled. Please contact us if you need a new slot.',
                'active' => true,
            ],
            'payment_confirmation' => [
                'key' => 'payment_confirmation',
                'label' => 'Payment confirmation',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => 'Your payment has been received',
                'body' => 'Hello {{customer_name}}, we have recorded your payment for booking {{booking_reference}}. Your {{service_name}} session remains scheduled for {{booking_date}} at {{booking_time}}.',
                'active' => true,
            ],
            'staff_assignment' => [
                'key' => 'staff_assignment',
                'label' => 'Staff assignment notification',
                'channel' => 'email',
                'audience' => 'staff',
                'subject' => 'New booking assigned to you',
                'body' => 'Hello {{staff_name}}, you have been assigned booking {{booking_reference}} for {{customer_name}} on {{booking_date}} at {{booking_time}}.',
                'active' => true,
            ],
        ];
    }

    private static function baseLogs(): array
    {
        $today = date('Y-m-d');

        return [
            'log-9001' => [
                'id' => 'log-9001',
                'reference' => 'NTF-9001',
                'type' => 'booking_confirmation',
                'channel' => 'email',
                'status' => 'sent',
                'booking_id' => 'BK1101',
                'booking_reference' => 'BK-1101',
                'recipient_name' => 'Rudo Ncube',
                'recipient_contact' => '+263 77 100 2001',
                'subject' => 'Your Annie’s Massages booking is confirmed',
                'body' => 'Confirmation delivered for today’s Swedish Reset session.',
                'created_by' => 'System',
                'created_at' => $today . ' 08:15:00',
                'scheduled_for' => '',
                'sent_at' => $today . ' 08:15:00',
                'note' => 'Automatic confirmation after booking creation.',
            ],
            'log-9002' => [
                'id' => 'log-9002',
                'reference' => 'NTF-9002',
                'type' => 'booking_reminder',
                'channel' => 'email',
                'status' => 'scheduled',
                'booking_id' => 'BK1104',
                'booking_reference' => 'BK-1104',
                'recipient_name' => 'James & Linda',
                'recipient_contact' => '+263 77 100 2004',
                'subject' => 'Reminder: your massage is coming up soon',
                'body' => 'Reminder scheduled for the couples booking.',
                'created_by' => 'System',
                'created_at' => $today . ' 07:45:00',
                'scheduled_for' => $today . ' 10:30:00',
                'sent_at' => '',
                'note' => 'Scheduled reminder for same-day arrival.',
            ],
            'log-9003' => [
                'id' => 'log-9003',
                'reference' => 'NTF-9003',
                'type' => 'payment_confirmation',
                'channel' => 'email',
                'status' => 'sent',
                'booking_id' => 'BK1103',
                'booking_reference' => 'BK-1103',
                'recipient_name' => 'Angela Banda',
                'recipient_contact' => '+263 77 100 2003',
                'subject' => 'Your payment has been received',
                'body' => 'Receipt confirmation sent after checkout settlement.',
                'created_by' => 'Reception',
                'created_at' => $today . ' 13:40:00',
                'scheduled_for' => '',
                'sent_at' => $today . ' 13:40:00',
                'note' => 'Triggered from payment workflow.',
            ],
        ];
    }
}
