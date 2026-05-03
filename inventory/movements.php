<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'item_id' => (string) ($_GET['item_id'] ?? 'all'),
    'type' => (string) ($_GET['type'] ?? 'all'),
    'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
    'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-d')),
];

$movements = Inventory::movements($filters);
$stats = Inventory::movementStats();
$items = Inventory::all();
$serviceOptions = Inventory::serviceOptions();
$movementErrors = flash_get('inventory_movement_errors', []);
$flashMessage = flash_get('inventory_success');

$pageTitle = 'Stock Movements';
$pageEyebrow = 'Inventory movement history';
$currentRoute = 'inventory';
$topbarAction = ['label' => 'Add inventory item', 'href' => '/inventory/create.php'];

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
                <p class="hero-eyebrow">Stock movement history</p>
                <h1 class="hero-title">See every stock change that moved inventory in or out.</h1>
                <p class="hero-copy">Movements make inventory auditable, which means low-stock warnings, service usage, and manual adjustments all have a visible trail.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/list.php">Inventory list</a>
                    <a class="action-link is-secondary" href="/inventory/low-stock.php">Low-stock report</a>
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

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Record movement</p>
                        <h3>Post a stock change</h3>
                    </div>
                    <p>Every stock adjustment should come through here so the history remains complete.</p>
                </div>

                <form class="module-form" method="post" action="/process/inventory-save.php">
                    <input type="hidden" name="form_action" value="movement">
                    <input type="hidden" name="recorded_by" value="<?= e($currentUser['name'] ?? 'Admin panel') ?>">
                    <input type="hidden" name="return_to" value="<?= e('/inventory/movements.php') ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Item</span>
                            <select name="item_id">
                                <option value="">Select item</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= e($item['id']) ?>" <?= (string) old_input('item_id', $filters['item_id'] !== 'all' ? $filters['item_id'] : '') === $item['id'] ? 'selected' : '' ?>><?= e($item['name'] . ' · ' . format_quantity((float) $item['on_hand']) . ' ' . $item['unit']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($movementErrors['item_id'])): ?><small><?= e($movementErrors['item_id']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="movement_date" value="<?= e((string) old_input('movement_date', date('Y-m-d'))) ?>">
                            <?php if (isset($movementErrors['movement_date'])): ?><small><?= e($movementErrors['movement_date']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Type</span>
                            <select name="type">
                                <?php foreach (Inventory::movementTypes() as $type): ?>
                                    <option value="<?= e($type) ?>" <?= (string) old_input('type', 'stock_in') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($movementErrors['type'])): ?><small><?= e($movementErrors['type']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Quantity</span>
                            <input type="number" min="0" step="0.01" name="quantity" value="<?= e((string) old_input('quantity')) ?>">
                            <?php if (isset($movementErrors['quantity'])): ?><small><?= e($movementErrors['quantity']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Service link</span>
                            <select name="service_id">
                                <option value="">No service link</option>
                                <?php foreach ($serviceOptions as $service): ?>
                                    <option value="<?= e($service['id']) ?>" <?= (string) old_input('service_id') === $service['id'] ? 'selected' : '' ?>><?= e($service['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($movementErrors['service_id'])): ?><small><?= e($movementErrors['service_id']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Reason</span>
                        <textarea name="reason" rows="3"><?= e((string) old_input('reason')) ?></textarea>
                        <?php if (isset($movementErrors['reason'])): ?><small><?= e($movementErrors['reason']) ?></small><?php endif; ?>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Save movement</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Filter history</p>
                        <h3>Trace movement patterns</h3>
                    </div>
                </div>

                <form class="module-form" method="get" action="/inventory/movements.php">
                    <label class="field">
                        <span>Search</span>
                        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Reference, item, reason, staff">
                    </label>
                    <label class="field">
                        <span>Item</span>
                        <select name="item_id">
                            <option value="all">All items</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= e($item['id']) ?>" <?= $filters['item_id'] === $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Type</span>
                        <select name="type">
                            <option value="all">All types</option>
                            <?php foreach (Inventory::movementTypes() as $type): ?>
                                <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="form-grid">
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
                        <a class="button-muted" href="/inventory/movements.php">Clear</a>
                        <button class="button-primary" type="submit">Apply</button>
                    </div>
                </form>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Movement ledger</p>
                    <h3>Recorded stock events</h3>
                </div>
                <p><?= e((string) count($movements)) ?> entries matched the current filters.</p>
            </div>

            <?php if ($movements === []): ?>
                <div class="empty-state">
                    <strong>No stock movements matched the current filters.</strong>
                    <p>Try a wider date range or record the next movement above.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Movement</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Stock change</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <strong><?= e($movement['reference']) ?></strong>
                                    <span><?= e(date('D, j M Y', strtotime($movement['movement_date']))) ?> · <?= e($movement['recorded_by']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($movement['item_name']) ?></strong>
                                    <span><?= e($movement['sku']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(badge_class($movement['type_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?></span>
                                    <span><?= e($movement['service_name'] !== '' ? $movement['service_name'] : 'No service link') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $movement['quantity'])) ?></strong>
                                    <span><?= e($movement['type'] === 'adjustment' ? 'Counted final quantity' : 'Units moved') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $movement['before_quantity'])) ?> → <?= e(format_quantity((float) $movement['after_quantity'])) ?></strong>
                                    <span><?= e(($movement['delta'] >= 0 ? '+' : '') . format_quantity((float) $movement['delta'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($movement['reason']) ?></strong>
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
