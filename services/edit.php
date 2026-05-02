<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Service.php';

$currentUser = require_login();
require_permission('services.update');

$serviceId = (string) ($_GET['id'] ?? '');
$service = $serviceId !== '' ? Service::find($serviceId) : null;

if ($service === null) {
    redirect_to('/services/list.php');
}

$errors = flash_get('service_errors', []);
$pageTitle = 'Edit Service';
$pageEyebrow = $service['name'];
$currentRoute = 'services';
$topbarAction = ['label' => 'View service', 'href' => '/services/view.php?id=' . urlencode($service['id'])];

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
                        <p class="section-kicker">Service update</p>
                        <h3>Adjust menu and delivery rules</h3>
                    </div>
                    <p>Update pricing, duration, room needs, add-ons, and activation state without breaking historical booking records.</p>
                </div>

                <form class="module-form" method="post" action="/process/service-save.php">
                    <input type="hidden" name="id" value="<?= e($service['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Service name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name', $service['name'])) ?>">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <input type="text" name="category" value="<?= e((string) old_input('category', $service['category'])) ?>">
                        </label>
                        <label class="field">
                            <span>Price</span>
                            <input type="number" min="0" step="0.01" name="price" value="<?= e((string) old_input('price', (string) $service['price'])) ?>">
                            <?php if (isset($errors['price'])): ?><small><?= e($errors['price']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Duration (minutes)</span>
                            <input type="number" min="15" step="15" name="duration" value="<?= e((string) old_input('duration', (string) $service['duration'])) ?>">
                            <?php if (isset($errors['duration'])): ?><small><?= e($errors['duration']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Buffer (minutes)</span>
                            <input type="number" min="0" step="5" name="buffer" value="<?= e((string) old_input('buffer', (string) $service['buffer'])) ?>">
                            <?php if (isset($errors['buffer'])): ?><small><?= e($errors['buffer']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Room / setup</span>
                            <input type="text" name="room" value="<?= e((string) old_input('room', $service['room'])) ?>">
                        </label>
                    </div>

                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="4"><?= e((string) old_input('description', $service['description'])) ?></textarea>
                    </label>

                    <label class="field">
                        <span>Add-ons</span>
                        <input type="text" name="addons" value="<?= e((string) old_input('addons', implode(', ', $service['addons']))) ?>">
                    </label>

                    <label class="toggle-field">
                        <input type="checkbox" name="active" value="1" <?= old_input('active', $service['active'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
                        <span>Service is active and visible during booking creation</span>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/services/view.php?id=<?= e($service['id']) ?>">Cancel</a>
                        <button class="button-primary" type="submit">Update service</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Service summary</p>
                        <h3><?= e($service['name']) ?></h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e(format_money((float) $service['price'])) ?> · <?= e((string) $service['duration']) ?> min</strong>
                        <p><?= e($service['category']) ?> · buffer <?= e((string) $service['buffer']) ?> min</p>
                    </article>
                    <article class="info-item">
                        <strong><?= e((string) $service['booking_count']) ?> linked bookings</strong>
                        <p><?= e((string) $service['upcoming_count']) ?> upcoming · <?= e((string) $service['completed_count']) ?> completed</p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($service['active'] ? 'Active in intake' : 'Hidden from new bookings') ?></strong>
                        <p><?= e($service['room']) ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
