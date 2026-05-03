<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$itemId = (string) ($_GET['id'] ?? '');
$item = $itemId !== '' ? Inventory::find($itemId) : null;

if ($item === null) {
    redirect_to('/inventory/list.php');
}

$itemErrors = flash_get('inventory_item_errors', []);
$movementErrors = flash_get('inventory_movement_errors', []);
$flashMessage = flash_get('inventory_success');
$serviceOptions = Inventory::serviceOptions();
$recentMovements = Inventory::recentMovements($item['id']);
$selectedServices = old_input('used_in_services', $item['used_in_services']);
$selectedServices = is_array($selectedServices) ? $selectedServices : $item['used_in_services'];

$pageTitle = 'Edit Inventory Item';
$pageEyebrow = $item['sku'];
$currentRoute = 'inventory';
$topbarAction = ['label' => 'Movement history', 'href' => '/inventory/movements.php?item_id=' . urlencode($item['id'])];

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
                <p class="hero-eyebrow">Inventory item</p>
                <h1 class="hero-title"><?= e($item['name']) ?></h1>
                <p class="hero-copy"><?= e($item['notes'] !== '' ? $item['notes'] : 'No item notes recorded yet.') ?> This stock item is currently stored at <?= e($item['location']) ?> and managed under <?= e($item['category']) ?>.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/movements.php?item_id=<?= e($item['id']) ?>">View movements</a>
                    <a class="action-link is-secondary" href="/inventory/low-stock.php">Low-stock report</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(badge_class($item['stock_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $item['stock_status']))) ?></span>
                    <strong><?= e(format_quantity((float) $item['on_hand'])) ?> <?= e($item['unit']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Reorder point</span>
                    <strong><?= e(format_quantity((float) $item['reorder_level'])) ?> <?= e($item['unit']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Stock value</span>
                    <strong><?= e(format_money((float) $item['stock_value'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Item profile</p>
                        <h3>Update inventory details</h3>
                    </div>
                    <p>Metadata changes live here. Quantity changes should go through the movement form below so they remain auditable.</p>
                </div>

                <form class="module-form" method="post" action="/process/inventory-save.php">
                    <input type="hidden" name="form_action" value="item">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Item name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name', $item['name'])) ?>">
                            <?php if (isset($itemErrors['name'])): ?><small><?= e($itemErrors['name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>SKU</span>
                            <input type="text" name="sku" value="<?= e((string) old_input('sku', $item['sku'])) ?>">
                            <?php if (isset($itemErrors['sku'])): ?><small><?= e($itemErrors['sku']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <input type="text" name="category" value="<?= e((string) old_input('category', $item['category'])) ?>">
                            <?php if (isset($itemErrors['category'])): ?><small><?= e($itemErrors['category']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Unit</span>
                            <input type="text" name="unit" value="<?= e((string) old_input('unit', $item['unit'])) ?>">
                            <?php if (isset($itemErrors['unit'])): ?><small><?= e($itemErrors['unit']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Current quantity</span>
                            <input type="text" value="<?= e(format_quantity((float) $item['on_hand']) . ' ' . $item['unit']) ?>" readonly>
                        </label>
                        <label class="field">
                            <span>Reorder level</span>
                            <input type="number" min="0" step="0.01" name="reorder_level" value="<?= e((string) old_input('reorder_level', (string) $item['reorder_level'])) ?>">
                            <?php if (isset($itemErrors['reorder_level'])): ?><small><?= e($itemErrors['reorder_level']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Cost per unit</span>
                            <input type="number" min="0" step="0.01" name="cost_per_unit" value="<?= e((string) old_input('cost_per_unit', (string) $item['cost_per_unit'])) ?>">
                            <?php if (isset($itemErrors['cost_per_unit'])): ?><small><?= e($itemErrors['cost_per_unit']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Supplier</span>
                            <input type="text" name="supplier" value="<?= e((string) old_input('supplier', $item['supplier'])) ?>">
                        </label>
                        <label class="field">
                            <span>Storage location</span>
                            <input type="text" name="location" value="<?= e((string) old_input('location', $item['location'])) ?>">
                        </label>
                    </div>

                    <label class="field">
                        <span>Used in services</span>
                        <select name="used_in_services[]" multiple>
                            <?php foreach ($serviceOptions as $service): ?>
                                <option value="<?= e($service['id']) ?>" <?= in_array($service['id'], $selectedServices, true) ? 'selected' : '' ?>><?= e($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Notes</span>
                        <textarea name="notes" rows="4"><?= e((string) old_input('notes', $item['notes'])) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/inventory/list.php">Back</a>
                        <button class="button-primary" type="submit">Save changes</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Stock context</p>
                        <h3>Current position</h3>
                    </div>
                </div>

                <div class="detail-pairs detail-pairs-single">
                    <div><span>Status</span><strong><?= e(ucwords(str_replace('_', ' ', $item['stock_status']))) ?></strong><small><?= e($item['supplier'] !== '' ? $item['supplier'] : 'No supplier recorded') ?></small></div>
                    <div><span>Gap to reorder</span><strong><?= e(format_quantity((float) $item['reorder_gap'])) ?> <?= e($item['unit']) ?></strong><small><?= e($item['reorder_gap'] > 0 ? 'Needs replenishment attention' : 'Healthy above reorder level') ?></small></div>
                    <div><span>Linked services</span><strong><?= e($item['used_in_service_names'] !== [] ? implode(', ', $item['used_in_service_names']) : 'None linked') ?></strong><small>Operational exposure when stock runs low</small></div>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Record stock movement</p>
                    <h3>Adjust on-hand quantity</h3>
                </div>
                <p>Use this form for stock in, stock out, wastage, service usage, or full counted adjustments.</p>
            </div>

            <form class="module-form" method="post" action="/process/inventory-save.php">
                <input type="hidden" name="form_action" value="movement">
                <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                <input type="hidden" name="recorded_by" value="<?= e($currentUser['name'] ?? 'Admin panel') ?>">
                <input type="hidden" name="return_to" value="<?= e('/inventory/edit.php?id=' . urlencode($item['id'])) ?>">

                <div class="form-grid">
                    <label class="field">
                        <span>Movement date</span>
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
                        <input type="number" min="0" step="0.01" name="quantity" value="<?= e((string) old_input('quantity')) ?>" placeholder="For adjustment, enter the counted final stock">
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
                    <textarea name="reason" rows="3" placeholder="Why did this stock change happen?"><?= e((string) old_input('reason')) ?></textarea>
                    <?php if (isset($movementErrors['reason'])): ?><small><?= e($movementErrors['reason']) ?></small><?php endif; ?>
                </label>

                <div class="button-row">
                    <button class="button-primary" type="submit">Save movement</button>
                </div>
            </form>
        </section>

        <section class="activity-card activity-card-standalone">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Recent item activity</p>
                    <h3>Latest stock events</h3>
                </div>
            </div>

            <div class="info-list">
                <?php foreach ($recentMovements as $movement): ?>
                    <article class="info-item">
                        <strong><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?> · <?= e(format_quantity((float) $movement['quantity'])) ?> <?= e($item['unit']) ?></strong>
                        <p><?= e(date('D, j M Y', strtotime($movement['movement_date']))) ?> · <?= e($movement['reason']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
