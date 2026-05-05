<?php declare(strict_types=1);

function current_user(): ?array
{
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $user = $_SESSION['user'];
    } else {
        return null;
    }

    require_once __DIR__ . '/../models/Role.php';
    $roleId = resolved_role_id_for_user($user);
    $role = Role::find($roleId);

    $user['role'] = $roleId;
    $user['role_label'] = $role['name'] ?? ($roleId === full_access_role_id() ? 'Super Admin' : 'Admin');
    $_SESSION['user'] = $user;

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
        header('Location: /index.php?redirect=' . urlencode((string) $redirect));
        exit;
    }

    return $user;
}

function user_can(string $permission): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    if (user_has_full_access($user)) {
        return true;
    }

    require_once __DIR__ . '/../models/Role.php';
    $roleId = resolved_role_id_for_user($user);
    $granted = $_SESSION['permissions'] ?? Role::permissionsForRole($roleId);

    return in_array($permission, $granted, true);
}

function require_permission(string $permission): void
{
    if (!user_can($permission)) {
        render_forbidden_page($permission);
    }
}

function render_forbidden_page(string $permission): never
{
    http_response_code(403);

    $currentUser = current_user();
    $pageTitle = 'Access Restricted';
    $permissionLabel = permission_label($permission);
    $roleLabel = (string) ($currentUser['role_label'] ?? str_replace('_', ' ', (string) ($currentUser['role'] ?? 'current role')));
    $availableDestinations = available_navigation_destinations();
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
    $bodyClass = 'status-body';

    require __DIR__ . '/header.php';
    ?>
    <main class="status-shell">
        <section class="status-layout">
            <article class="status-panel">
                <span class="badge badge-danger">403 Forbidden</span>
                <p class="section-kicker status-kicker">Access restricted</p>
                <h1 class="status-title">This view is outside your current access level.</h1>
                <p class="status-copy">
                    You are signed in as <strong><?= e($roleLabel) ?></strong>, but this page needs
                    <strong><?= e($permissionLabel) ?></strong>.
                </p>

                <div class="status-detail-grid">
                    <div class="status-detail-card">
                        <span>Requested path</span>
                        <strong><?= e($requestPath) ?></strong>
                    </div>
                    <div class="status-detail-card">
                        <span>Permission required</span>
                        <strong><?= e($permission) ?></strong>
                    </div>
                </div>

                <div class="status-actions">
                    <a class="action-link" href="/dashboard.php">Go to dashboard</a>
                    <a class="action-link is-secondary" href="/profile/index.php">Open profile</a>
                    <a class="topbar-link" href="/process/logout.php">Sign out</a>
                </div>

                <div class="status-note">
                    If you should have access to this area, ask an administrator to update the permissions on your role.
                </div>
            </article>

            <aside class="status-side-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Available now</p>
                        <h3>Use a section you can access</h3>
                    </div>
                </div>

                <div class="status-link-stack">
                    <?php foreach ($availableDestinations as $destination): ?>
                        <a class="action-card" href="<?= e((string) $destination['href']) ?>">
                            <strong><?= e((string) $destination['label']) ?></strong>
                            <p><?= e((string) $destination['meta']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>
    </main>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

function permission_label(string $permission): string
{
    return ucwords(str_replace(['.', '_'], [' ', ' '], trim($permission)));
}

function available_navigation_destinations(): array
{
    $destinations = [];

    foreach (app_navigation() as $group => $items) {
        foreach ($items as $item) {
            $permission = $item['permission'] ?? null;

            if ($permission !== null && !user_can((string) $permission)) {
                continue;
            }

            $href = trim((string) ($item['href'] ?? ''));
            if ($href === '') {
                continue;
            }

            $destinations[$href] = [
                'href' => $href,
                'label' => (string) ($item['label'] ?? 'Open'),
                'meta' => $group . ' section',
            ];
        }
    }

    if (!isset($destinations['/profile/index.php'])) {
        $destinations['/profile/index.php'] = [
            'href' => '/profile/index.php',
            'label' => 'Profile',
            'meta' => 'Review your account details',
        ];
    }

    if (!isset($destinations['/process/logout.php'])) {
        $destinations['/process/logout.php'] = [
            'href' => '/process/logout.php',
            'label' => 'Sign out',
            'meta' => 'Switch accounts if needed',
        ];
    }

    return array_slice(array_values($destinations), 0, 5);
}

function resolved_role_id_for_user(array $user): string
{
    if (user_has_full_access($user)) {
        return full_access_role_id();
    }

    require_once __DIR__ . '/../models/Role.php';

    return Role::roleIdForUser(
        (string) ($user['id'] ?? ''),
        (string) ($user['role'] ?? app_config('default_role', 'super_admin'))
    );
}

function user_has_full_access(array $user): bool
{
    $configuredEmail = full_access_email();
    $userEmail = trim((string) ($user['email'] ?? ''));

    return $configuredEmail !== '' && $userEmail !== '' && strtolower($userEmail) === strtolower($configuredEmail);
}

function full_access_email(): string
{
    return trim((string) ($_ENV['AUTH_OVERRIDE_EMAIL'] ?? $_SERVER['AUTH_OVERRIDE_EMAIL'] ?? getenv('AUTH_OVERRIDE_EMAIL') ?: ''));
}

function full_access_role_id(): string
{
    return 'super_admin';
}
