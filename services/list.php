<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('services.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'all'),
];

$services = Service::all($filters);
$stats = Service::stats();
$flashMessage = flash_get('service_success');
$errors = flash_get('service_errors', []);

$pageTitle = 'Services';
$pageEyebrow = 'Service menu management';
$currentRoute = 'services';
$topbarAction = ['label' => 'New service', 'href' => '/services/create.php', 'permission' => 'services.create'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['service'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['service']) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Services management</p>
                <h1 class="hero-title">Control the massage menu without touching code.</h1>
                <p class="hero-copy">Set prices, durations, buffers, add-ons, and activation state so the booking flow always reflects the real studio offering.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/services/create.php">Create service</a>
                    <a class="action-link is-secondary" href="/bookings/create.php">Test in booking flow</a>
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
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/services/list.php">All</a>
                    <a class="<?= e(active_filter($filters['status'], 'active')) ?>" href="/services/list.php?status=active">Active</a>
                    <a class="<?= e(active_filter($filters['status'], 'inactive')) ?>" href="/services/list.php?status=inactive">Inactive</a>
                </div>

                <form class="inline-search" method="get" action="/services/list.php">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search service, category, room, or add-on">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Service catalog</p>
                    <h3>Current treatment menu</h3>
                </div>
                <p>Inactive services stay visible here for management, but they are hidden from new booking creation.</p>
            </div>

            <?php if ($services === []): ?>
                <div class="empty-state">
                    <strong>No services match the current filter.</strong>
                    <p>Try clearing the search or create a new service.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Category</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong><?= e($service['name']) ?></strong>
                                    <span><?= e($service['room']) ?> · <?= e($service['description']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($service['category']) ?></strong>
                                    <span><?= e(implode(' · ', array_slice($service['addons'], 0, 2))) ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $service['duration']) ?> min</strong>
                                    <span>Buffer <?= e((string) $service['buffer']) ?> min</span>
                                </td>
                                <td>
                                    <strong><?= e(format_money((float) $service['price'])) ?></strong>
                                    <span><?= e((string) $service['booking_count']) ?> bookings total</span>
                                </td>
                                <td>
                                    <strong><?= e((string) $service['upcoming_count']) ?> upcoming</strong>
                                    <span><?= e((string) $service['completed_count']) ?> completed</span>
                                </td>
                                <td><span class="<?= e($service['active'] ? 'badge badge-success' : 'badge badge-warning') ?>"><?= e($service['active'] ? 'Active' : 'Inactive') ?></span></td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a class="icon-action-button" href="/services/view.php?id=<?= e($service['id']) ?>" aria-label="View <?= e($service['name']) ?>" title="View">
                                            <?= action_icon_svg('view') ?>
                                        </a>
                                        <a class="icon-action-button" href="/services/edit.php?id=<?= e($service['id']) ?>" aria-label="Edit <?= e($service['name']) ?>" title="Edit">
                                            <?= action_icon_svg('edit') ?>
                                        </a>
                                        <?php if (($service['can_delete'] ?? false) === true): ?>
                                            <form
                                                class="inline-action-form inline-action-form-danger"
                                                method="post"
                                                action="/process/service-save.php"
                                                data-confirm-dialog-form
                                                data-confirm-title="Delete service?"
                                                data-confirm-message="Delete <?= e($service['name']) ?> permanently? This action cannot be undone."
                                                data-confirm-submit-label="Delete service"
                                            >
                                                <input type="hidden" name="action" value="delete_service">
                                                <input type="hidden" name="id" value="<?= e($service['id']) ?>">
                                                <button class="icon-action-button icon-action-button-danger" type="submit" aria-label="Delete <?= e($service['name']) ?>" title="Delete">
                                                    <?= action_icon_svg('delete') ?>
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
