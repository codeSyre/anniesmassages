<?php declare(strict_types=1);

$topbarAction = $topbarAction ?? null;
$topbarActions = $topbarActions ?? [];

if ($topbarAction !== null && $topbarActions === []) {
    $topbarActions = [$topbarAction];
}

$topbarActions = array_values(array_filter(
    is_array($topbarActions) ? $topbarActions : [],
    static fn (mixed $action): bool => is_array($action)
        && (($action['permission'] ?? null) === null || user_can((string) $action['permission']))
));

$showTopbarAction = $topbarAction !== null
    && (($topbarAction['permission'] ?? null) === null || user_can((string) $topbarAction['permission']));
?>
<header class="topbar">
    <div>
        <p class="topbar-kicker"><?= e($pageEyebrow ?? 'Dashboard overview') ?></p>
        <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
    </div>

    <div class="topbar-actions">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">Menu</button>
        <?php if ($topbarActions !== []): ?>
            <div class="topbar-action-group">
                <?php foreach ($topbarActions as $index => $action): ?>
                    <?php $actionClass = $index === 0 ? 'topbar-cta' : 'topbar-link'; ?>
                    <a class="<?= e($actionClass) ?>" href="<?= e((string) ($action['href'] ?? '#')) ?>"><?= e((string) ($action['label'] ?? 'Open')) ?></a>
                <?php endforeach; ?>
            </div>
        <?php elseif ($showTopbarAction && is_array($topbarAction)): ?>
            <a class="topbar-cta" href="<?= e((string) ($topbarAction['href'] ?? '#')) ?>"><?= e((string) ($topbarAction['label'] ?? 'Open')) ?></a>
        <?php endif; ?>
        <div class="user-chip" data-user-menu-trigger aria-haspopup="true" aria-expanded="false" role="button" tabindex="0">
            <span class="user-chip-avatar"><?= e(initials($currentUser['name'] ?? 'Admin User')) ?></span>
            <div>
                <strong><?= e($currentUser['name'] ?? 'Admin User') ?></strong>
                <span><?= e($currentUser['role_label'] ?? str_replace('_', ' ', $currentUser['role'] ?? 'admin')) ?></span>
            </div>
            <div class="user-menu" role="menu">
                <a class="user-menu-item" href="/profile/index.php" role="menuitem">Profile</a>
                <a class="user-menu-item user-menu-item-danger" href="/process/logout.php" role="menuitem">Sign out</a>
            </div>
        </div>
    </div>
</header>
