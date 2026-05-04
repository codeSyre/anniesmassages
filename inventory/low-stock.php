<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$inventoryItems = Inventory::lowStockItems();
$stats = Inventory::lowStockSummary();
$flashMessage = flash_get('inventory_success');

$pageTitle = 'Low Stock Report';
$pageEyebrow = 'Inventory risk view';
$currentRoute = 'inventory';
$topbarAction = ['label' => 'Record movement', 'href' => '/inventory/movements.php'];

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
                <p class="hero-eyebrow">Low-stock report</p>
                <h1 class="hero-title">See which supply items are closest to blocking operations.</h1>
                <p class="hero-copy">This report keeps the front desk and operations aligned on the items most likely to affect service delivery, room setup, or treatment quality.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/movements.php">Record restock</a>
                    <a class="action-link is-secondary" href="/inventory/list.php?status=low_stock">Open filtered inventory</a>
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
                    <p class="section-kicker">Priority replenishment</p>
                    <h3>Items needing attention</h3>
                </div>
                <p><?= e((string) count($inventoryItems)) ?> items are currently at or below their reorder point.</p>
            </div>

            <?php if ($inventoryItems === []): ?>
                <div class="empty-state">
                    <strong>No low-stock items right now.</strong>
                    <p>The current inventory position is healthy across all tracked items.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>On hand</th>
                            <th>Reorder level</th>
                            <th>Gap</th>
                            <th>Estimated top-up</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['name']) ?></strong>
                                    <span><?= e($item['category']) ?> · <?= e($item['location']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $item['on_hand'])) ?> <?= e($item['unit']) ?></strong>
                                    <span class="<?= e(badge_class($item['stock_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $item['stock_status']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $item['reorder_level'])) ?> <?= e($item['unit']) ?></strong>
                                    <span><?= e($item['supplier'] !== '' ? $item['supplier'] : 'No supplier recorded') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $item['reorder_gap'])) ?> <?= e($item['unit']) ?></strong>
                                    <span><?= e($item['used_in_service_names'] !== [] ? implode(' · ', array_slice($item['used_in_service_names'], 0, 2)) : 'No linked services') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $item['reorder_gap'] * (float) $item['cost_per_unit'])) ?></strong>
                                    <span><?= e(format_money((float) $item['cost_per_unit'])) ?> per <?= e($item['unit']) ?></span>
                                </td>
                                <td class="row-actions">
                                    <a href="/inventory/edit.php?id=<?= e($item['id']) ?>">Edit</a>
                                    <a href="/inventory/movements.php?item_id=<?= e($item['id']) ?>">Restock</a>
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
