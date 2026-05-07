<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

$currentUser = require_login();
require_permission('roles.view');

$roleId = trim((string) ($_GET['id'] ?? ''));
$role = $roleId !== '' ? Role::find($roleId) : null;

if ($role === null) {
    http_response_code(404);
    exit('Role not found');
}

$pageTitle = 'Role Profile';
$pageEyebrow = 'Permissions and assignments';
$currentRoute = 'roles';
$topbarAction = ['label' => 'Back to roles', 'href' => '/roles/list.php'];
$flashMessage = flash_get('role_success');
$errors = flash_get('role_errors', []);

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['role'])): ?>
            <div class="notice-banner notice-banner-danger"><?= e($errors['role']) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Role profile</p>
                <h1 class="hero-title"><?= e($role['name']) ?></h1>
                <p class="hero-copy"><?= e($role['description']) ?></p>

                <div class="hero-actions">
                    <?php if (!($role['is_locked'] ?? false)): ?>
                        <a class="action-link" href="/roles/edit.php?id=<?= e($role['id']) ?>">Edit role</a>
                    <?php endif; ?>
                    <?php if (($role['can_delete'] ?? false) && user_can('roles.update')): ?>
                        <form
                            class="hero-action-form"
                            method="post"
                            action="/process/role-save.php"
                            data-confirm-dialog-form
                            data-confirm-title="Delete role?"
                            data-confirm-message="Delete the <?= e($role['name']) ?> role? This action cannot be undone."
                            data-confirm-submit-label="Delete role"
                        >
                            <input type="hidden" name="action" value="delete_role">
                            <input type="hidden" name="id" value="<?= e($role['id']) ?>">
                            <button class="button-danger" type="submit">Delete role</button>
                        </form>
                    <?php endif; ?>
                    <a class="action-link is-secondary" href="/roles/list.php">Return to roles</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($role['status'])) ?>"><?= e(ucfirst($role['status'])) ?></span>
                    <strong><?= e((string) $role['permission_count']) ?> permissions</strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Assignments</span>
                    <strong><?= e((string) count($role['users'])) ?> admin users</strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-warning">Type</span>
                    <strong><?= e(($role['is_system'] ?? false) ? 'System role' : 'Custom role') ?></strong>
                </article>
            </aside>
        </section>

        <section class="report-grid section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Permission coverage</p>
                        <h3>What this role can access</h3>
                    </div>
                    <p class="report-table-note">Permissions are grouped the same way the rest of the admin is guarded.</p>
                </div>

                <div class="permission-group-stack">
                    <?php foreach ($role['permission_groups'] as $group => $permissions): ?>
                        <section class="template-card">
                            <div class="section-head">
                                <div>
                                    <p class="section-kicker"><?= e($group) ?></p>
                                    <h3><?= e($group) ?> permissions</h3>
                                </div>
                            </div>

                            <div class="permission-list">
                                <?php foreach ($permissions as $permission => $description): ?>
                                    <article class="permission-item">
                                        <strong><?= e($permission) ?></strong>
                                        <span><?= e($description) ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Assigned admins</p>
                        <h3>Who currently uses this role</h3>
                    </div>
                    <p class="report-table-note">These accounts inherit the permissions defined here.</p>
                </div>

                <?php if ($role['users'] === []): ?>
                    <p class="report-empty">No admin users are currently assigned to this role.</p>
                <?php else: ?>
                    <div class="report-list">
                        <?php foreach ($role['users'] as $user): ?>
                            <article class="report-list-item">
                                <div>
                                    <strong><?= e($user['name']) ?></strong>
                                    <span><?= e($user['title']) ?> · <?= e($user['email']) ?></span>
                                </div>
                                <em><?= e(date('j M Y H:i', strtotime($user['last_active_at']))) ?></em>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
