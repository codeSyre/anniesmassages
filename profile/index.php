<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Profile.php';

$currentUser = require_login();
$profile = Profile::current($currentUser);
$errors = flash_get('profile_errors', []);
$flashMessage = flash_get('profile_success');

$pageTitle = 'Admin Profile';
$pageEyebrow = 'Account and access';
$currentRoute = 'profile';
$topbarAction = ['label' => 'Roles & permissions', 'href' => '/roles/list.php', 'permission' => 'roles.view'];

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
                <p class="hero-eyebrow">Admin profile</p>
                <h1 class="hero-title"><?= e($profile['name']) ?></h1>
                <p class="hero-copy"><?= e($profile['title']) ?> with <?= e($profile['permission_count']) ?> active permission keys across <?= e($profile['access_scope_count']) ?> access groups. Keep your identity, notification preferences, and control-room visibility accurate here.</p>

                <div class="hero-actions">
                    <a class="action-link" href="/roles/view.php?id=<?= e($profile['role_id']) ?>">Open role profile</a>
                    <a class="action-link is-secondary" href="/reports/dashboard.php">View reports</a>
                </div>
            </article>

            <aside class="module-stat-grid">
                <article class="mini-stat-card">
                    <span class="<?= e(status_badge_class($profile['status'])) ?>"><?= e(ucfirst($profile['status'])) ?></span>
                    <strong><?= e($profile['role_label']) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-info">Last active</span>
                    <strong><?= e(date('j M Y H:i', strtotime($profile['last_active_at']))) ?></strong>
                </article>
                <article class="mini-stat-card">
                    <span class="badge badge-success">Timezone</span>
                    <strong><?= e($profile['timezone']) ?></strong>
                </article>
            </aside>
        </section>

        <section class="split-layout section-spaced">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Account details</p>
                        <h3>Keep your profile current</h3>
                    </div>
                    <p>These details are shown around the admin shell and help the team know who is operating the control room.</p>
                </div>

                <form class="module-form" method="post" action="/process/profile-save.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Full name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name', $profile['name'])) ?>" placeholder="Your name">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Email address</span>
                            <input type="email" name="email" value="<?= e((string) old_input('email', $profile['email'])) ?>" placeholder="you@example.com">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Job title</span>
                            <input type="text" name="title" value="<?= e((string) old_input('title', $profile['title'])) ?>" placeholder="Operations Lead">
                            <?php if (isset($errors['title'])): ?><small><?= e($errors['title']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Phone</span>
                            <input type="text" name="phone" value="<?= e((string) old_input('phone', $profile['phone'])) ?>" placeholder="+263 ...">
                        </label>

                        <label class="field">
                            <span>Timezone</span>
                            <input type="text" name="timezone" value="<?= e((string) old_input('timezone', $profile['timezone'])) ?>" placeholder="Africa/Harare">
                            <?php if (isset($errors['timezone'])): ?><small><?= e($errors['timezone']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Role</span>
                            <input type="text" value="<?= e($profile['role_label']) ?>" disabled>
                        </label>
                    </div>

                    <label class="field">
                        <span>Bio</span>
                        <textarea name="bio" rows="4" placeholder="Short working summary..."><?= e((string) old_input('bio', $profile['bio'])) ?></textarea>
                    </label>

                    <div class="section-head section-head-compact">
                        <div>
                            <p class="section-kicker">Preferences</p>
                            <h3>Notification defaults</h3>
                        </div>
                    </div>

                    <div class="settings-checklist">
                        <label class="toggle-field">
                            <input type="checkbox" name="daily_brief" value="1" <?= old_input('daily_brief', $profile['daily_brief'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
                            <span>Send a daily operations brief to this account each morning</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="payment_alerts" value="1" <?= old_input('payment_alerts', $profile['payment_alerts'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
                            <span>Keep payment and reconciliation alerts enabled</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="inventory_alerts" value="1" <?= old_input('inventory_alerts', $profile['inventory_alerts'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
                            <span>Receive low-stock and supply pressure reminders</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="marketing_updates" value="1" <?= old_input('marketing_updates', $profile['marketing_updates'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
                            <span>Opt in to non-operational update digests</span>
                        </label>
                    </div>

                    <div class="section-head section-head-compact">
                        <div>
                            <p class="section-kicker">Password reset</p>
                            <h3>Change your login password</h3>
                        </div>
                    </div>

                    <div class="form-grid">
                        <label class="field">
                            <span>Current password</span>
                            <input type="password" name="current_password" placeholder="Enter current password">
                            <?php if (isset($errors['current_password'])): ?><small><?= e($errors['current_password']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>New password</span>
                            <input type="password" name="new_password" placeholder="At least 8 characters">
                            <?php if (isset($errors['new_password'])): ?><small><?= e($errors['new_password']) ?></small><?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Confirm new password</span>
                            <input type="password" name="confirm_password" placeholder="Repeat the new password">
                            <?php if (isset($errors['confirm_password'])): ?><small><?= e($errors['confirm_password']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <div class="button-row">
                        <a class="button-muted" href="/dashboard.php">Back to dashboard</a>
                        <button class="button-primary" type="submit">Save profile</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Account security</p>
                        <h3>Current access posture</h3>
                    </div>
                </div>

                <div class="report-pairs">
                    <div class="report-pair">
                        <strong>Password changed</strong>
                        <span><?= e(date('j M Y', strtotime($profile['password_changed_at']))) ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Two-factor</strong>
                        <span><?= e($profile['two_factor_enabled'] ? 'Enabled' : 'Not enabled') ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Permission keys</strong>
                        <span><?= e((string) $profile['permission_count']) ?></span>
                    </div>
                    <div class="report-pair">
                        <strong>Access groups</strong>
                        <span><?= e((string) $profile['access_scope_count']) ?></span>
                    </div>
                </div>

                <div class="section-head section-head-compact">
                    <div>
                        <p class="section-kicker">Recent account activity</p>
                        <h3>Identity timeline</h3>
                    </div>
                </div>

                <div class="info-list">
                    <?php foreach ($profile['recent_activity'] as $item): ?>
                        <article class="info-item">
                            <strong><?= e($item['label']) ?></strong>
                            <p><?= e($item['meta']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="table-card section-spaced">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Effective permissions</p>
                    <h3>What this account can currently access</h3>
                </div>
                <p class="report-table-note">This is driven by your assigned role and the active permission matrix behind it.</p>
            </div>

            <div class="permission-group-stack">
                <?php foreach ($profile['permission_groups'] as $group => $permissions): ?>
                    <section class="template-card">
                        <div class="section-head">
                            <div>
                                <p class="section-kicker"><?= e($group) ?></p>
                                <h3><?= e($group) ?></h3>
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
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
