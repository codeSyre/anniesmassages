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

$inventoryItems = Inventory::all();
$inventoryItemLabels = [];
foreach ($inventoryItems as $item) {
    $inventoryItemLabels[(string) $item['id']] = $item['name'] . ' · ' . format_quantity((float) $item['on_hand']) . ' ' . $item['unit'];
}

if ($filters['item_id'] === '' || ($filters['item_id'] !== 'all' && !isset($inventoryItemLabels[$filters['item_id']]))) {
    $filters['item_id'] = 'all';
}

if ($filters['type'] === '' || !in_array($filters['type'], array_merge(['all'], Inventory::movementTypes()), true)) {
    $filters['type'] = 'all';
}

$hasMovementHistory = Inventory::movements() !== [];
$allMovements = Inventory::movements($filters);
$perPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalMovements = count($allMovements);
$totalPages = max(1, (int) ceil($totalMovements / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$movements = array_slice($allMovements, $offset, $perPage);

if ($totalMovements > 0 && $movements === []) {
    $currentPage = 1;
    $offset = 0;
    $movements = array_slice($allMovements, 0, $perPage);
}

$visibleStart = $totalMovements > 0 ? $offset + 1 : 0;
$visibleEnd = min($offset + $perPage, $totalMovements);
$stats = Inventory::movementStats();
$selectedMovementItemId = (string) old_input('item_id', $filters['item_id'] !== 'all' ? $filters['item_id'] : '');
$selectedMovementItemLabel = $selectedMovementItemId !== '' ? ($inventoryItemLabels[$selectedMovementItemId] ?? '') : '';
$selectedFilterItemId = $filters['item_id'];
$selectedFilterItemLabel = $selectedFilterItemId === 'all'
    ? 'All items'
    : ($inventoryItemLabels[$selectedFilterItemId] ?? '');
$serviceOptions = Inventory::serviceOptions();
$serviceLabels = [];
foreach ($serviceOptions as $service) {
    $serviceLabels[(string) $service['id']] = (string) $service['name'];
}
$selectedMovementServiceId = (string) old_input('service_id', '');
$selectedMovementServiceValue = $selectedMovementServiceId !== '' ? $selectedMovementServiceId : '__none__';
$selectedMovementServiceLabel = $selectedMovementServiceId !== ''
    ? ($serviceLabels[$selectedMovementServiceId] ?? '')
    : 'No service link';
$movementErrors = flash_get('inventory_movement_errors', []);
$flashMessage = flash_get('inventory_success');

$pageTitle = 'Stock Movements';
$pageEyebrow = 'Inventory movement history';
$currentRoute = 'inventory';

if ($hasMovementHistory) {
    $topbarActions = [
        ['label' => 'Add inventory item', 'href' => '/inventory/create.php', 'permission' => 'inventory.manage'],
        ['label' => 'Inventory list', 'href' => '/inventory/list.php', 'permission' => 'inventory.manage'],
        ['label' => 'Low-stock report', 'href' => '/inventory/low-stock.php', 'permission' => 'inventory.manage'],
    ];
} else {
    $topbarAction = ['label' => 'Add inventory item', 'href' => '/inventory/create.php', 'permission' => 'inventory.manage'];
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

        <?php if (isset($movementErrors['movement'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($movementErrors['movement']) ?></div>
        <?php endif; ?>

        <section class="module-hero<?= $hasMovementHistory ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasMovementHistory): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Stock movement history</p>
                    <h1 class="hero-title">See every stock change that moved inventory in or out.</h1>
                    <p class="hero-copy">Movements make inventory auditable, which means low-stock warnings, service usage, and manual adjustments all have a visible trail.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/inventory/list.php">Inventory list</a>
                        <a class="action-link is-secondary" href="/inventory/low-stock.php">Low-stock report</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasMovementHistory ? ' module-stat-grid-quad' : '' ?>">
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
                    <p>Every stock adjustment should come through here so the history remains complete.</p>
                    </div>
                </div>

                <form class="module-form" method="post" action="/process/inventory-save.php">
                    <input type="hidden" name="form_action" value="movement">
                    <input type="hidden" name="recorded_by" value="<?= e($currentUser['name'] ?? 'Admin panel') ?>">
                    <input type="hidden" name="return_to" value="<?= e('/inventory/movements.php') ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Item</span>
                            <input type="hidden" name="item_id" id="movement-item-value" value="<?= e($selectedMovementItemId) ?>">
                            <input
                                type="text"
                                id="movement-item-search"
                                value="<?= e($selectedMovementItemLabel) ?>"
                                list="movement-item-options"
                                autocomplete="off"
                                placeholder="Select item"
                                data-searchable-select-input
                                data-searchable-select-target="movement-item-value"
                                data-searchable-select-empty-message="Select a valid inventory item from the list."
                            >
                            <datalist id="movement-item-options">
                                <?php foreach ($inventoryItems as $item): ?>
                                    <option value="<?= e($inventoryItemLabels[(string) $item['id']]) ?>" data-searchable-select-id="<?= e($item['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
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
                            <input type="hidden" name="service_id" id="movement-service-value" value="<?= e($selectedMovementServiceValue) ?>">
                            <input
                                type="text"
                                id="movement-service-search"
                                value="<?= e($selectedMovementServiceLabel) ?>"
                                list="movement-service-options"
                                autocomplete="off"
                                placeholder="No service link"
                                data-searchable-select-input
                                data-searchable-select-target="movement-service-value"
                                data-searchable-select-empty-message="Select a valid service option from the list."
                            >
                            <datalist id="movement-service-options">
                                <option value="No service link" data-searchable-select-id="__none__"></option>
                                <?php foreach ($serviceOptions as $service): ?>
                                    <option value="<?= e($service['name']) ?>" data-searchable-select-id="<?= e($service['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
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
                        <input type="hidden" name="item_id" id="movement-filter-item-value" value="<?= e($selectedFilterItemId) ?>">
                        <input
                            type="text"
                            id="movement-filter-item-search"
                            value="<?= e($selectedFilterItemLabel) ?>"
                            list="movement-filter-item-options"
                            autocomplete="off"
                            placeholder="All items"
                            data-searchable-select-input
                            data-searchable-select-target="movement-filter-item-value"
                            data-searchable-select-empty-message="Select a valid inventory item from the list."
                        >
                        <datalist id="movement-filter-item-options">
                            <option value="All items" data-searchable-select-id="all"></option>
                            <?php foreach ($inventoryItems as $item): ?>
                                <option value="<?= e($inventoryItemLabels[(string) $item['id']]) ?>" data-searchable-select-id="<?= e($item['id']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
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
                <p>
                    <?php if ($totalMovements > 0): ?>
                        Showing <?= e((string) $visibleStart) ?>-<?= e((string) $visibleEnd) ?> of <?= e((string) $totalMovements) ?> entries matched the current filters.
                    <?php else: ?>
                        0 entries matched the current filters.
                    <?php endif; ?>
                </p>
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <strong><?= e($movement['reference']) ?></strong>
                                    <span><?= e(date('D, j M Y', strtotime($movement['movement_date']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($movement['item_name']) ?></strong>
                                    <span><?= e($movement['sku']) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e(badge_class($movement['type_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $movement['quantity'])) ?></strong>
                                </td>
                                <td>
                                    <strong><?= e(format_quantity((float) $movement['before_quantity'])) ?> → <?= e(format_quantity((float) $movement['after_quantity'])) ?></strong>
                                    <span><?= e(($movement['delta'] >= 0 ? '+' : '') . format_quantity((float) $movement['delta'])) ?></span>
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/inventory/movement-view.php?id=<?= e($movement['id']) ?>" aria-label="View <?= e($movement['reference']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <?php
                    $pageBaseParams = [
                        'search' => $filters['search'],
                        'item_id' => $filters['item_id'],
                        'type' => $filters['type'],
                        'date_from' => $filters['date_from'],
                        'date_to' => $filters['date_to'],
                    ];
                    $previousHref = '/inventory/movements.php?' . http_build_query($pageBaseParams + ['page' => $currentPage - 1]);
                    $nextHref = '/inventory/movements.php?' . http_build_query($pageBaseParams + ['page' => $currentPage + 1]);
                    ?>
                    <div class="button-row list-pagination">
                        <?php if ($currentPage > 1): ?>
                            <a class="button-muted" href="<?= e($previousHref) ?>">Previous</a>
                        <?php else: ?>
                            <span class="button-muted button-muted-disabled" aria-disabled="true">Previous</span>
                        <?php endif; ?>

                        <span>Page <?= e((string) $currentPage) ?> of <?= e((string) $totalPages) ?></span>

                        <?php if ($currentPage < $totalPages): ?>
                            <a class="button-muted" href="<?= e($nextHref) ?>">Next</a>
                        <?php else: ?>
                            <span class="button-muted button-muted-disabled" aria-disabled="true">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
