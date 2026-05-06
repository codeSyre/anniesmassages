<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

$currentUser = require_login();
require_permission('customers.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
];

$hasCustomers = Customer::all() !== [];
$customers = Customer::all($filters);
$stats = Customer::stats();
$flashMessage = flash_get('customer_success');
$errors = flash_get('customer_errors', []);

$pageTitle = 'Customers';
$pageEyebrow = 'Guest profiles and preferences';
$currentRoute = 'customers';

if ($hasCustomers) {
    $topbarActions = [
        ['label' => 'New customer', 'href' => '/customers/create.php', 'permission' => 'customers.create'],
        ['label' => 'Book for a guest', 'href' => '/bookings/create.php', 'permission' => 'bookings.create'],
    ];
} else {
    $topbarAction = ['label' => 'New customer', 'href' => '/customers/create.php', 'permission' => 'customers.create'];
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

        <?php if (isset($errors['customer'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['customer']) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasCustomers ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasCustomers): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Customer management</p>
                    <h1 class="hero-title">Keep every guest record rich enough to support confident booking.</h1>
                    <p class="hero-copy">Profiles, preferences, booking history, payment context, and private admin notes all live here so the front desk never has to guess.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/customers/create.php">Create customer</a>
                        <a class="action-link is-secondary" href="/bookings/create.php">Book for a guest</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasCustomers ? ' module-stat-grid-quad' : '' ?>">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="filter-panel">
            <div class="filter-panel-row">
                <div>
                    <p class="section-kicker">Search customers</p>
                </div>
                <form class="inline-search" method="get" action="/customers/list.php">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search guest, tag, phone, note, or preference">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Customer directory</p>
                    <h3>Profiles in the system</h3>
                </div>
                <p>Use the directory to jump into booking context, recent visits, tags, notes, and recorded preferences.</p>
            </div>

            <?php if ($customers === []): ?>
                <div class="empty-state">
                    <strong>No customers matched your search.</strong>
                    <p>Try a broader term or create a new guest profile.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Status</th>
                            <th>Visits</th>
                            <th>Spend</th>
                            <th>Next visit</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <strong><?= e($customer['name']) ?></strong>
                                    <span><?= e($customer['phone']) ?> · <?= e($customer['email']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($customer['status'] ?? 'active')) ?>"><?= e(ucfirst((string) ($customer['status'] ?? 'active'))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $customer['booking_count']) ?> bookings</strong>
                                    <span><?= e($customer['last_visit'] !== null ? date('j M Y', strtotime($customer['last_visit'])) : 'No completed history yet') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $customer['total_spent'])) ?></strong>
                                    
                                </td>
                                <td>
                                    <strong><?= e($customer['next_visit'] !== null ? date('D, j M', strtotime($customer['next_visit'])) : 'No upcoming visit') ?></strong>
                                    
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/customers/view.php?id=<?= e($customer['id']) ?>" aria-label="View <?= e($customer['name']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                        <a class="icon-action-button" href="/customers/edit.php?id=<?= e($customer['id']) ?>" aria-label="Edit <?= e($customer['name']) ?>" title="Edit">
                                            <?= action_icon_svg('edit') ?>
                                        </a>
                                        <?php if (($customer['can_ban'] ?? false) === true): ?>
                                            <form
                                                class="inline-action-form inline-action-form-danger"
                                                method="post"
                                                action="/process/customer-save.php"
                                                data-confirm-dialog-form
                                                data-confirm-title="Ban customer?"
                                                data-confirm-message="Ban <?= e($customer['name']) ?>? They should no longer be treated as an active guest in the system."
                                                data-confirm-submit-label="Ban customer"
                                            >
                                                <input type="hidden" name="form_type" value="ban">
                                                <input type="hidden" name="id" value="<?= e($customer['id']) ?>">
                                                <button class="icon-action-button icon-action-button-danger" type="submit" aria-label="Ban <?= e($customer['name']) ?>" title="Ban">
                                                    <?= action_icon_svg('suspend') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
