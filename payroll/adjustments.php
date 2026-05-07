<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

$currentUser = require_login();
require_permission('payroll.manage');

$staffOptions = Payroll::staffOptions();
$filters = [
    'staff_id' => (string) ($_GET['staff_id'] ?? ''),
    'type' => (string) ($_GET['type'] ?? 'all'),
    'status' => (string) ($_GET['status'] ?? 'all'),
    'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
    'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-t')),
];
$staffOptionLabel = static function (array $options, string $staffId, string $fallback): string {
    foreach ($options as $option) {
        if ((string) $option['id'] !== $staffId) {
            continue;
        }

        return (string) $option['name'];
    }

    return $fallback;
};

$recordStaffId = (string) old_input('staff_id', $filters['staff_id']);
$recordStaffLabel = $staffOptionLabel($staffOptions, $recordStaffId, 'Select a staff member');
$filterStaffLabel = $filters['staff_id'] !== ''
    ? $staffOptionLabel($staffOptions, $filters['staff_id'], 'All staff')
    : 'All staff';

$adjustments = Payroll::adjustments($filters);
$errors = flash_get('payroll_adjustment_errors', []);
$flashMessage = flash_get('payroll_success');

$fieldValue = static function (string $key, mixed $default = ''): mixed {
    return old_input($key, $default);
};

