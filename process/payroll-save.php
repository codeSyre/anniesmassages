<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

require_login();
require_permission('payroll.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/payroll/dashboard.php');
}

$action   = trim((string) ($_POST['action'] ?? 'generate'));
$returnTo = trim((string) ($_POST['return_to'] ?? '/payroll/history.php'));
$actor    = (string) (current_user()['name'] ?? 'Admin panel');

// -------------------------------------------------------------------------
// Generate a new payroll run
// -------------------------------------------------------------------------
if ($action === 'generate') {
    $payload = [
        'label'         => trim((string) ($_POST['label']        ?? '')),
        'period_start'  => trim((string) ($_POST['period_start'] ?? '')),
        'period_end'    => trim((string) ($_POST['period_end']   ?? '')),
        'selected_staff'=> $_POST['selected_staff'] ?? [],
        'notes'         => trim((string) ($_POST['notes']        ?? '')),
        'adjustments'   => is_array($_POST['adjustments']  ?? null) ? $_POST['adjustments']  : [],
        'staff_notes'   => is_array($_POST['staff_notes']  ?? null) ? $_POST['staff_notes']  : [],
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
}

// -------------------------------------------------------------------------
// Transition an existing run (finalize / pay)
// -------------------------------------------------------------------------
$runId = trim((string) ($_POST['run_id'] ?? ''));

if ($runId === '') {
    redirect_to('/payroll/history.php');
}

try {
    $run = Payroll::transitionRun($runId, $action, $actor);
} catch (Throwable $e) {
    flash_set('payroll_error', 'Could not update payroll run. Please try again.');
    redirect_to($returnTo);
}

if ($run !== null) {
    flash_set('payroll_success', match ($action) {
        'finalize' => 'Payroll run finalized successfully.',
        'pay'      => 'Payroll run marked as paid.',
        default    => 'Payroll run updated.',
    });
}

redirect_to($returnTo);
