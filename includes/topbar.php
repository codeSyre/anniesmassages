<?php declare(strict_types=1);

$topbarAction = $topbarAction ?? null;
?>
<header class="topbar">
    <div>
        <p class="topbar-kicker"><?= e($pageEyebrow ?? 'Dashboard overview') ?></p>
        <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
    </div>

    <div class="topbar-actions">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">Menu</button>
        <?php if (is_array($topbarAction)): ?>
            <a class="topbar-cta" href="<?= e((string) ($topbarAction['href'] ?? '#')) ?>"><?= e((string) ($topbarAction['label'] ?? 'Open')) ?></a>
        <?php else: ?>
            <button class="topbar-cta" type="button">Export snapshot</button>
        <?php endif; ?>
        <div class="user-chip">
            <span class="user-chip-avatar"><?= e(initials($currentUser['name'] ?? 'Admin User')) ?></span>
            <div>
                <strong><?= e($currentUser['name'] ?? 'Admin User') ?></strong>
                <span><?= e(str_replace('_', ' ', $currentUser['role'] ?? 'admin')) ?></span>
            </div>
        </div>
    </div>
</header>
