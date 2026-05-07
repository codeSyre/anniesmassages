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

$selectedTagsValue = (string) old_input('tags', implode(', ', $customer['tags']));
$selectedTags = array_values(array_unique(array_filter(array_map(
    static fn (string $tag): string => trim($tag),
    explode(',', $selectedTagsValue)
), static fn (string $tag): bool => $tag !== '')));
$suggestedTags = [];
foreach (Customer::all() as $candidateCustomer) {
    foreach (($candidateCustomer['tags'] ?? []) as $tag) {
        $trimmedTag = trim((string) $tag);

        if ($trimmedTag !== '') {
            $suggestedTags[strtolower($trimmedTag)] = $trimmedTag;
        }
    }
}
foreach ($selectedTags as $tag) {
    unset($suggestedTags[strtolower($tag)]);
}
$suggestedTags = array_values($suggestedTags);
sort($suggestedTags, SORT_NATURAL | SORT_FLAG_CASE);

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
                    <p>Keep contact details, source, preferences, and internal notes current so every future booking inherits the right context.</p>
                    </div>
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
                            <input type="hidden" name="tags" value="<?= e($selectedTagsValue) ?>" data-tag-editor-value>
                            <div class="tag-editor" data-tag-editor>
                                <div class="tag-editor-list" data-tag-editor-list>
                                    <?php foreach ($selectedTags as $tag): ?>
                                        <span class="tag-editor-chip" data-tag-editor-chip data-tag-value="<?= e($tag) ?>">
                                            <span><?= e($tag) ?></span>
                                            <button type="button" class="tag-editor-chip-remove" data-tag-editor-remove aria-label="Remove <?= e($tag) ?>">×</button>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <input
                                    type="text"
                                    class="tag-editor-input"
                                    list="customer-tag-suggestions"
                                    placeholder="Type a tag and press Enter"
                                    autocomplete="off"
                                    data-tag-editor-input
                                >
                            </div>
                            <datalist id="customer-tag-suggestions">
                                <?php foreach ($suggestedTags as $tag): ?>
                                    <option value="<?= e($tag) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
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
