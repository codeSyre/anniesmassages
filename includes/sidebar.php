<?php declare(strict_types=1);

$navigation = app_navigation();
$activeRoute = $currentRoute ?? 'dashboard';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-brand-mark">AM</span>
        <div>
            <p class="sidebar-kicker">Spa control room</p>
            <h1><?= e(app_config('app_name', "Annie's Massages Admin")) ?></h1>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Primary navigation">
        <?php foreach ($navigation as $group => $items): ?>
            <?php $visibleItems = array_filter($items, static fn (array $item): bool => $item['permission'] === null || user_can((string) $item['permission'])); ?>
            <?php if ($visibleItems === []): continue; endif; ?>
            <section class="nav-group">
                <p class="nav-group-title"><?= e($group) ?></p>
                <?php foreach ($visibleItems as $item): ?>
                    <?php $isActive = $activeRoute === $item['route']; ?>
                    <a class="nav-link <?= $isActive ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
                        <span class="nav-link-icon"><?= nav_icon_svg((string) ($item['icon'] ?? 'overview')) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </nav>
</aside>
