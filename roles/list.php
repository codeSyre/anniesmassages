<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

$currentUser = require_login();
require_permission('roles.view');

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'all'),
];

$hasRoles = Role::all() !== [];
$roles = Role::all($filters);
$stats = Role::stats();
$flashMessage = flash_get('role_success');
$errors = flash_get('role_errors', []);

$pageTitle = 'Roles & Permissions';
$pageEyebrow = 'Access control';
$currentRoute = 'roles';

if ($hasRoles) {
    $topbarActions = [
        ['label' => 'Create role', 'href' => '/roles/create.php', 'permission' => 'roles.create'],
        ['label' => 'Open reports', 'href' => '/reports/dashboard.php', 'permission' => 'reports.view'],
    ];
} else {
    $topbarAction = ['label' => 'Create role', 'href' => '/roles/create.php', 'permission' => 'roles.create'];
}

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

        <section class="module-hero<?= $hasRoles ? ' module-hero-compact' : '' ?>">
            <?php if (!$hasRoles): ?>
                <article class="hero-panel">
                    <p class="hero-eyebrow">Roles and permissions</p>
                    <h1 class="hero-title">Control who can see, change, and approve each part of the admin.</h1>
                    <p class="hero-copy">Use roles to keep operational access intentional. The matrix below reflects the live permission keys already guarding bookings, payments, inventory, payroll, notifications, reports, and the rest of the admin.</p>

                    <div class="hero-actions">
                        <a class="action-link" href="/roles/create.php">Create role</a>
                        <a class="action-link is-secondary" href="/reports/dashboard.php">Open reports</a>
                    </div>
                </article>
            <?php endif; ?>

            <aside class="module-stat-grid<?= $hasRoles ? ' module-stat-grid-quad' : '' ?>">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="filter-panel">
            <div class="filter-panel-row">
                <div class="filter-chip-row">
                    <a class="<?= e(active_filter($filters['status'], 'all')) ?>" href="/roles/list.php?search=<?= urlencode($filters['search']) ?>">All roles</a>
                    <?php foreach (Role::statuses() as $status): ?>
                        <a class="<?= e(active_filter($filters['status'], $status)) ?>" href="/roles/list.php?status=<?= urlencode($status) ?>&search=<?= urlencode($filters['search']) ?>">
                            <?= e(ucfirst($status)) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form class="inline-search" method="get">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search roles or permissions">
                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Role directory</p>
                    <h3>Configured access layers</h3>
                </div>
                <p class="report-table-note">System roles can be reviewed directly here, while custom roles can also be edited.</p>
            </div>

            <?php if ($roles === []): ?>
                <p class="report-empty">No roles matched the current filters.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Permissions</th>
                            <th>Assigned users</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <strong><?= e($role['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="<?= e(status_badge_class($role['status'])) ?>"><?= e(ucfirst($role['status'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $role['permission_count']) ?> keys</strong>
                                    <span><?= e(implode(' · ', array_slice($role['permissions'], 0, 2))) ?><?= count($role['permissions']) > 2 ? ' ...' : '' ?></span>
                                </td>
                                <td>
                                    <strong><?= e((string) $role['user_count']) ?> mapped</strong>
                                    <span><?= e($role['users'] !== [] ? implode(' · ', array_map(static fn (array $user): string => $user['name'], array_slice($role['users'], 0, 2))) : 'No users assigned') ?></span>
                                </td>
                                <td>
                                    <strong><?= e(($role['is_system'] ?? false) ? 'System' : 'Custom') ?></strong>
                                    <span><?= e(($role['is_locked'] ?? false) ? 'Locked' : 'Editable') ?></span>
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-actions">
                                        <a href="/roles/view.php?id=<?= e($role['id']) ?>">View</a>
                                        <?php if (!($role['is_locked'] ?? false) && user_can('roles.update')): ?>
                                            <a href="/roles/edit.php?id=<?= e($role['id']) ?>">Edit</a>
                                        <?php endif; ?>
                                        <?php if (($role['can_delete'] ?? false) && user_can('roles.update')): ?>
                                            <form
                                                class="inline-action-form inline-action-form-danger"
                                                method="post"
                                                action="/process/role-save.php"
                                                data-confirm-dialog-form
                                                data-confirm-title="Delete role?"
                                                data-confirm-message="Delete the <?= e($role['name']) ?> role? This action cannot be undone."
                                                data-confirm-submit-label="Delete role"
                                            >
                                                <input type="hidden" name="action" value="delete_role">
                                                <input type="hidden" name="id" value="<?= e($role['id']) ?>">
                                                <button class="button-danger" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
