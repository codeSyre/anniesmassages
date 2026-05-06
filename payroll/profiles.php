<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payroll.manage');

$staffOptions = Payroll::staffOptions();
$selectedStaffId = (string) ($_GET['staff_id'] ?? ($staffOptions[0]['id'] ?? ''));
$selectedProfile = $selectedStaffId !== '' ? Payroll::profileForStaff($selectedStaffId) : null;
$profiles = Payroll::profiles();
$errors = flash_get('payroll_profile_errors', []);
$flashMessage = flash_get('payroll_success');

$fieldValue = static function (string $key, mixed $default = '') use ($selectedProfile): mixed {
    return old_input($key, $selectedProfile[$key] ?? $default);
};

$pageTitle = 'Payroll Profiles';
$pageEyebrow = 'Employee payroll setup';
$currentRoute = 'payroll';
$topbarActions = [
    ['label' => 'Generate payroll run', 'href' => '/payroll/run.php', 'permission' => 'payroll.manage'],
    ['label' => 'Payroll inputs', 'href' => '/payroll/adjustments.php', 'permission' => 'payroll.manage'],
];

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
                        <p class="section-kicker">Payroll profile</p>
                        <h3>Set how each employee should be paid</h3>
                    </div>
                    <p>These settings drive payroll calculations, payment posting, and statutory deductions.</p>
                </div>

                <form class="module-form" method="get" action="/payroll/profiles.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Staff member</span>
                            <select name="staff_id">
                                <?php foreach ($staffOptions as $option): ?>
                                    <option value="<?= e($option['id']) ?>" <?= $selectedStaffId === $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="button-row">
                        <button class="button-primary" type="submit">Load profile</button>
                    </div>
                </form>

                <?php if ($selectedProfile !== null): ?>
                    <form class="module-form section-spaced" method="post" action="/process/payroll-save.php">
                        <input type="hidden" name="action" value="save_profile">
                        <input type="hidden" name="staff_id" value="<?= e($selectedStaffId) ?>">

                        <div class="form-grid">
                            <label class="field">
                                <span>Employment type</span>
                                <select name="employment_type">
                                    <?php foreach (Payroll::employmentTypes() as $value): ?>
                                        <option value="<?= e($value) ?>" <?= (string) $fieldValue('employment_type') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['employment_type'])): ?><small><?= e($errors['employment_type']) ?></small><?php endif; ?>
                            </label>
                            <label class="field">
                                <span>Salary structure</span>
                                <select name="salary_structure">
                                    <?php foreach (['commission', 'fixed', 'hybrid'] as $value): ?>
                                        <option value="<?= e($value) ?>" <?= (string) $fieldValue('salary_structure') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['salary_structure'])): ?><small><?= e($errors['salary_structure']) ?></small><?php endif; ?>
                            </label>
                            <label class="field">
                                <span>Payment method</span>
                                <select name="payment_method">
                                    <?php foreach (Payment::methods() as $method): ?>
                                        <option value="<?= e($method) ?>" <?= (string) $fieldValue('payment_method') === $method ? 'selected' : '' ?>><?= e(Payment::methodLabel($method)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['payment_method'])): ?><small><?= e($errors['payment_method']) ?></small><?php endif; ?>
                            </label>
                            <label class="field">
                                <span>Commission model</span>
                                <select name="commission_model">
                                    <?php foreach (Payroll::commissionModels() as $value): ?>
                                        <option value="<?= e($value) ?>" <?= (string) $fieldValue('commission_model') === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['commission_model'])): ?><small><?= e($errors['commission_model']) ?></small><?php endif; ?>
                            </label>
                            <label class="field">
                                <span>Commission rate %</span>
                                <input type="number" step="0.01" name="commission_rate" value="<?= e((string) $fieldValue('commission_rate', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Per-booking commission</span>
                                <input type="number" step="0.01" name="commission_per_booking" value="<?= e((string) $fieldValue('commission_per_booking', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Base salary</span>
                                <input type="number" step="0.01" name="base_salary" value="<?= e((string) $fieldValue('base_salary', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Hourly rate</span>
                                <input type="number" step="0.01" name="hourly_rate" value="<?= e((string) $fieldValue('hourly_rate', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Overtime rate</span>
                                <input type="number" step="0.01" name="overtime_rate" value="<?= e((string) $fieldValue('overtime_rate', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Tax %</span>
                                <input type="number" step="0.01" name="tax_percent" value="<?= e((string) $fieldValue('tax_percent', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Pension %</span>
                                <input type="number" step="0.01" name="pension_percent" value="<?= e((string) $fieldValue('pension_percent', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>NSSA %</span>
                                <input type="number" step="0.01" name="nssa_percent" value="<?= e((string) $fieldValue('nssa_percent', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Medical aid amount</span>
                                <input type="number" step="0.01" name="medical_aid_amount" value="<?= e((string) $fieldValue('medical_aid_amount', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Advance limit</span>
                                <input type="number" step="0.01" name="advance_limit" value="<?= e((string) $fieldValue('advance_limit', '0')) ?>">
                            </label>
                            <label class="field">
                                <span>Effective from</span>
                                <input type="date" name="effective_from" value="<?= e((string) $fieldValue('effective_from', date('Y-m-01'))) ?>">
                            </label>
                        </div>

                        <label class="field">
                            <span>Notes</span>
                            <textarea name="notes" rows="3"><?= e((string) $fieldValue('notes', '')) ?></textarea>
                        </label>

                        <div class="form-grid">
                            <label class="field checkbox-field">
                                <input type="checkbox" name="overtime_eligible" value="1" <?= (string) $fieldValue('overtime_eligible', '0') === '1' ? 'checked' : '' ?>>
                                <span>Eligible for overtime</span>
                            </label>
                            <label class="field checkbox-field">
                                <input type="checkbox" name="active" value="1" <?= (string) $fieldValue('active', '1') === '1' ? 'checked' : '' ?>>
                                <span>Active payroll profile</span>
                            </label>
                        </div>

                        <div class="button-row">
                            <button class="button-primary" type="submit">Save payroll profile</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Current roster</p>
                        <h3>Profile coverage</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($profiles as $profile): ?>
                        <article class="info-item">
                            <strong><?= e($profile['staff_name']) ?></strong>
                            <p><?= e(ucfirst((string) $profile['employment_type'])) ?> · <?= e(ucfirst((string) $profile['salary_structure'])) ?> · <?= e(Payment::methodLabel((string) $profile['payment_method'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
