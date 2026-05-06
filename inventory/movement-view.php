<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Inventory.php';

$currentUser = require_login();
require_permission('inventory.manage');

$movementId = (string) ($_GET['id'] ?? '');
$movement = $movementId !== '' ? Inventory::findMovement($movementId) : null;

if ($movement === null) {
    redirect_to('/inventory/movements.php');
}

$item = Inventory::find((string) $movement['item_id']);
$recentItemMovements = $item !== null ? Inventory::recentMovements((string) $item['id'], 6) : [];
$relatedMovements = array_values(array_filter(
    $recentItemMovements,
    static fn (array $entry): bool => (string) ($entry['id'] ?? '') !== (string) $movement['id']
));

$pageTitle = 'Movement Details';
$pageEyebrow = (string) $movement['reference'];
$currentRoute = 'inventory';
$topbarActions = [
    ['label' => 'Back to ledger', 'href' => '/inventory/movements.php', 'permission' => 'inventory.manage'],
];

if ($item !== null) {
    $topbarActions[] = ['label' => 'Open item', 'href' => '/inventory/edit.php?id=' . urlencode((string) $item['id']), 'permission' => 'inventory.manage'];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Movement entry</p>
                <h1 class="hero-title"><?= e($movement['reference']) ?></h1>
                <p class="hero-copy">
                    <?= e($movement['item_name']) ?> was recorded as <?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?>
                    on <?= e(date('D, j M Y', strtotime((string) $movement['movement_date']))) ?>.
                    <?= e($movement['reason']) ?>
                </p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/movements.php">Back to ledger</a>
                    <?php if ($item !== null): ?>
                        <a class="action-link is-secondary" href="/inventory/edit.php?id=<?= e($item['id']) ?>">Open item</a>
                    <?php endif; ?>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(badge_class($movement['type_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?></span>
                    <strong><?= e(format_quantity((float) $movement['quantity'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Stock change</span>
                    <strong><?= e(($movement['delta'] >= 0 ? '+' : '') . format_quantity((float) $movement['delta'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Movement date</span>
                    <strong><?= e(date('D, j M Y', strtotime((string) $movement['movement_date']))) ?></strong>
                </article>
            </aside>
        </section>

        <section class="module-stat-grid module-stat-grid-quad">
            <article class="mini-stat-card">
                <span class="<?= e(badge_class($movement['type_tone'])) ?>"><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?></span>
                <strong><?= e(format_quantity((float) $movement['quantity'])) ?></strong>
            </article>
            <article class="mini-stat-card">
                <span class="badge badge-info">Stock change</span>
                <strong><?= e(($movement['delta'] >= 0 ? '+' : '') . format_quantity((float) $movement['delta'])) ?></strong>
            </article>
            <article class="mini-stat-card">
                <span class="badge badge-success">Before</span>
                <strong><?= e(format_quantity((float) $movement['before_quantity'])) ?></strong>
            </article>
            <article class="mini-stat-card">
                <span class="badge badge-warning">After</span>
                <strong><?= e(format_quantity((float) $movement['after_quantity'])) ?></strong>
            </article>
        </section>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Movement breakdown</p>
                        <h3>Recorded details</h3>
                    </div>
                    <p>This entry is part of the inventory audit trail and reflects the exact stock change that was recorded.</p>
                </div>

                <div class="detail-pairs detail-pairs-single">
                    <div>
                        <span>Item</span>
                        <strong><?= e($movement['item_name']) ?></strong>
                        <small><?= e($movement['sku']) ?></small>
                    </div>
                    <div>
                        <span>Recorded by</span>
                        <strong><?= e($movement['recorded_by']) ?></strong>
                        <small><?= e(date('D, j M Y', strtotime((string) $movement['movement_date']))) ?></small>
                    </div>
                    <div>
                        <span>Movement reference</span>
                        <strong><?= e($movement['reference']) ?></strong>
                        <small>Ledger entry identifier</small>
                    </div>
                    <div>
                        <span>Service link</span>
                        <strong><?= e($movement['service_name'] !== '' ? $movement['service_name'] : 'No service link') ?></strong>
                        <small><?= e(ucwords(str_replace('_', ' ', $movement['type']))) ?></small>
                    </div>
                    <div>
                        <span>Before and after</span>
                        <strong><?= e(format_quantity((float) $movement['before_quantity'])) ?> -> <?= e(format_quantity((float) $movement['after_quantity'])) ?></strong>
                        <small><?= e(($movement['delta'] >= 0 ? '+' : '') . format_quantity((float) $movement['delta'])) ?></small>
                    </div>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Item context</p>
                        <h3>Current inventory position</h3>
                    </div>
                </div>

                <div class="detail-pairs detail-pairs-single">
                    <div>
                        <span>Item</span>
                        <strong><?= e($item['name'] ?? $movement['item_name']) ?></strong>
                        <small><?= e($movement['sku']) ?></small>
                    </div>
                    <div>
                        <span>Category</span>
                        <strong><?= e((string) ($item['category'] ?? 'Unknown')) ?></strong>
                        <small><?= e((string) ($item['location'] ?? 'No location recorded')) ?></small>
                    </div>
                    <div>
                        <span>On hand now</span>
                        <strong><?= e(format_quantity((float) ($item['on_hand'] ?? $movement['after_quantity']))) ?> <?= e((string) ($item['unit'] ?? 'units')) ?></strong>
                        <small><?= e((string) ($item['supplier'] ?? 'No supplier recorded')) ?></small>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong><?= e(ucwords(str_replace('_', ' ', (string) ($item['stock_status'] ?? 'unknown')))) ?></strong>
                        <small><?= e('Reorder at ' . format_quantity((float) ($item['reorder_level'] ?? 0))) ?></small>
                    </div>
                </div>
            </aside>
        </section>

        <section class="split-layout section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Reason log</p>
                        <h3>Why this movement was posted</h3>
                    </div>
                    <p>Use this note to understand what operational event triggered the stock change.</p>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e($movement['reference']) ?></strong>
                        <p><?= e($movement['reason']) ?></p>
                    </article>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Related activity</p>
                        <h3>Recent movements for this item</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php if ($relatedMovements === []): ?>
                        <article class="info-item">
                            <strong>No other movements yet.</strong>
                            <p>This is the only recorded ledger entry for this inventory item so far.</p>
                        </article>
                    <?php else: ?>
                        <?php foreach (array_slice($relatedMovements, 0, 4) as $entry): ?>
                            <article class="info-item">
                                <strong><?= e($entry['reference']) ?> · <?= e(ucwords(str_replace('_', ' ', $entry['type']))) ?></strong>
                                <p><?= e(date('D, j M Y', strtotime((string) $entry['movement_date']))) ?> · <?= e(($entry['delta'] >= 0 ? '+' : '') . format_quantity((float) $entry['delta'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
