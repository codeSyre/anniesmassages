<?php declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/models/Report.php';

$currentUser = require_login();
require_permission('dashboard.view');

$pageTitle = 'Dashboard Overview';
$pageEyebrow = 'Operations command center';
$currentRoute = 'dashboard';
$dashboard = Report::dashboardOverview();

require __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="hero">
            <article class="hero-panel">
                <p class="hero-eyebrow"><?= e($dashboard['headline']['eyebrow']) ?></p>
                <h1 class="hero-title"><?= e($dashboard['headline']['title']) ?></h1>
                <p class="hero-copy"><?= e($dashboard['headline']['description']) ?></p>

                <div class="hero-actions">
                    <a class="action-link" href="/bookings/create.php">Create booking</a>
                    <a class="action-link is-secondary" href="/payments/ledger.php">Review ledger</a>
                </div>
            </article>

            <aside class="focus-stack" aria-label="Operational focus panels">
                <?php foreach ($dashboard['focusPanels'] as $panel): ?>
                    <section class="focus-card">
                        <p class="card-label"><?= e($panel['title']) ?></p>
                        <strong><?= e($panel['value']) ?></strong>
                        <p><?= e($panel['description']) ?></p>
                    </section>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="metrics-grid" aria-label="Dashboard summary metrics">
            <?php foreach ($dashboard['metrics'] as $metric): ?>
                <article class="card">
                    <span class="<?= e(badge_class($metric['tone'])) ?>"><?= e($metric['label']) ?></span>
                    <h3 class="card-value"><?= e($metric['value']) ?></h3>
                    <p class="card-note"><?= e($metric['change']) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-grid">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Today’s operations</p>
                        <h3>Booking flow</h3>
                    </div>
                    <p>Critical appointments and service handoffs that need attention today.</p>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Assignment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['operations'] as $operation): ?>
                            <tr>
                                <td><strong><?= e($operation['time']) ?></strong></td>
                                <td>
                                    <strong><?= e($operation['title']) ?></strong>
                                    <span><?= e($operation['customer']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($operation['staff']) ?></strong>
                                    <span>Assigned therapist</span>
                                </td>
                                <td><span class="<?= e(badge_class($operation['tone'])) ?>"><?= e($operation['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>

            <aside class="activity-column">
                <section class="activity-card">
                    <div class="section-head">
                        <div>
                            <p class="section-kicker">Quick actions</p>
                            <h3>Move fast</h3>
                        </div>
                        <p>One-click jumps into the busiest daily workflows.</p>
                    </div>

                    <div class="quick-actions">
                        <?php foreach ($dashboard['quickActions'] as $action): ?>
                            <a class="action-card" href="<?= e($action['href']) ?>">
                                <strong><?= e($action['label']) ?></strong>
                                <p><?= e($action['description']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </aside>
        </section>

        <section class="activity-card activity-card-standalone">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Recent activity</p>
                    <h3>What changed</h3>
                </div>
                <p>Fresh signals across bookings, payments, stock, and staff updates.</p>
            </div>

            <div class="activity-feed">
                <?php foreach ($dashboard['recentActivity'] as $activity): ?>
                    <article class="activity-item">
                        <h4><?= e($activity['title']) ?></h4>
                        <p><?= e($activity['description']) ?></p>
                        <span class="activity-time"><?= e($activity['time']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
