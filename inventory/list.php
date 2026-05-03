<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'all'),
    'category' => (string) ($_GET['category'] ?? 'all'),
];

$items = Inventory::all($filters);
$stats = Inventory::stats();
$categories = Inventory::categories();
$flashMessage = flash_get('inventory_success');

$pageTitle = 'Inventory';
$pageEyebrow = 'Stock levels and supply flow';
$currentRoute = 'inventory';
$topbarAction = ['label' => 'New inventory item', 'href' => '/inventory/create.php'];

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
                <p class="hero-eyebrow">Inventory management</p>
                <h1 class="hero-title">Track every supply item before shortages disrupt bookings.</h1>
                <p class="hero-copy">Keep consumables, room setup stock, equipment, and service-linked usage visible from one operational stock layer.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/create.php">Add inventory item</a>
                    <a class="action-link is-secondary" href="/inventory/movements.php">Stock movement history</a>
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
                <div class="filter-chip-row">
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/inventory/list.php?category=<?= e(urlencode($filters['category'])) ?>">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'in_stock')) ?>" href="/inventory/list.php?status=in_stock&category=<?= e(urlencode($filters['category'])) ?>">In stock</a>
                    <a class="<?= e(active_filter($filters['status'], 'low_stock')) ?>" href="/inventory/list.php?status=low_stock&category=<?= e(urlencode($filters['category'])) ?>">Low stock</a>
                    <a class="<?= e(active_filter($filters['status'], 'out_of_stock')) ?>" href="/inventory/list.php?status=out_of_stock&category=<?= e(urlencode($filters['category'])) ?>">Out of stock</a>
                </div>

                <form class="inline-search" method="get" action="/inventory/list.php">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <select name="category">
                        <option value="all">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search item, SKU, supplier, location, or linked service">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Inventory register</p>
                    <h3>Current stock on hand</h3>
                </div>
                <p>Every quantity shown here is backed by movement history so stock changes stay auditable.</p>
            </div>

            <?php if ($items === []): ?>
                <div class="empty-state">
                    <strong>No inventory items matched the current filters.</strong>
                    <p>Try clearing the search or add a new stock item.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>On hand</th>
                            <th>Reorder point</th>
                            <th>Stock value</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['name']) ?></strong>
                                    <span><?= e($item['sku']) ?> · <?= e($item['location']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($item['category']) ?></strong>
                                    <span><?= e($item['supplier'] !== '' ? $item['supplier'] : 'No supplier recorded') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $item['on_hand'])) ?> <?= e($item['unit']) ?></strong>
                                    <span><?= e($item['last_movement_at'] !== null ? 'Last movement ' . date('j M Y', strtotime($item['last_movement_at'])) : 'No movement yet') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $item['reorder_level'])) ?> <?= e($item['unit']) ?></strong>
                                    <span><?= e($item['used_in_service_names'] !== [] ? implode(' · ', array_slice($item['used_in_service_names'], 0, 2)) : 'Not linked to services') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $item['stock_value'])) ?></strong>
                                    <span><?= e(format_money((float) $item['cost_per_unit'])) ?> per <?= e($item['unit']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(badge_class($item['stock_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $item['stock_status']))) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/inventory/edit.php?id=<?= e($item['id']) ?>">Edit</a>
                                    <a href="/inventory/movements.php?item_id=<?= e($item['id']) ?>">Movements</a>
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
