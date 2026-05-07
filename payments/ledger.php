<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Payment.php';

$currentUser = require_login();
require_permission('payments.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'method' => (string) ($_GET['method'] ?? 'all'),
    'status' => (string) ($_GET['status'] ?? 'all'),
    'staff_id' => (string) ($_GET['staff_id'] ?? 'all'),
    'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
    'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-t')),
];

$hasPayments = Payment::all() !== [];
$payments = Payment::all($filters);
$totalPayments = count($payments);
$perPage = 10;
$totalPages = max(1, (int) ceil($totalPayments / $perPage));
$currentPage = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$payments = array_slice($payments, ($currentPage - 1) * $perPage, $perPage);
$stats = Payment::stats();
$methods = Payment::methods();
$statuses = Payment::statuses();
$staffOptions = Payment::staffOptions();
$flashMessage = flash_get('payment_success');
$activeFilterCount = 0;

foreach (['search', 'method', 'status', 'staff_id', 'date_from', 'date_to'] as $key) {
    $value = trim((string) ($filters[$key] ?? ''));

    if ($key === 'method' || $key === 'status' || $key === 'staff_id') {
        if ($value !== '' && $value !== 'all') {
            $activeFilterCount++;
        }

        continue;
    }

    if ($key === 'date_from' && $value !== '' && $value !== date('Y-m-01')) {
        $activeFilterCount++;
        continue;
    }

    if ($key === 'date_to' && $value !== '' && $value !== date('Y-m-t')) {
        $activeFilterCount++;
        continue;
    }

    if (!in_array($key, ['date_from', 'date_to'], true) && $value !== '') {
        $activeFilterCount++;
    }
}

$pageTitle = 'Payments Ledger';
$pageEyebrow = 'Finance operations';
$currentRoute = 'payments';

