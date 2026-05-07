<?php declare(strict_types=1);

$reportTabs = Report::navigation();
$activeReportRoute = $reportRoute ?? 'reports.dashboard';
?>
<nav class="report-nav" aria-label="Reports navigation">
    <?php foreach ($reportTabs as $tab): ?>
        <?php $isActive = $activeReportRoute === $tab['route']; ?>
        <a class="report-nav-link <?= $isActive ? 'is-active' : '' ?>" href="<?= e($tab['href']) ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>
