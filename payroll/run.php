<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payroll.php';

$currentUser = require_login();
require_permission('payroll.manage');

$errors = flash_get('payroll_run_errors', []);
$selectedFromQuery = [];
$queryStaff = (string) ($_GET['staff_id'] ?? '');

if ($queryStaff !== '') {
    $selectedFromQuery[] = $queryStaff;
}

$payload = [
    'label' => (string) old_input('label', ''),
    'period_start' => (string) old_input('period_start', (string) ($_GET['period_start'] ?? date('Y-m-01'))),
    'period_end' => (string) old_input('period_end', (string) ($_GET['period_end'] ?? date('Y-m-t'))),
    'selected_staff' => old_input('selected_staff', $selectedFromQuery),
    'notes' => (string) old_input('notes', ''),
    'adjustments' => old_input('adjustments', []),
    'staff_notes' => old_input('staff_notes', []),
];

$payload['selected_staff'] = is_array($payload['selected_staff']) ? $payload['selected_staff'] : [];
$payload['adjustments'] = is_array($payload['adjustments']) ? $payload['adjustments'] : [];
$payload['staff_notes'] = is_array($payload['staff_notes']) ? $payload['staff_notes'] : [];

$preview = Payroll::previewRun($payload);
$staffOptions = Payroll::staffOptions();

$pageTitle = 'Generate Payroll Run';
$pageEyebrow = 'Payroll snapshot';
$currentRoute = 'payroll';
$topbarAction = ['label' => 'Back to payroll', 'href' => '/payroll/dashboard.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Run setup</p>
                        <h3>Create a payroll snapshot</h3>
                    </div>
                    <p>Draft runs lock the earnings state for the chosen period so later booking edits do not silently rewrite payroll.</p>
                </div>

                <form class="module-form" method="post" action="/process/payroll-save.php">
                    <input type="hidden" name="action" value="generate">

                    <div class="form-grid">
                        <label class="field">
                            <span>Run label</span>
                            <input type="text" name="label" value="<?= e($payload['label']) ?>" placeholder="May weekly payroll">
                        </label>
                        <label class="field">
                            <span>Period start</span>
                            <input type="date" name="period_start" value="<?= e($payload['period_start']) ?>">
                            <?php if (isset($errors['period_start'])): ?><small><?= e($errors['period_start']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Period end</span>
                            <input type="date" name="period_end" value="<?= e($payload['period_end']) ?>">
                            <?php if (isset($errors['period_end'])): ?><small><?= e($errors['period_end']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Staff in scope</span>
                            <select name="selected_staff[]" multiple>
                                <?php foreach ($staffOptions as $staff): ?>
                                    <option value="<?= e($staff['id']) ?>" <?= in_array($staff['id'], $payload['selected_staff'], true) ? 'selected' : '' ?>><?= e($staff['name'] . ' · ' . ucfirst($staff['salary_structure'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['selected_staff'])): ?><small><?= e($errors['selected_staff']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Run notes</span>
                        <textarea name="notes" rows="3" placeholder="Context for this payroll batch, approvals, exceptions..."><?= e($payload['notes']) ?></textarea>
                    </label>

                    <section class="table-card payroll-preview-card">
                        <div class="section-head">
                            <div>
                                <p class="section-kicker">Item preview</p>
                                <h3>Manual adjustments before lock</h3>
                            </div>
                        </div>

                        <?php if ($preview['items'] === []): ?>
                            <div class="empty-state">
                                <strong>No payroll items are in scope yet.</strong>
                                <p>Choose a broader period or include more therapists.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Therapist</th>
                                        <th>Base payout</th>
                                        <th>Adjustment</th>
                                        <th>Staff note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($item['staff_name']) ?></strong>
                                                <span><?= e((string) $item['completed_count']) ?> completed · <?= e(format_money((float) $item['commission_total'])) ?> commission</span>
                                            </td>
                                            <td>
                                                <strong><?= e(format_money((float) $item['base_payout'])) ?></strong>
                                                <span><?= e(ucfirst($item['salary_structure'])) ?></span>
                                            </td>
                                            <td>
                                                <input class="table-input" type="number" step="0.01" name="adjustments[<?= e($item['staff_id']) ?>]" value="<?= e((string) ($payload['adjustments'][$item['staff_id']] ?? '0')) ?>">
                                                <?php if (isset($errors['adjustments.' . $item['staff_id']])): ?><small class="inline-error"><?= e($errors['adjustments.' . $item['staff_id']]) ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <textarea class="table-textarea" name="staff_notes[<?= e($item['staff_id']) ?>]" rows="2" placeholder="Optional adjustment note"><?= e((string) ($payload['staff_notes'][$item['staff_id']] ?? '')) ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>

                    <div class="button-row">
                        <a class="button-muted" href="/payroll/dashboard.php">Cancel</a>
                        <button class="button-primary" type="submit">Create payroll run</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Run summary</p>
                        <h3>What will be locked</h3>
                    </div>
                </div>

                <div class="detail-pairs detail-pairs-single">
                    <div><span>Staff rows</span><strong><?= e((string) $preview['totals']['staff_count']) ?></strong><small>Snapshot item count</small></div>
                    <div><span>Completed bookings</span><strong><?= e((string) $preview['totals']['completed_bookings']) ?></strong><small>Only completed sessions count</small></div>
                    <div><span>Base payout</span><strong><?= e(format_money((float) $preview['totals']['base_payout'])) ?></strong><small>Before adjustments</small></div>
                    <div><span>Adjustment total</span><strong><?= e(format_money((float) $preview['totals']['adjustment_total'])) ?></strong><small>Manual changes to carry into the run</small></div>
                    <div><span>Net payout</span><strong><?= e(format_money((float) $preview['totals']['net_payout'])) ?></strong><small>Projected payroll outflow</small></div>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