$pageTitle = 'Payroll Inputs';
$pageEyebrow = 'Bonuses, deductions, advances, overtime';
$currentRoute = 'payroll';
$topbarActions = [
    ['label' => 'Payroll profiles', 'href' => '/payroll/profiles.php', 'permission' => 'payroll.manage'],
    ['label' => 'Generate payroll run', 'href' => '/payroll/run.php', 'permission' => 'payroll.manage'],
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
                        <p class="section-kicker">Record payroll input</p>
                        <h3>Create a first-class payroll record</h3>
                    <p>Use this for bonuses, deductions, advance recoveries, and overtime. Only approved rows are counted in payroll.</p>
                    </div>
                </div>

                <form class="module-form" method="post" action="/process/payroll-save.php">
                    <input type="hidden" name="action" value="save_adjustment">

                    <div class="form-grid">
                        <label class="field">
                            <span>Staff member</span>
                            <input type="hidden" name="staff_id" id="payroll-adjustment-staff-id" value="<?= e($recordStaffId) ?>">
                            <input
                                type="text"
                                list="payroll-adjustment-staff-options"
                                value="<?= e($recordStaffLabel) ?>"
                                placeholder="Select a staff member"
                                data-searchable-select-input
                                data-searchable-select-target="payroll-adjustment-staff-id"
                                data-searchable-select-empty-message="Select a valid staff member from the list."
                            >
                            <datalist id="payroll-adjustment-staff-options">
                                <?php foreach ($staffOptions as $option): ?>
                                    <option value="<?= e($option['name']) ?>" data-searchable-select-id="<?= e($option['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['staff_id'])): ?><small><?= e($errors['staff_id']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Type</span>
                            <select name="type">
                                <?php foreach (Payroll::adjustmentTypes() as $type): ?>
                                    <option value="<?= e($type) ?>" <?= (string) $fieldValue('type', 'bonus') === $type ? 'selected' : '' ?>><?= e(Payroll::adjustmentTypeLabel($type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['type'])): ?><small><?= e($errors['type']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (Payroll::adjustmentStatuses() as $status): ?>
                                    <option value="<?= e($status) ?>" <?= (string) $fieldValue('status', 'pending') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Label</span>
                            <input type="text" name="label" value="<?= e((string) $fieldValue('label', '')) ?>" placeholder="Transport allowance">
                            <?php if (isset($errors['label'])): ?><small><?= e($errors['label']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Amount</span>
                            <input type="number" step="0.01" name="amount" value="<?= e((string) $fieldValue('amount', '')) ?>">
                            <?php if (isset($errors['amount'])): ?><small><?= e($errors['amount']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Hours or units</span>
                            <input type="number" step="0.01" name="units" value="<?= e((string) $fieldValue('units', '')) ?>">
                        </label>
                        <label class="field">
                            <span>Rate</span>
                            <input type="number" step="0.01" name="rate" value="<?= e((string) $fieldValue('rate', '')) ?>">
                        </label>
                        <label class="field">
                            <span>Period start</span>
                            <input type="date" name="period_start" value="<?= e((string) $fieldValue('period_start', date('Y-m-01'))) ?>">
                            <?php if (isset($errors['period_start'])): ?><small><?= e($errors['period_start']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Period end</span>
                            <input type="date" name="period_end" value="<?= e((string) $fieldValue('period_end', date('Y-m-t'))) ?>">
                            <?php if (isset($errors['period_end'])): ?><small><?= e($errors['period_end']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Notes</span>
                        <textarea name="notes" rows="3"><?= e((string) $fieldValue('notes', '')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Save payroll input</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Filters</p>
                        <h3>Review payroll inputs</h3>
                    </div>
                </div>

                <form class="module-form" method="get" action="/payroll/adjustments.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Staff</span>
                            <input type="hidden" name="staff_id" id="payroll-adjustment-filter-staff-id" value="<?= e($filters['staff_id']) ?>">
                            <input
                                type="text"
                                list="payroll-adjustment-filter-staff-options"
                                value="<?= e($filterStaffLabel) ?>"
                                placeholder="All staff"
                                data-searchable-select-input
                                data-searchable-select-target="payroll-adjustment-filter-staff-id"
                                data-searchable-select-empty-message="Select a valid staff filter from the list."
                            >
                            <datalist id="payroll-adjustment-filter-staff-options">
                                <option value="All staff" data-searchable-select-id=""></option>
                                <?php foreach ($staffOptions as $option): ?>
                                    <option value="<?= e($option['name']) ?>" data-searchable-select-id="<?= e($option['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                        <label class="field">
                            <span>Type</span>
                            <select name="type">
                                <option value="all">All types</option>
                                <?php foreach (Payroll::adjustmentTypes() as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(Payroll::adjustmentTypeLabel($type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <option value="all">All statuses</option>
                                <?php foreach (Payroll::adjustmentStatuses() as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="button-row">
                        <button class="button-primary" type="submit">Apply filters</button>
                    </div>
                </form>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Payroll input ledger</p>
                    <h3>Recorded payroll rows</h3>
                </div>
                <p><?= e((string) count($adjustments)) ?> records in scope.</p>
            </div>

            <?php if ($adjustments === []): ?>
                <div class="empty-state">
                    <strong>No payroll inputs matched the current filters.</strong>
                    <p>Record the first bonus, deduction, advance, or overtime row to start the audit trail.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['staff_name']) ?></strong>
                                    <span><?= e($row['label']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(Payroll::adjustmentTypeLabel((string) $row['type'])) ?></strong>
                                    <span><?= e($row['units'] > 0 ? format_quantity((float) $row['units']) . ' units @ ' . format_money((float) $row['rate']) : 'Flat amount') ?></span>
                                </td>
                                <td><strong><?= e(format_money((float) $row['amount'])) ?></strong></td>
                                <td>
                                    <strong><?= e($row['period_start']) ?></strong>
                                    <span><?= e($row['period_end']) ?></span>
                                </td>
                                <td><span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(ucfirst((string) $row['status'])) ?></span></td>
                                <td class="row-actions">
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="approve_adjustment">
                                            <input type="hidden" name="adjustment_id" value="<?= e($row['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/adjustments.php?staff_id=' . urlencode($filters['staff_id']) . '&type=' . urlencode($filters['type']) . '&status=' . urlencode($filters['status'])) ?>">
                                            <button class="button-link" type="submit">Approve</button>
                                        </form>
                                        <form class="inline-action-form" method="post" action="/process/payroll-save.php">
                                            <input type="hidden" name="action" value="cancel_adjustment">
                                            <input type="hidden" name="adjustment_id" value="<?= e($row['id']) ?>">
                                            <input type="hidden" name="return_to" value="<?= e('/payroll/adjustments.php?staff_id=' . urlencode($filters['staff_id']) . '&type=' . urlencode($filters['type']) . '&status=' . urlencode($filters['status'])) ?>">
                                            <button class="button-link" type="submit">Cancel</button>
                                        </form>
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
