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

if ($action === 'save_profile') {
    $payload = [
        'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
        'employment_type' => trim((string) ($_POST['employment_type'] ?? '')),
        'salary_structure' => trim((string) ($_POST['salary_structure'] ?? '')),
        'payment_method' => trim((string) ($_POST['payment_method'] ?? '')),
        'commission_model' => trim((string) ($_POST['commission_model'] ?? '')),
        'commission_rate' => trim((string) ($_POST['commission_rate'] ?? '0')),
        'commission_per_booking' => trim((string) ($_POST['commission_per_booking'] ?? '0')),
        'base_salary' => trim((string) ($_POST['base_salary'] ?? '0')),
        'hourly_rate' => trim((string) ($_POST['hourly_rate'] ?? '0')),
        'overtime_rate' => trim((string) ($_POST['overtime_rate'] ?? '0')),
        'overtime_eligible' => (string) ($_POST['overtime_eligible'] ?? '0'),
        'tax_percent' => trim((string) ($_POST['tax_percent'] ?? '0')),
        'pension_percent' => trim((string) ($_POST['pension_percent'] ?? '0')),
        'nssa_percent' => trim((string) ($_POST['nssa_percent'] ?? '0')),
        'medical_aid_amount' => trim((string) ($_POST['medical_aid_amount'] ?? '0')),
        'advance_limit' => trim((string) ($_POST['advance_limit'] ?? '0')),
        'effective_from' => trim((string) ($_POST['effective_from'] ?? date('Y-m-01'))),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'active' => (string) ($_POST['active'] ?? '0'),
    ];

    $errors = Payroll::validateProfilePayload($payload);

    if ($errors !== []) {
        flash_set('payroll_profile_errors', $errors);
        remember_old_input($payload);
        redirect_to('/payroll/profiles.php?staff_id=' . urlencode($payload['staff_id']));
    }

    $profile = Payroll::saveProfile($payload, $actor);

    if (!is_array($profile)) {
        flash_set('payroll_profile_errors', ['profile' => 'Failed to save payroll profile.']);
        remember_old_input($payload);
        redirect_to('/payroll/profiles.php?staff_id=' . urlencode($payload['staff_id']));
    }

    clear_old_input();
    flash_set('payroll_success', 'Payroll profile saved.');
    redirect_to('/payroll/profiles.php?staff_id=' . urlencode($payload['staff_id']));
}

if ($action === 'save_adjustment') {
    $payload = [
        'staff_id' => trim((string) ($_POST['staff_id'] ?? '')),
        'type' => trim((string) ($_POST['type'] ?? 'bonus')),
        'label' => trim((string) ($_POST['label'] ?? '')),
        'amount' => trim((string) ($_POST['amount'] ?? '')),
        'units' => trim((string) ($_POST['units'] ?? '')),
        'rate' => trim((string) ($_POST['rate'] ?? '')),
        'period_start' => trim((string) ($_POST['period_start'] ?? date('Y-m-01'))),
        'period_end' => trim((string) ($_POST['period_end'] ?? date('Y-m-t'))),
        'status' => trim((string) ($_POST['status'] ?? 'pending')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    $errors = Payroll::validateAdjustmentPayload($payload);

    if ($errors !== []) {
        flash_set('payroll_adjustment_errors', $errors);
        remember_old_input($payload);
        redirect_to('/payroll/adjustments.php?staff_id=' . urlencode($payload['staff_id']));
    }

    $record = Payroll::saveAdjustment($payload, $actor);

    if (!is_array($record)) {
        flash_set('payroll_adjustment_errors', ['adjustment' => 'Failed to save payroll input.']);
        remember_old_input($payload);
        redirect_to('/payroll/adjustments.php?staff_id=' . urlencode($payload['staff_id']));
    }

    clear_old_input();
    flash_set('payroll_success', 'Payroll input recorded.');
    redirect_to('/payroll/adjustments.php?staff_id=' . urlencode($payload['staff_id']));
}

if (in_array($action, ['approve_adjustment', 'cancel_adjustment'], true)) {
    $adjustmentId = trim((string) ($_POST['adjustment_id'] ?? ''));
    if ($adjustmentId !== '') {
        Payroll::transitionAdjustment($adjustmentId, $action, $actor);
    }
    flash_set('payroll_success', $action === 'approve_adjustment' ? 'Payroll input approved.' : 'Payroll input cancelled.');
    redirect_to($returnTo);
}

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
    $run = Payroll::transitionRun($runId, $action, $actor, trim((string) ($_POST['reason'] ?? '')));
} catch (Throwable $e) {
    flash_set('payroll_error', 'Could not update payroll run. Please try again.');
    redirect_to($returnTo);
}

if ($run !== null) {
    flash_set('payroll_success', match ($action) {
        'submit_review' => 'Payroll run sent for review.',
        'approve'       => 'Payroll run approved.',
        'lock', 'finalize' => 'Payroll run locked and payslips generated.',
        'pay'           => 'Payroll payout entries posted successfully.',
        'cancel'        => 'Payroll run cancelled.',
        default    => 'Payroll run updated.',
    });
}

redirect_to($returnTo);
