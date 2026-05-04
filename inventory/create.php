<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$errors = flash_get('inventory_item_errors', []);
$serviceOptions = Inventory::serviceOptions();
$categories = Inventory::categories();
$locations = Inventory::locations();
$selectedServices = old_input('used_in_services', []);
$selectedServices = is_array($selectedServices) ? $selectedServices : [];

$pageTitle = 'Create Inventory Item';
$pageEyebrow = 'New stock item';
$currentRoute = 'inventory';
$topbarAction = ['label' => 'Back to inventory', 'href' => '/inventory/list.php'];

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
                        <p class="section-kicker">Inventory setup</p>
                        <h3>Create a stock item</h3>
                    </div>
                    <p>Start with the item profile, opening quantity, reorder point, and service links so low-stock warnings can become operational quickly.</p>
                </div>

                <form class="module-form" method="post" action="/process/inventory-save.php">
                    <input type="hidden" name="form_action" value="item">

                    <div class="form-grid">
                        <label class="field">
                            <span>Item name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name')) ?>" placeholder="Lavender Treatment Oil">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>SKU</span>
                            <input type="text" name="sku" value="<?= e((string) old_input('sku')) ?>" placeholder="INV-1006">
                            <?php if (isset($errors['sku'])): ?><small><?= e($errors['sku']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <input type="text" name="category" value="<?= e((string) old_input('category', 'Consumables')) ?>" list="category-options" autocomplete="off">
                            <datalist id="category-options">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['category'])): ?><small><?= e($errors['category']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Unit</span>
                            <input type="text" name="unit" value="<?= e((string) old_input('unit', 'units')) ?>" placeholder="bottles, jars, sets">
                            <?php if (isset($errors['unit'])): ?><small><?= e($errors['unit']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Opening quantity</span>
                            <input type="number" min="0" step="0.01" name="quantity" value="<?= e((string) old_input('quantity', '0')) ?>">
                            <?php if (isset($errors['quantity'])): ?><small><?= e($errors['quantity']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Reorder level</span>
                            <input type="number" min="0" step="0.01" name="reorder_level" value="<?= e((string) old_input('reorder_level', '0')) ?>">
                            <?php if (isset($errors['reorder_level'])): ?><small><?= e($errors['reorder_level']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Cost per unit</span>
                            <input type="number" min="0" step="0.01" name="cost_per_unit" value="<?= e((string) old_input('cost_per_unit', '0')) ?>">
                            <?php if (isset($errors['cost_per_unit'])): ?><small><?= e($errors['cost_per_unit']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Supplier</span>
                            <input type="text" name="supplier" value="<?= e((string) old_input('supplier')) ?>" placeholder="Supplier name">
                        </label>
                        <label class="field">
                            <span>Storage location</span>
                            <input type="text" name="location" value="<?= e((string) old_input('location', 'Main storage')) ?>" placeholder="Shelf, room, cabinet">
                        </label>
                    </div>

                    <fieldset class="field field-checkgroup">
                        <legend>Used in services</legend>
                        <?php if ($serviceOptions === []): ?>
                            <p class="field-empty-note">No services created yet.</p>
                        <?php else: ?>
                            <?php foreach ($serviceOptions as $service): ?>
                                <label class="check-item">
                                    <input type="checkbox" name="used_in_services[]" value="<?= e($service['id']) ?>" <?= in_array($service['id'], $selectedServices, true) ? 'checked' : '' ?>>
                                    <span><?= e($service['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </fieldset>

                    <label class="field">
                        <span>Notes</span>
                        <textarea name="notes" rows="4" placeholder="Storage risks, usage pattern, handling instructions..."><?= e((string) old_input('notes')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/inventory/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save item</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Inventory rules</p>
                        <h3>How this module behaves</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Opening stock can seed the ledger</strong>
                        <p>If you create the item with quantity on hand, the system records that as the initial stock-in event.</p>
                    </article>
                    <article class="info-item">
                        <strong>Reorder levels drive alerts</strong>
                        <p>Anything at or below the reorder threshold will show up in the low-stock report automatically.</p>
                    </article>
                    <article class="info-item">
                        <strong>Service links add context</strong>
                        <p>Link items to services so the team understands what treatments are most exposed when stock runs low.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
