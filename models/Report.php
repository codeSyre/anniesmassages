<?php declare(strict_types=1);

final class Report
{
    public static function dashboardOverview(): array
    {
        return [
            'headline' => [
                'eyebrow' => 'Today at a glance',
                'title' => 'Operational clarity for a calm day of service.',
                'description' => 'Bookings sit at the center of the business. This overview highlights what needs attention now across appointments, cash flow, stock, and therapist workload.',
            ],
            'metrics' => [
                [
                    'label' => "Today's bookings",
                    'value' => '18',
                    'change' => '+3 vs yesterday',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Pending bookings',
                    'value' => '5',
                    'change' => '2 need confirmation in the next hour',
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Completed bookings',
                    'value' => '9',
                    'change' => '50% of today scheduled volume',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Cancelled bookings',
                    'value' => '2',
                    'change' => '1 slot reopened for rebooking',
                    'tone' => 'danger',
                ],
                [
                    'label' => "Today's revenue",
                    'value' => format_money(1240.00),
                    'change' => 'Collected across cash, card, and transfer',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Outstanding balances',
                    'value' => format_money(285.00),
                    'change' => '3 bookings still partially paid',
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Low stock items',
                    'value' => '4',
                    'change' => 'Massage oil and towels need restock soon',
                    'tone' => 'danger',
                ],
                [
                    'label' => 'Staff booked today',
                    'value' => '6 / 8',
                    'change' => '2 therapists still available this afternoon',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Payroll pending',
                    'value' => format_money(640.00),
                    'change' => 'Awaiting review before payout',
                    'tone' => 'warning',
                ],
            ],
            'operations' => [
                [
                    'time' => '09:00',
                    'title' => 'Morning recovery massage',
                    'staff' => 'Tariro M.',
                    'customer' => 'Rudo N.',
                    'status' => 'Confirmed',
                    'tone' => 'success',
                ],
                [
                    'time' => '10:30',
                    'title' => 'Deep tissue session',
                    'staff' => 'Amanda S.',
                    'customer' => 'Lauren P.',
                    'status' => 'Pending payment',
                    'tone' => 'warning',
                ],
                [
                    'time' => '12:00',
                    'title' => 'Hot stone therapy',
                    'staff' => 'Shamiso C.',
                    'customer' => 'Angela B.',
                    'status' => 'In progress',
                    'tone' => 'info',
                ],
                [
                    'time' => '14:30',
                    'title' => 'Couples relaxation package',
                    'staff' => 'Team booking',
                    'customer' => 'James & Linda',
                    'status' => 'Needs room prep',
                    'tone' => 'warning',
                ],
            ],
            'quickActions' => [
                ['label' => 'New Booking', 'href' => '/bookings/create.php', 'description' => 'Create and assign a new appointment.'],
                ['label' => 'Open Calendar', 'href' => '/scheduling/calendar.php', 'description' => 'Check staff availability and daily slots.'],
                ['label' => 'Record Payment', 'href' => '/payments/create.php', 'description' => 'Capture partial or full payment quickly.'],
                ['label' => 'Restock Inventory', 'href' => '/inventory/create.php', 'description' => 'Add stock or adjust low inventory items.'],
            ],
            'recentActivity' => [
                [
                    'title' => 'Payment recorded',
                    'description' => 'A card payment of ' . format_money(120.00) . ' was linked to booking BK-1048.',
                    'time' => '8 minutes ago',
                ],
                [
                    'title' => 'Booking rescheduled',
                    'description' => 'Client Chipo N. moved their aromatherapy session to 16:00.',
                    'time' => '21 minutes ago',
                ],
                [
                    'title' => 'Low stock alert',
                    'description' => 'Carrier oil dropped below the threshold set for treatment room supplies.',
                    'time' => '42 minutes ago',
                ],
                [
                    'title' => 'Staff availability updated',
                    'description' => 'Amanda S. marked herself unavailable for tomorrow morning.',
                    'time' => '1 hour ago',
                ],
            ],
            'focusPanels' => [
                [
                    'title' => 'Revenue pulse',
                    'value' => '78%',
                    'description' => 'Of today’s scheduled value has already been collected.',
                ],
                [
                    'title' => 'Capacity',
                    'value' => '2 open slots',
                    'description' => 'Late afternoon windows can still absorb walk-ins or rebookings.',
                ],
                [
                    'title' => 'Guest satisfaction',
                    'value' => '4.9 / 5',
                    'description' => 'Based on this week’s post-treatment internal follow-up notes.',
                ],
            ],
        ];
    }
}
