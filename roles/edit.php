<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

$currentUser = require_login();
require_permission('roles.update');

$roleId = trim((string) ($_GET['id'] ?? ''));
$role = $roleId !== '' ? Role::find($roleId) : null;

if ($role === null) {
    http_response_code(404);
    exit('Role not found');
}

$errors = flash_get('role_errors', []);
$permissionGroups = Role::permissionGroups();
$selectedPermissions = old_input('permissions', $role['permissions']);
$isLocked = (bool) ($role['is_locked'] ?? false);

$pageTitle = 'Edit Role';
$pageEyebrow = 'Adjust access';
$currentRoute = 'roles';
$topbarAction = ['label' => 'Back to role', 'href' => '/roles/view.php?id=' . urlencode($role['id'])];

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
                        <h3>Edit <?= e($role['name']) ?></h3>
                    </div>
                    <p>Update the access rules for this role and keep permissions aligned with actual responsibilities.</p>
                </div>

                <?php if ($isLocked): ?>
                    <p class="inline-error">This system role is locked and can only be reviewed, not edited.</p>
                <?php elseif (isset($errors['role'])): ?>
                    <p class="inline-error"><?= e($errors['role']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/role-save.php">
                    <input type="hidden" name="id" value="<?= e($role['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Role name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name', $role['name'])) ?>" placeholder="Guest Experience Lead" <?= $isLocked ? 'disabled' : '' ?>>
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Status</span>
                            <select name="status" <?= $isLocked ? 'disabled' : '' ?>>
                                <?php foreach (Role::statuses() as $status): ?>
                                    <option value="<?= e($status) ?>" <?= old_input('status', $role['status']) === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="4" placeholder="What this role is responsible for and why it exists..." <?= $isLocked ? 'disabled' : '' ?>><?= e((string) old_input('description', $role['description'])) ?></textarea>
                        <?php if (isset($errors['description'])): ?><small><?= e($errors['description']) ?></small><?php endif; ?>
                    </label>

                    <div class="section-head section-head-compact">
                        <div>
                            <p class="section-kicker">Permission matrix</p>
                            <h3>Review role access</h3>
                        </div>
                        <p class="report-table-note">This matrix maps directly to the permission guards used across the application.</p>
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
                                            <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>" <?= $checked ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
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
                        <a class="button-muted" href="/roles/view.php?id=<?= e($role['id']) ?>">Cancel</a>
                        <?php if (!$isLocked): ?>
                            <button class="button-primary" type="submit">Update role</button>
                        <?php endif; ?>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Role summary</p>
                        <h3>Before you save</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e((string) count($role['permissions'])) ?> permission keys</strong>
                        <p>Current scope for <?= e($role['name']) ?> before any changes are saved.</p>
                    </article>
                    <article class="info-item">
                        <strong><?= e((string) count($role['users'])) ?> assigned admin users</strong>
                        <p>Changes here will affect every mapped user immediately after reassignment or login refresh.</p>
                    </article>
                    <article class="info-item">
                        <strong><?= e(($role['is_system'] ?? false) ? 'System role' : 'Custom role') ?></strong>
                        <p><?= e($isLocked ? 'Locked roles are reserved for baseline application access.' : 'This role can be adjusted as responsibilities evolve.') ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
