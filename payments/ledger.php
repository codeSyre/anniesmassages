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
    'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-d')),
];

$payments = Payment::all($filters);
$stats = Payment::stats();
$methods = Payment::methods();
$statuses = Payment::statuses();
$staffOptions = Payment::staffOptions();
$flashMessage = flash_get('payment_success');

$pageTitle = 'Payments Ledger';
$pageEyebrow = 'Finance operations';
$currentRoute = 'payments';
$topbarAction = ['label' => 'Record payment', 'href' => '/payments/create.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Payments ledger</p>
                <h1 class="hero-title">Track cash flow against every booking from one calm finance view.</h1>
                <p class="hero-copy">Use the ledger to record incoming payments, watch outstanding balances, and keep daily reconciliation close to the booking workflow.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/payments/create.php">Record payment</a>
                    <a class="action-link is-secondary" href="/payments/reconciliation.php?date=<?= e(date('Y-m-d')) ?>">Daily reconciliation</a>
                </div>
            </article>

            <aside class="module-stat-grid">
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
                                <option value="<?= e($method) ?>" <?= $filters['method'] === $method ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $method))) ?></option>
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

                <div class="button-row">
                    <a class="button-muted" href="/payments/ledger.php">Clear filters</a>
                    <a class="button-muted" href="/payments/export.php?<?= e(http_build_query($filters)) ?>">Export CSV</a>
                    <button class="button-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Ledger entries</p>
                    <h3>Payment history</h3>
                </div>
                <p><?= e((string) count($payments)) ?> entries matched the current filters.</p>
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
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
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
                                    <strong><?= e(ucwords(str_replace('_', ' ', $payment['method']))) ?></strong>
                                    <span><?= e($payment['note'] !== '' ? $payment['note'] : $payment['recorded_by']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $payment['amount'])) ?></strong>
                                    <span><?= e($payment['recorded_by']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($payment['payment_status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $payment['payment_status']))) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/payments/view.php?booking_id=<?= e($payment['booking_id']) ?>">Details</a>
                                    <a href="/payments/create.php?booking_id=<?= e($payment['booking_id']) ?>">Add payment</a>
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
