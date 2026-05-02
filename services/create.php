<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$currentUser = require_login();
require_permission('services.create');

$errors = flash_get('service_errors', []);
$pageTitle = 'Create Service';
$pageEyebrow = 'New treatment offering';
$currentRoute = 'services';
$topbarAction = ['label' => 'Back to services', 'href' => '/services/list.php'];

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
                        <p class="section-kicker">Service setup</p>
                        <h3>Create a treatment</h3>
                    </div>
                    <p>Define the customer-facing offer and the operational rules the calendar and booking flow should follow.</p>
                </div>

                <form class="module-form" method="post" action="/process/service-save.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Service name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name')) ?>" placeholder="Treatment name">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <input type="text" name="category" value="<?= e((string) old_input('category', 'Massage')) ?>" placeholder="Massage, Signature, Wellness">
                        </label>
                        <label class="field">
                            <span>Price</span>
                            <input type="number" min="0" step="0.01" name="price" value="<?= e((string) old_input('price')) ?>" placeholder="0.00">
                            <?php if (isset($errors['price'])): ?><small><?= e($errors['price']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Duration (minutes)</span>
                            <input type="number" min="15" step="15" name="duration" value="<?= e((string) old_input('duration', '60')) ?>">
                            <?php if (isset($errors['duration'])): ?><small><?= e($errors['duration']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Buffer (minutes)</span>
                            <input type="number" min="0" step="5" name="buffer" value="<?= e((string) old_input('buffer', '15')) ?>">
                            <?php if (isset($errors['buffer'])): ?><small><?= e($errors['buffer']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Room / setup</span>
                            <input type="text" name="room" value="<?= e((string) old_input('room', 'Studio')) ?>" placeholder="Therapy room, Calm room">
                        </label>
                    </div>

                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="4" placeholder="What the guest receives and why this service exists..."><?= e((string) old_input('description')) ?></textarea>
                    </label>

                    <label class="field">
                        <span>Add-ons</span>
                        <input type="text" name="addons" value="<?= e((string) old_input('addons')) ?>" placeholder="Lavender oil, Scalp finish, Hot towel reset">
                    </label>

                    <label class="toggle-field">
                        <input type="checkbox" name="active" value="1" <?= old_input('active', '1') === '1' ? 'checked' : '' ?>>
                        <span>Service is active and available for new bookings</span>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/services/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save service</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Service rules</p>
                        <h3>Operational impact</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Duration drives scheduling</strong>
                        <p>The booking engine uses the configured duration to calculate occupied therapist time and available slots.</p>
                    </article>
                    <article class="info-item">
                        <strong>Inactive hides from intake</strong>
                        <p>Inactive services remain manageable here but drop out of new booking creation automatically.</p>
                    </article>
                    <article class="info-item">
                        <strong>Add-ons shape delivery</strong>
                        <p>Use add-ons to capture enhancements, prep needs, or premium touches the team should remember.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
