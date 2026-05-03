<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Notification.php';

$currentUser = require_login();
require_permission('notifications.view');

$templates = Notification::templates();
$stats = Notification::stats();
$errors = flash_get('notification_template_errors', []);
$flashMessage = flash_get('notification_success');
$oldTemplates = old_input('templates', []);
$oldTemplates = is_array($oldTemplates) ? $oldTemplates : [];

$pageTitle = 'Notification Templates';
$pageEyebrow = 'Notifications & reminders';
$currentRoute = 'notifications';
$topbarAction = ['label' => 'Notification logs', 'href' => '/notifications/logs.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
        <?php endif; ?>

        <section class="module-hero">
            <article class="hero-panel">
                <p class="hero-eyebrow">Template control</p>
                <h1 class="hero-title">Shape every booking, reminder, and payment message from one place.</h1>
                <p class="hero-copy">Templates define the language customers and therapists receive when the workflow sends confirmations, reminders, cancellations, and assignment notices.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/notifications/reminders.php">Reminder settings</a>
                    <a class="action-link is-secondary" href="/notifications/logs.php">Open logs</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <article class="mini-stat-card">
                        <span class="<?= e(badge_class($stat['tone'])) ?>"><?= e($stat['label']) ?></span>
                        <strong><?= e($stat['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="table-card">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Template workspace</p>
                    <h3>Default message copy</h3>
                </div>
                <p>All templates currently dispatch through email in this first phase, but the structure is ready for later SMS or WhatsApp channels.</p>
            </div>

            <form class="module-form" method="post" action="/process/notification-save.php">
                <input type="hidden" name="action" value="templates">

                <div class="template-stack">
                    <?php foreach ($templates as $key => $template): ?>
                        <?php
                        $value = $oldTemplates[$key] ?? [];
                        $subjectValue = (string) ($value['subject'] ?? $template['subject']);
                        $bodyValue = (string) ($value['body'] ?? $template['body']);
                        $activeValue = (($value['active'] ?? ($template['active'] ? '1' : '0')) === '1');
                        ?>
                        <article class="template-card">
                            <div class="section-head">
                                <div>
                                    <p class="section-kicker"><?= e($template['channel']) ?></p>
                                    <h3><?= e($template['label']) ?></h3>
                                </div>
                                <span class="<?= e($template['audience'] === 'staff' ? 'badge badge-info' : 'badge badge-success') ?>"><?= e(ucfirst($template['audience'])) ?></span>
                            </div>

                            <div class="form-grid">
                                <label class="field">
                                    <span>Subject</span>
                                    <input type="text" name="templates[<?= e($key) ?>][subject]" value="<?= e($subjectValue) ?>">
                                    <?php if (isset($errors['templates.' . $key . '.subject'])): ?><small><?= e($errors['templates.' . $key . '.subject']) ?></small><?php endif; ?>
                                </label>
                                <label class="toggle-field">
                                    <input type="hidden" name="templates[<?= e($key) ?>][active]" value="0">
                                    <input type="checkbox" name="templates[<?= e($key) ?>][active]" value="1" <?= $activeValue ? 'checked' : '' ?>>
                                    <span>Template is active in the workflow</span>
                                </label>
                            </div>

                            <label class="field">
                                <span>Body</span>
                                <textarea name="templates[<?= e($key) ?>][body]" rows="4"><?= e($bodyValue) ?></textarea>
                                <?php if (isset($errors['templates.' . $key . '.body'])): ?><small><?= e($errors['templates.' . $key . '.body']) ?></small><?php endif; ?>
                            </label>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="button-row">
                    <button class="button-primary" type="submit">Save templates</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
