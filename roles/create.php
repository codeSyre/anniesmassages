<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

$currentUser = require_login();
require_permission('roles.create');

$errors = flash_get('role_errors', []);
$permissionGroups = Role::permissionGroups();
$selectedPermissions = old_input('permissions', []);

$pageTitle = 'Create Role';
$pageEyebrow = 'New access layer';
$currentRoute = 'roles';
$topbarAction = ['label' => 'Back to roles', 'href' => '/roles/list.php'];

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
                        <p class="section-kicker">Role setup</p>
                        <h3>Create a role</h3>
                    </div>
                    <p>Define the access boundaries and assign only the permissions this role genuinely needs.</p>
                </div>

                <?php if (isset($errors['role'])): ?>
                    <p class="inline-error"><?= e($errors['role']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/role-save.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Role name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name')) ?>" placeholder="Guest Experience Lead">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (Role::statuses() as $status): ?>
                                    <option value="<?= e($status) ?>" <?= old_input('status', 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="4" placeholder="What this role is responsible for and why it exists..."><?= e((string) old_input('description')) ?></textarea>
                        <?php if (isset($errors['description'])): ?><small><?= e($errors['description']) ?></small><?php endif; ?>
                    </label>

                    <div class="section-head section-head-compact">
                        <div>
                            <p class="section-kicker">Permission matrix</p>
                            <h3>Choose access deliberately</h3>
                        </div>
                        <p class="report-table-note">Only the checked permissions will be granted to users with this role.</p>
                    </div>

                    <?php if (isset($errors['permissions'])): ?>
                        <p class="inline-error"><?= e($errors['permissions']) ?></p>
                    <?php endif; ?>

                    <div class="permission-group-stack">
                        <?php foreach ($permissionGroups as $group => $permissions): ?>
                            <section class="template-card">
                                <div class="section-head">
                                    <div>
                                        <p class="section-kicker"><?= e($group) ?></p>
                                        <h3><?= e($group) ?></h3>
                                    </div>
                                </div>

                                <div class="permission-checklist">
                                    <?php foreach ($permissions as $permission => $description): ?>
                                        <?php $checked = in_array($permission, (array) $selectedPermissions, true); ?>
                                        <label class="permission-checkbox">
                                            <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>" <?= $checked ? 'checked' : '' ?>>
                                            <div>
                                                <strong><?= e($permission) ?></strong>
                                                <span><?= e($description) ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>

                    <div class="button-row">
                        <a class="button-muted" href="/roles/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save role</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Access guidelines</p>
                        <h3>Keep roles focused</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Match responsibility to access</strong>
                        <p>Only grant the permissions the role needs for day-to-day work. Avoid “just in case” access.</p>
                    </article>
                    <article class="info-item">
                        <strong>Separate finance from operations</strong>
                        <p>Payments, inventory, payroll, and reports can stay distinct from front-desk booking duties when needed.</p>
                    </article>
                    <article class="info-item">
                        <strong>Use reports to audit</strong>
                        <p>After assigning a role, validate whether it still fits the user’s workflow before expanding access.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
