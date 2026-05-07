CREATE DATABASE IF NOT EXISTS anniesmassages
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE anniesmassages;

CREATE TABLE IF NOT EXISTS roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id CHAR(36) NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    group_name VARCHAR(80) NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (role_id, permission_key),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) NOT NULL PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    title VARCHAR(120) NULL,
    phone VARCHAR(40) NULL,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city_town VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    profile_picture_path VARCHAR(255) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Harare',
    bio TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    notification_booking_updates TINYINT(1) NOT NULL DEFAULT 1,
    notification_payment_updates TINYINT(1) NOT NULL DEFAULT 1,
    notification_system_alerts TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id CHAR(36) NOT NULL PRIMARY KEY,
    role_id CHAR(36) NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    preference VARCHAR(255) NULL,
    admin_notes TEXT NULL,
    location VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customers_name (first_name, last_name),
    KEY idx_customers_phone (phone),
    KEY idx_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_tags (
    customer_id CHAR(36) NOT NULL,
    tag VARCHAR(80) NOT NULL,
    PRIMARY KEY (customer_id, tag),
    CONSTRAINT fk_customer_tags_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff (
    id CHAR(36) NOT NULL PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    specialty VARCHAR(150) NULL,
    role_type VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL UNIQUE,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city_town VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    profile_picture_path VARCHAR(255) NULL,
    bio TEXT NULL,
    capacity_label VARCHAR(100) NULL,
    salary_structure VARCHAR(20) NOT NULL DEFAULT 'commission',
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    fixed_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    color_hex VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_staff_status (status),
    KEY idx_staff_role_type (role_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category ENUM('Massage', 'Therapeutic', 'Signature', 'Wellness', 'Experience') NOT NULL DEFAULT 'Massage',
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    room VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_services_active (is_active),
    KEY idx_services_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_addons (
    id CHAR(36) NOT NULL PRIMARY KEY,
    service_id CHAR(36) NOT NULL,
    addon_name VARCHAR(150) NOT NULL,
    addon_price DECIMAL(10,2) NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_service_addons_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(64) NOT NULL UNIQUE,
    customer_id CHAR(36) NOT NULL,
    service_id CHAR(36) NOT NULL,
    staff_id CHAR(36) NOT NULL,
    customer_name_snapshot VARCHAR(150) NULL,
    customer_phone_snapshot VARCHAR(40) NULL,
    customer_email_snapshot VARCHAR(190) NULL,
    service_name_snapshot VARCHAR(150) NULL,
    service_price_snapshot DECIMAL(10,2) NULL,
    staff_name_snapshot VARCHAR(150) NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    channel VARCHAR(50) NULL,
    amount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    location VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_staff
        FOREIGN KEY (staff_id) REFERENCES staff (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    KEY idx_bookings_date (appointment_date),
    KEY idx_bookings_status (status),
    KEY idx_bookings_payment_status (payment_status),
    KEY idx_bookings_customer_date (customer_id, appointment_date),
    KEY idx_bookings_staff_date (staff_id, appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_history (
    id CHAR(36) NOT NULL PRIMARY KEY,
    booking_id CHAR(36) NOT NULL,
    event_label VARCHAR(150) NOT NULL,
    event_meta TEXT NULL,
    tone VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_history_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    KEY idx_booking_history_booking (booking_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(64) NOT NULL UNIQUE,
    booking_id CHAR(36) NOT NULL,
    payment_date DATE NOT NULL,
    method VARCHAR(40) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'paid',
    note TEXT NULL,
    recorded_by VARCHAR(120) NULL,
    external_reference VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    KEY idx_payments_booking (booking_id),
    KEY idx_payments_date (payment_date),
    KEY idx_payments_method (method),
    KEY idx_payments_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduling_settings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    day_start TIME NOT NULL,
    day_end TIME NOT NULL,
    slot_interval_minutes SMALLINT UNSIGNED NOT NULL,
    default_duration_minutes SMALLINT UNSIGNED NOT NULL,
    buffer_minutes SMALLINT UNSIGNED NOT NULL,
    same_day_lead_minutes SMALLINT UNSIGNED NOT NULL,
    max_parallel_rooms SMALLINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_weekly_availability (
    id CHAR(36) NOT NULL PRIMARY KEY,
    staff_id CHAR(36) NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    start_time TIME NULL,
    end_time TIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_weekly_availability_staff
        FOREIGN KEY (staff_id) REFERENCES staff (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    UNIQUE KEY uq_staff_weekday (staff_id, weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blocked_periods (
    id CHAR(36) NOT NULL PRIMARY KEY,
    block_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    staff_id CHAR(36) NULL,
    scope VARCHAR(20) NOT NULL DEFAULT 'global',
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blocked_periods_staff
        FOREIGN KEY (staff_id) REFERENCES staff (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    KEY idx_blocked_periods_date (block_date),
    KEY idx_blocked_periods_staff_date (staff_id, block_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(80) NOT NULL UNIQUE,
    category VARCHAR(120) NULL,
    unit VARCHAR(50) NOT NULL,
    on_hand DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier VARCHAR(150) NULL,
    location VARCHAR(120) NULL,
    notes TEXT NULL,
    last_movement_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_inventory_items_category (category),
    KEY idx_inventory_items_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_item_services (
    item_id CHAR(36) NOT NULL,
    service_id CHAR(36) NOT NULL,
    PRIMARY KEY (item_id, service_id),
    CONSTRAINT fk_inventory_item_services_item
        FOREIGN KEY (item_id) REFERENCES inventory_items (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_inventory_item_services_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(64) NOT NULL UNIQUE,
    item_id CHAR(36) NOT NULL,
    service_id CHAR(36) NULL,
    movement_date DATE NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    before_quantity DECIMAL(10,2) NOT NULL,
    after_quantity DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    recorded_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_movements_item
        FOREIGN KEY (item_id) REFERENCES inventory_items (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_inventory_movements_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    KEY idx_inventory_movements_date (movement_date),
    KEY idx_inventory_movements_type (movement_type),
    KEY idx_inventory_movements_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_settings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    booking_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1,
    customer_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
    same_day_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
    booking_cancellation_enabled TINYINT(1) NOT NULL DEFAULT 1,
    payment_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1,
    staff_assignment_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reminder_hours_before SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    same_day_reminder_hours SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',
    daily_summary_note TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    template_key VARCHAR(64) NOT NULL PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',
    audience VARCHAR(40) NOT NULL DEFAULT 'customer',
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(64) NOT NULL UNIQUE,
    booking_id CHAR(36) NULL,
    type VARCHAR(64) NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',
    status VARCHAR(20) NOT NULL DEFAULT 'sent',
    recipient_kind VARCHAR(40) NOT NULL DEFAULT 'customer',
    recipient_name VARCHAR(150) NOT NULL,
    recipient_contact VARCHAR(190) NULL,
    subject VARCHAR(255) NOT NULL,
    body_snapshot LONGTEXT NOT NULL,
    note TEXT NULL,
    scheduled_for DATETIME NULL,
    sent_at DATETIME NULL,
    created_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_logs_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    KEY idx_notification_logs_booking (booking_id),
    KEY idx_notification_logs_type (type),
    KEY idx_notification_logs_status (status),
    KEY idx_notification_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_runs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_by VARCHAR(120) NULL,
    finalized_at DATETIME NULL,
    paid_at DATETIME NULL,
    notes TEXT NULL,
    staff_count INT UNSIGNED NOT NULL DEFAULT 0,
    completed_bookings INT UNSIGNED NOT NULL DEFAULT 0,
    commissionable_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    base_payout DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adjustment_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_payout DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payroll_runs_period (period_start, period_end),
    KEY idx_payroll_runs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_run_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    run_id CHAR(36) NOT NULL,
    staff_id CHAR(36) NULL,
    staff_name VARCHAR(150) NOT NULL,
    role_type VARCHAR(100) NULL,
    salary_structure VARCHAR(20) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    fixed_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    completed_count INT UNSIGNED NOT NULL DEFAULT 0,
    commissionable_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    collected_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commission_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    base_payout DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adjustment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adjustment_note TEXT NULL,
    total_payout DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_payroll_run_items_run
        FOREIGN KEY (run_id) REFERENCES payroll_runs (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_payroll_run_items_staff
        FOREIGN KEY (staff_id) REFERENCES staff (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    KEY idx_payroll_run_items_run (run_id),
    KEY idx_payroll_run_items_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_run_history (
    id CHAR(36) NOT NULL PRIMARY KEY,
    run_id CHAR(36) NOT NULL,
    event_label VARCHAR(150) NOT NULL,
    event_meta TEXT NULL,
    tone VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payroll_run_history_run
        FOREIGN KEY (run_id) REFERENCES payroll_runs (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    KEY idx_payroll_run_history_run (run_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO scheduling_settings (
    id,
    day_start,
    day_end,
    slot_interval_minutes,
    default_duration_minutes,
    buffer_minutes,
    same_day_lead_minutes,
    max_parallel_rooms
) VALUES ('0ec6ee8d-4122-4dd3-8d7e-d654ecb54f3a', '08:00:00', '18:00:00', 15, 60, 15, 60, 2);

INSERT IGNORE INTO notification_settings (
    id,
    booking_confirmation_enabled,
    customer_reminder_enabled,
    same_day_reminder_enabled,
    booking_cancellation_enabled,
    payment_confirmation_enabled,
    staff_assignment_enabled,
    reminder_hours_before,
    same_day_reminder_hours,
    channel,
    daily_summary_note
) VALUES (
    '2183ea2f-7c4c-4725-8dd0-f8b1c9b7d55f',
    1,
    1,
    1,
    1,
    1,
    1,
    24,
    3,
    'email',
    'Reminder digests are reviewed by the front desk at opening.'
);

INSERT IGNORE INTO notification_templates (
    template_key,
    label,
    channel,
    audience,
    subject,
    body,
    is_active
) VALUES
    ('booking_confirmation', 'Booking confirmation', 'email', 'customer', 'Your Annie''s Massages booking is confirmed', 'Hello {{customer_name}}, your {{service_name}} booking is set for {{booking_date}} at {{booking_time}} with {{staff_name}}. Reference: {{booking_reference}}.', 1),
    ('booking_reminder', 'Booking reminder', 'email', 'customer', 'Reminder: your massage is coming up soon', 'Hello {{customer_name}}, this is a reminder for your {{service_name}} booking on {{booking_date}} at {{booking_time}}. Reference: {{booking_reference}}.', 1),
    ('booking_cancellation', 'Booking cancellation', 'email', 'customer', 'Update on your Annie''s Massages booking', 'Hello {{customer_name}}, your booking {{booking_reference}} scheduled for {{booking_date}} at {{booking_time}} has been cancelled. Please contact us if you need a new slot.', 1),
    ('payment_confirmation', 'Payment confirmation', 'email', 'customer', 'Your payment has been received', 'Hello {{customer_name}}, we have recorded your payment for booking {{booking_reference}}. Your {{service_name}} session remains scheduled for {{booking_date}} at {{booking_time}}.', 1),
    ('staff_assignment', 'Staff assignment notification', 'email', 'staff', 'New booking assigned to you', 'Hello {{staff_name}}, you have been assigned booking {{booking_reference}} for {{customer_name}} on {{booking_date}} at {{booking_time}}.', 1);
