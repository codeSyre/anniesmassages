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

$flashMessage = flash_get('customer_success');
$errors = flash_get('customer_errors', []);

$pageTitle = 'Customer Notes';
$pageEyebrow = $customer['name'];
$currentRoute = 'customers';
$topbarAction = ['label' => 'View profile', 'href' => '/customers/view.php?id=' . urlencode($customer['id'])];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['customer'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['customer']) ?></div>
        <?php endif; ?>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Private profile context</p>
                        <h3>Preferences and admin notes</h3>
                    </div>
                    <p>Use this area to store operational context that helps the team deliver smoother repeat experiences.</p>
                </div>

                <form class="module-form" method="post" action="/process/customer-save.php">
                    <input type="hidden" name="form_type" value="notes">
                    <input type="hidden" name="id" value="<?= e($customer['id']) ?>">

                    <label class="field">
                        <span>Preference</span>
                        <textarea name="preference" rows="4"><?= e($customer['preference']) ?></textarea>
                    </label>

                    <label class="field">
                        <span>Private admin notes</span>
                        <textarea name="admin_notes" rows="7"><?= e($customer['admin_notes']) ?></textarea>
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
                                list="customer-notes-tag-suggestions"
                                placeholder="Type a tag and press Enter"
                                autocomplete="off"
                                data-tag-editor-input
                            >
                        </div>
                        <datalist id="customer-notes-tag-suggestions">
                            <?php foreach ($suggestedTags as $tag): ?>
                                <option value="<?= e($tag) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <div class="button-row">
                        <button class="button-primary" type="submit">Save notes</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Profile quick view</p>
                        <h3><?= e($customer['name']) ?></h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e($customer['phone']) ?></strong>
                        <p><?= e($customer['email']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e((string) $customer['booking_count']) ?> bookings</strong>
                        <p>Total spend <?= e(format_money((float) $customer['total_spent'])) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e($customer['next_visit'] !== null ? date('D, j M Y', strtotime($customer['next_visit'])) : 'No upcoming visit') ?></strong>
                        <p><?= e($customer['location']) ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