if ($hasPayments) {
    $topbarActions = [
        ['label' => 'Record payment', 'href' => '/payments/create.php', 'permission' => 'payments.create'],
        ['label' => 'Daily reconciliation', 'href' => '/payments/reconciliation.php?date=' . urlencode(date('Y-m-d')), 'permission' => 'payments.view'],
    ];
} else {
    $topbarAction = ['label' => 'Record payment', 'href' => '/payments/create.php', 'permission' => 'payments.create'];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasPayments ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasPayments): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Payments ledger</p>
                    <h1 class="hero-title">Track cash flow against every booking from one calm finance view.</h1>
                    <p class="hero-copy">Use the ledger to record incoming payments, watch outstanding balances, and keep daily reconciliation close to the booking workflow.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/payments/create.php">Record payment</a>
                        <a class="action-link is-secondary" href="/payments/reconciliation.php?date=<?= e(date('Y-m-d')) ?>">Daily reconciliation</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasPayments ? ' module-stat-grid-quad' : '' ?>">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Ledger filters</p>
                    <h3>Find the exact payment trail</h3>
                </div>
                <p>Filter by method, payment status, therapist, and date range, then export the current ledger slice as CSV.</p>
            </div>

            <div class="filter-summary-bar">
                <div class="filter-summary-copy">
                    <strong><?= e((string) $activeFilterCount) ?> active filters</strong>
                    <span><?= e(date('j M Y', strtotime($filters['date_from']))) ?> to <?= e(date('j M Y', strtotime($filters['date_to']))) ?></span>
                </div>
                <div class="button-row">
                    <button class="button-muted" type="button" data-dialog-trigger="ledger-filters">Filters</button>
                    <a class="button-muted" href="/payments/ledger.php">Clear filters</a>
                    <a class="button-muted" href="/payments/export.php?<?= e(http_build_query($filters)) ?>">Export CSV</a>
                </div>
            </div>
        </section>

        <dialog class="confirm-dialog filter-dialog" data-dialog-id="ledger-filters">
            <div class="confirm-dialog-panel filter-dialog-panel">
                <div class="confirm-dialog-copy">
                    <p class="confirm-dialog-kicker">Ledger filters</p>
                    <h3 class="confirm-dialog-title">Refine the payment trail</h3>
                    <p class="confirm-dialog-message">Search by booking context, payment method, therapist, status, and date range.</p>
                </div>

                <form class="module-form" method="get" action="/payments/ledger.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Search</span>
                            <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Reference, guest, service, therapist">
                        </label>
                        <label class="field">
                            <span>Method</span>
                            <select name="method">
                                <option value="all">All methods</option>
                                <?php foreach ($methods as $method): ?>
                                    <option value="<?= e($method) ?>" <?= $filters['method'] === $method ? 'selected' : '' ?>><?= e(Payment::methodLabel($method)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <option value="all">All statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Therapist</span>
                            <select name="staff_id">
                                <option value="all">All therapists</option>
                                <?php foreach ($staffOptions as $staff): ?>
                                    <option value="<?= e($staff['id']) ?>" <?= $filters['staff_id'] === $staff['id'] ? 'selected' : '' ?>><?= e($staff['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Date from</span>
                            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                        </label>
                        <label class="field">
                            <span>Date to</span>
                            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        </label>
                    </div>

                    <div class="button-row filter-dialog-actions">
                        <button class="topbar-link" type="button" data-dialog-close>Cancel</button>
                        <button class="button-primary" type="submit">Apply filters</button>
                    </div>
                </form>
            </div>
        </dialog>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Ledger entries</p>
                    <h3>Payment history</h3>
                </div>
                <p><?= e((string) $totalPayments) ?> entries matched the current filters.</p>
            </div>

            <?php if ($payments === []): ?>
                <div class="empty-state">
                    <strong>No payments matched the current filters.</strong>
                    <p>Try a wider date range or record the next payment directly from a booking.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Booking</th>
                            <th>Guest / Therapist</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $recordedBy = trim((string) ($payment['recorded_by'] ?? ''));
                            $recordedByParts = preg_split('/\s+[·|]\s+/', $recordedBy, 2);
                            $recordedByName = trim((string) ($recordedByParts[0] ?? $recordedBy));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($payment['reference']) ?></strong>
                                    <span><?= e(date('D, j M Y', strtotime($payment['payment_date']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($payment['booking_reference']) ?></strong>
                                    <span><?= e($payment['service']['name'] ?? 'Service') ?></span>
                                </td>
                                <td>
                                    <strong><?= e($payment['customer']['name'] ?? 'Guest') ?></strong>
                                    <span><?= e($payment['staff']['name'] ?? 'Therapist') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(Payment::methodLabel((string) $payment['method'])) ?></strong>
                                    <span><?= e($payment['note'] !== '' ? $payment['note'] : $recordedByName) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $payment['amount'])) ?></strong>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) ($payment['balance'] ?? 0))) ?></strong>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($payment['payment_status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $payment['payment_status']))) ?></span>
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/payments/view.php?booking_id=<?= e($payment['booking_id']) ?>" aria-label="View payment details for <?= e($payment['booking_reference']) ?>" title="Details">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                        <a class="icon-action-button" href="/payments/create.php?booking_id=<?= e($payment['booking_id']) ?>" aria-label="Add payment for <?= e($payment['booking_reference']) ?>" title="Add payment">
                                            <?= action_icon_svg('edit') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <?php
                $paginationQuery = array_merge($filters, ['page' => 1]);
                ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a class="pagination-btn" href="/payments/ledger.php?<?= e(http_build_query(array_merge($filters, ['page' => $currentPage - 1]))) ?>">Previous</a>
                    <?php else: ?>
                        <span class="pagination-btn is-disabled">Previous</span>
                    <?php endif; ?>

                    <span class="pagination-info"><?= e((string) $currentPage) ?> of <?= e((string) $totalPages) ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="pagination-btn" href="/payments/ledger.php?<?= e(http_build_query(array_merge($filters, ['page' => $currentPage + 1]))) ?>">Next</a>
                    <?php else: ?>
                        <span class="pagination-btn is-disabled">Next</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
