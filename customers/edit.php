<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

$currentUser = require_login();
require_permission('customers.update');

$customerId = (string) ($_GET['id'] ?? '');
$customer = $customerId !== '' ? Customer::find($customerId) : null;

if ($customer === null) {
    redirect_to('/customers/list.php');
}

$errors = flash_get('customer_errors', []);
$pageTitle = 'Edit Customer';
$pageEyebrow = $customer['name'];
$currentRoute = 'customers';
$topbarAction = ['label' => 'View profile', 'href' => '/customers/view.php?id=' . urlencode($customer['id'])];

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
                        <p class="section-kicker">Profile update</p>
                        <h3>Adjust customer details</h3>
                    </div>
                    <p>Keep contact details, source, preferences, and internal notes current so every future booking inherits the right context.</p>
                </div>

                <?php if (isset($errors['customer'])): ?>
                    <p class="inline-error"><?= e($errors['customer']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/customer-save.php">
                    <input type="hidden" name="form_type" value="profile">
                    <input type="hidden" name="id" value="<?= e($customer['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>First name</span>
                            <input type="text" name="first_name" value="<?= e((string) old_input('first_name', $customer['first_name'])) ?>">
                            <?php if (isset($errors['first_name'])): ?><small><?= e($errors['first_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Last name</span>
                            <input type="text" name="last_name" value="<?= e((string) old_input('last_name', $customer['last_name'])) ?>">
                            <?php if (isset($errors['last_name'])): ?><small><?= e($errors['last_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Phone</span>
                            <input type="text" name="phone" value="<?= e((string) old_input('phone', $customer['phone'])) ?>">
                            <?php if (isset($errors['phone'])): ?><small><?= e($errors['phone']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e((string) old_input('email', $customer['email'])) ?>">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Location</span>
                            <input type="text" name="location" value="<?= e((string) old_input('location', $customer['location'])) ?>">
                        </label>
                        <label class="field">
                            <span>Tags</span>
                            <input type="text" name="tags" value="<?= e((string) old_input('tags', implode(', ', $customer['tags']))) ?>">
                        </label>
                    </div>

                    <label class="field">
                        <span>Preference</span>
                        <textarea name="preference" rows="3"><?= e((string) old_input('preference', $customer['preference'])) ?></textarea>
                    </label>

                    <label class="field">
                        <span>Private admin notes</span>
                        <textarea name="admin_notes" rows="5"><?= e((string) old_input('admin_notes', $customer['admin_notes'])) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/customers/view.php?id=<?= e($customer['id']) ?>">Cancel</a>
                        <button class="button-primary" type="submit">Update customer</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Profile summary</p>
                        <h3><?= e($customer['name']) ?></h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e((string) $customer['booking_count']) ?> bookings on record</strong>
                        <p>Total spend <?= e(format_money((float) $customer['total_spent'])) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($customer['next_visit'] !== null ? date('D, j M Y', strtotime($customer['next_visit'])) : 'No upcoming booking') ?></strong>
                        <p><?= e($customer['location']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($customer['preference'] !== '' ? $customer['preference'] : 'No preference saved') ?></strong>
                        <p><?= e(implode(' · ', $customer['tags'])) ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
