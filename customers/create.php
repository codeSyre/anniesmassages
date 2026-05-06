<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Customer.php';

$currentUser = require_login();
require_permission('customers.create');

$selectedTagsValue = (string) old_input('tags');
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
$pageTitle = 'Create Customer';
$pageEyebrow = 'New guest record';
$currentRoute = 'customers';
$topbarAction = ['label' => 'Back to customers', 'href' => '/customers/list.php'];

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
                        <p class="section-kicker">Customer intake</p>
                        <h3>Create a guest profile</h3>
                    </div>
                    <p>Capture the basics now, then enrich the profile with preferences, notes, and history over time.</p>
                </div>

                <?php if (isset($errors['customer'])): ?>
                    <p class="inline-error"><?= e($errors['customer']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/customer-save.php">
                    <input type="hidden" name="form_type" value="profile">

                    <div class="form-grid">
                        <label class="field">
                            <span>First name</span>
                            <input type="text" name="first_name" value="<?= e((string) old_input('first_name')) ?>" placeholder="Guest first name">
                            <?php if (isset($errors['first_name'])): ?><small><?= e($errors['first_name']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Last name</span>
                            <input type="text" name="last_name" value="<?= e((string) old_input('last_name')) ?>" placeholder="Guest last name">
                            <?php if (isset($errors['last_name'])): ?><small><?= e($errors['last_name']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Phone</span>
                            <input type="text" name="phone" value="<?= e((string) old_input('phone')) ?>" placeholder="+263 ...">
                            <?php if (isset($errors['phone'])): ?><small><?= e($errors['phone']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e((string) old_input('email')) ?>" placeholder="guest@example.com">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Location</span>
                            <input type="text" name="location" value="<?= e((string) old_input('location', 'Harare')) ?>" placeholder="Neighborhood or area">
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
                                    list="customer-create-tag-suggestions"
                                    placeholder="Type a tag and press Enter"
                                    autocomplete="off"
                                    data-tag-editor-input
                                >
                            </div>
                            <datalist id="customer-create-tag-suggestions">
                                <?php foreach ($suggestedTags as $tag): ?>
                                    <option value="<?= e($tag) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                    </div>

                    <label class="field">
                        <span>Preference</span>
                        <textarea name="preference" rows="3" placeholder="Pressure preference, scent preference, timing..."><?= e((string) old_input('preference')) ?></textarea>
                    </label>

                    <label class="field">
                        <span>Private admin notes</span>
                        <textarea name="admin_notes" rows="5" placeholder="Only visible to the admin team..."><?= e((string) old_input('admin_notes')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/customers/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save customer</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Why this matters</p>
                        <h3>Booking context</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Preferences reduce friction</strong>
                        <p>Keep the front desk aware of pressure, room, scent, and therapist preferences before a guest arrives.</p>
                    </article>
                    <article class="info-item">
                        <strong>Notes support repeat service</strong>
                        <p>Private admin notes help the team deliver continuity without exposing internal detail to the customer.</p>
                    </article>
                    <article class="info-item">
                        <strong>Profiles feed bookings</strong>
                        <p>Saved customer records automatically become selectable in the booking flow.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
