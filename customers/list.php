<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

$currentUser = require_login();
require_permission('customers.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
];

$customers = Customer::all($filters);
$stats = Customer::stats();
$flashMessage = flash_get('customer_success');

$pageTitle = 'Customers';
$pageEyebrow = 'Guest profiles and preferences';
$currentRoute = 'customers';
$topbarAction = ['label' => 'New customer', 'href' => '/customers/create.php'];

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
                <p class="hero-eyebrow">Customer management</p>
                <h1 class="hero-title">Keep every guest record rich enough to support confident booking.</h1>
                <p class="hero-copy">Profiles, preferences, booking history, payment context, and private admin notes all live here so the front desk never has to guess.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/customers/create.php">Create customer</a>
                    <a class="action-link is-secondary" href="/bookings/create.php">Book for a guest</a>
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
                            <th>Preference</th>
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
                                    <strong><?= e($customer['preference'] !== '' ? $customer['preference'] : 'No preference recorded') ?></strong>
                                    <span><?= e(implode(' · ', $customer['tags'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $customer['booking_count']) ?> bookings</strong>
                                    <span><?= e($customer['last_visit'] !== null ? date('j M Y', strtotime($customer['last_visit'])) : 'No completed history yet') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $customer['total_spent'])) ?></strong>
                                    <span><?= e($customer['source']) ?> source</span>
                                </td>
                                <td>
                                    <strong><?= e($customer['next_visit'] !== null ? date('D, j M', strtotime($customer['next_visit'])) : 'No upcoming visit') ?></strong>
                                    <span><?= e($customer['location']) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/customers/view.php?id=<?= e($customer['id']) ?>">View</a>
                                    <a href="/customers/edit.php?id=<?= e($customer['id']) ?>">Edit</a>
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
