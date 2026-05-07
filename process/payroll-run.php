<?php declare(strict_types=1);

// Payroll run handler – delegates to payroll-save.php with action=generate.
// This file exists so forms can POST directly to /process/payroll-run.php
// and still follow the same flow as the main save handler.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

require_login();
require_permission('payroll.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/payroll/run.php');
}

$actor = (string) (current_user()['name'] ?? 'Admin panel');

$payload = [
    'label'          => trim((string) ($_POST['label']        ?? '')),
    'period_start'   => trim((string) ($_POST['period_start'] ?? '')),
    'period_end'     => trim((string) ($_POST['period_end']   ?? '')),
    'selected_staff' => $_POST['selected_staff'] ?? [],
    'notes'          => trim((string) ($_POST['notes']        ?? '')),
    'adjustments'    => is_array($_POST['adjustments'] ?? null) ? $_POST['adjustments'] : [],
    'staff_notes'    => is_array($_POST['staff_notes']  ?? null) ? $_POST['staff_notes'] : [],
];

$errors = Payroll::validateRunPayload($payload);

if ($errors !== []) {
    flash_set('payroll_run_errors', $errors);
    remember_old_input($payload);
    redirect_to('/payroll/run.php');
}

try {
    $run = Payroll::createRun($payload, $actor);
} catch (Throwable $e) {
    flash_set('payroll_run_errors', ['_db' => 'Failed to save payroll run. Please try again.']);
    remember_old_input($payload);
    redirect_to('/payroll/run.php');
}

clear_old_input();
flash_set('payroll_success', 'Payroll run created successfully.');
redirect_to('/payroll/history.php?id=' . urlencode($run['id']));
