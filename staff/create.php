<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$currentUser = require_login();
require_permission('staff.create');

$errors = flash_get('staff_errors', []);
$pageTitle = 'Create Staff Profile';
$pageEyebrow = 'New therapist';
$currentRoute = 'staff';
$topbarAction = ['label' => 'Back to staff', 'href' => '/staff/list.php'];

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="page">
        <?php require __DIR__ . '/../includes/topbar.php'; ?>

        <section class="split-layout">
            <article class="table-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Therapist setup</p>
                        <h3>Create a staff profile</h3>
                    </div>
                    <p>Capture role, specialty, contact info, compensation structure, and baseline capacity so the rest of the system can use it.</p>
                </div>

                <form class="module-form" method="post" action="/process/staff-save.php">
                    <div class="form-grid">
                        <label class="field">
                            <span>Full name</span>
                            <input type="text" name="name" value="<?= e((string) old_input('name')) ?>" placeholder="Therapist name">
                            <?php if (isset($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Specialty</span>
                            <input type="text" name="specialty" value="<?= e((string) old_input('specialty')) ?>" placeholder="Deep tissue, aromatherapy, recovery">
                            <?php if (isset($errors['specialty'])): ?><small><?= e($errors['specialty']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Role / type</span>
                            <input type="text" name="role_type" value="<?= e((string) old_input('role_type', 'therapist')) ?>" placeholder="therapist, senior therapist">
                            <?php if (isset($errors['role_type'])): ?><small><?= e($errors['role_type']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (['active', 'inactive', 'on_leave', 'terminated'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= old_input('status', 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Phone</span>
                            <input type="text" name="phone" value="<?= e((string) old_input('phone')) ?>" placeholder="+263 ...">
                        </label>
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e((string) old_input('email')) ?>" placeholder="therapist@example.com">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Capacity note</span>
                            <input type="text" name="capacity" value="<?= e((string) old_input('capacity', '4 sessions/day')) ?>" placeholder="4 sessions/day">
                        </label>
                        <label class="field">
                            <span>Profile color</span>
                            <select name="color">
                                <?php foreach (['cyan', 'teal', 'amber', 'slate'] as $color): ?>
                                    <option value="<?= e($color) ?>" <?= old_input('color', 'cyan') === $color ? 'selected' : '' ?>><?= e(ucfirst($color)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Salary structure</span>
                            <select name="salary_structure">
                                <?php foreach (['fixed', 'commission', 'hybrid'] as $structure): ?>
                                    <option value="<?= e($structure) ?>" <?= old_input('salary_structure', 'commission') === $structure ? 'selected' : '' ?>><?= e(ucfirst($structure)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['salary_structure'])): ?><small><?= e($errors['salary_structure']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Commission rate (%)</span>
                            <input type="number" min="0" step="0.01" name="commission_rate" value="<?= e((string) old_input('commission_rate', '25')) ?>">
                            <?php if (isset($errors['commission_rate'])): ?><small><?= e($errors['commission_rate']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Fixed pay</span>
                            <input type="number" min="0" step="0.01" name="fixed_pay" value="<?= e((string) old_input('fixed_pay', '0')) ?>">
                            <?php if (isset($errors['fixed_pay'])): ?><small><?= e($errors['fixed_pay']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Bio / internal context</span>
                        <textarea name="bio" rows="5" placeholder="Experience notes, client fit, strengths..."><?= e((string) old_input('bio')) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/staff/list.php">Cancel</a>
                        <button class="button-primary" type="submit">Save therapist</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Why this matters</p>
                        <h3>Operational links</h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong>Profiles feed scheduling</strong>
                        <p>Availability, blocked slots, and calendar boards all key off the therapist profiles created here.</p>
                    </article>
                    <article class="info-item">
                        <strong>Assignments affect booking quality</strong>
                        <p>Specialties help the front desk pair guests with the right therapist during booking creation.</p>
                    </article>
                    <article class="info-item">
                        <strong>Compensation data prepares payroll</strong>
                        <p>Salary structure and commission settings set the groundwork for the later payroll module.</p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
