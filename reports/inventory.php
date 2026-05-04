<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Report.php';

$currentUser = require_login();
require_permission('reports.view');

$filters = [
    'date_from' => (string) ($_GET['date_from'] ?? Report::defaultDateRange()['date_from']),
    'date_to' => (string) ($_GET['date_to'] ?? Report::defaultDateRange()['date_to']),
];

$report = Report::inventory($filters);

$pageTitle = 'Inventory Report';
$pageEyebrow = 'Stock health, movement flow, and supply risk';
$currentRoute = 'reports';
$reportRoute = 'reports.inventory';
$topbarAction = ['label' => 'Inventory list', 'href' => '/inventory/list.php', 'permission' => 'inventory.manage'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Inventory analytics</p>
                <h1 class="hero-title">Measure stock risk, movement activity, and where supplies are being consumed.</h1>
                <p class="hero-copy">This report surfaces current stock value, replenishment pressure, movement patterns, and the items most affected by usage or wastage so procurement stays ahead of service demand.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/inventory/list.php">Open inventory</a>
                    <a class="action-link is-secondary" href="/inventory/movements.php">Open movement history</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <?php foreach ($report['stats'] as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/reports-nav.php'; ?>

        <section class="filter-panel section-spaced">
            <form class="report-filter-grid" method="get">
                <label>
                    Date from
                    <input type="date" name="date_from" value="<?= e($report['filters']['date_from']) ?>">
                </label>
                <label>
                    Date to
                    <input type="date" name="date_to" value="<?= e($report['filters']['date_to']) ?>">
                </label>
                <div class="report-filter-actions">
                    <button class="topbar-cta" type="submit">Refresh report</button>
                    <a class="action-link is-secondary" href="/reports/inventory.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Movement profile</p>
                        <h3>What happened to stock</h3>
                    </div>
                    <p class="report-table-note">Movement counts and quantities across the selected window.</p>
                </div>

                <div class="report-list">
                    <?php foreach ($report['movement_type_rows'] as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['label']) ?></strong>
                                <span><?= e((string) $row['count']) ?> events</span>
                            </div>
                            <em><?= e(format_quantity((float) $row['quantity'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Low stock exposure</p>
                        <h3>Items closest to disruption</h3>
                    </div>
                    <p class="report-table-note">Reorder gaps help prioritize the next procurement pass.</p>
                </div>

                <div class="report-list">
                    <?php foreach (array_slice($report['low_stock_rows'], 0, 6) as $row): ?>
                        <article class="report-list-item">
                            <div>
                                <strong><?= e($row['name']) ?></strong>
                                <span><?= e(format_quantity((float) $row['on_hand'])) ?> <?= e($row['unit']) ?> on hand</span>
                            </div>
                            <em>Gap <?= e(format_quantity((float) $row['reorder_gap'])) ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Category value</p>
                    <h3>Where stock value is sitting now</h3>
                </div>
                <p class="report-table-note">Current-value view by inventory category, with low-stock counts included.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Items</th>
                        <th>Flagged</th>
                        <th>Stock value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['category_rows'] as $row): ?>
                        <tr>
                            <td><strong><?= e($row['category']) ?></strong></td>
                            <td><?= e((string) $row['item_count']) ?></td>
                            <td><?= e((string) $row['low_stock_count']) ?></td>
                            <td><?= e(format_money((float) $row['stock_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Consumption hotspots</p>
                    <h3>Items absorbing the most usage</h3>
                </div>
                <p class="report-table-note">Tracks usage, stock-out, and wastage events together.</p>
            </div>

            <?php if ($report['consumption_rows'] === []): ?>
                <p class="report-empty">No stock consumption movements were recorded in this date range.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>SKU</th>
                            <th>Events</th>
                            <th>Quantity</th>
                            <th>Latest date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['consumption_rows'] as $row): ?>
                            <tr>
                                <td><strong><?= e($row['item_name']) ?></strong></td>
                                <td><?= e($row['sku']) ?></td>
                                <td><?= e((string) $row['events']) ?></td>
                                <td><?= e(format_quantity((float) $row['quantity'])) ?></td>
                                <td><?= e(date('j M Y', strtotime($row['latest_date']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
