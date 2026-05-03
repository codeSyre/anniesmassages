<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('services.view');

$serviceId = (string) ($_GET['id'] ?? '');
$service = $serviceId !== '' ? Service::find($serviceId) : null;

if ($service === null) {
    redirect_to('/services/list.php');
}

$bookings = array_slice(Service::bookings($service['id']), 0, 6);
$flashMessage = flash_get('service_success');
$errors = flash_get('service_errors', []);

$pageTitle = 'Service Details';
$pageEyebrow = 'Services management';
$currentRoute = 'services';
$topbarAction = ['label' => 'Edit service', 'href' => '/services/edit.php?id=' . urlencode($service['id'])];

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
                <p class="hero-eyebrow">Service profile</p>
                <h1 class="hero-title"><?= e($service['name']) ?></h1>
                <p class="hero-copy"><?= e($service['description']) ?> This service is currently <?= e($service['active'] ? 'active and visible in booking creation.' : 'inactive and hidden from new booking creation.') ?></p>

                <div class="hero-actions">
                    <a class="action-link" href="/services/edit.php?id=<?= e($service['id']) ?>">Edit service</a>
                    <a class="action-link is-secondary" href="/services/list.php">Back to services</a>
                    <?php if (($service['can_delete'] ?? false) === true): ?>
                        <form
                            class="hero-action-form"
                            method="post"
                            action="/process/service-save.php"
                            data-confirm-dialog-form
                            data-confirm-title="Delete service?"
                            data-confirm-message="Delete <?= e($service['name']) ?> permanently? This action cannot be undone."
                            data-confirm-submit-label="Delete service"
                        >
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="id" value="<?= e($service['id']) ?>">
                            <button class="button-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e($service['active'] ? 'badge badge-success' : 'badge badge-warning') ?>"><?= e($service['active'] ? 'Active' : 'Inactive') ?></span>
                    <strong><?= e(format_money((float) $service['price'])) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Duration</span>
                    <strong><?= e((string) $service['duration']) ?> min</strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Revenue linked</span>
                    <strong><?= e(format_money((float) $service['revenue'])) ?></strong>
                </article>
            </aside>
        </section>

        <section class="detail-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Service definition</p>
                        <h3>Operational details</h3>
                    </div>
                </div>

                <div class="detail-pairs">
                    <div><span>Category</span><strong><?= e($service['category']) ?></strong><small>Customer-facing grouping</small></div>
                    <div><span>Room</span><strong><?= e($service['room']) ?></strong><small>Primary delivery space</small></div>
                    <div><span>Duration</span><strong><?= e((string) $service['duration']) ?> minutes</strong><small>Buffer <?= e((string) $service['buffer']) ?> minutes</small></div>
                    <div><span>Booking usage</span><strong><?= e((string) $service['booking_count']) ?> total</strong><small><?= e((string) $service['upcoming_count']) ?> upcoming</small></div>
                </div>

                <div class="tag-row">
                    <?php foreach ($service['addons'] as $addon): ?>
                        <span class="tag-pill"><?= e($addon) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Recent usage</p>
                        <h3>Latest linked bookings</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($bookings as $booking): ?>
                        <article class="info-item">
                            <strong><?= e($booking['customer']['name']) ?></strong>
                            <p><?= e(date('D, j M Y', strtotime($booking['date']))) ?> · <?= e($booking['time']) ?> with <?= e($booking['staff']['name']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
