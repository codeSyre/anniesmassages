<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Role.php';

$currentUser = require_login();
require_permission('staff.create');

$roleOptions = Role::roleOptions(true);

$errors = flash_get('staff_errors', []);
$pageTitle = 'Create Staff Profile';
$pageEyebrow = 'New staff';
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
                        <p class="section-kicker">Staff setup</p>
                        <h3>Create a staff profile</h3>
                    <p>Capture role, specialty, contact info, compensation structure, and baseline capacity so the rest of the system can use it.</p>
                    </div>
                </div>

                <?php if (isset($errors['staff'])): ?>
                    <p class="inline-error"><?= e($errors['staff']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/staff-save.php" enctype="multipart/form-data">
                    <div class="form-grid">
                        <label class="field">
                            <span>First name</span>
                            <input type="text" name="first_name" value="<?= e((string) old_input('first_name')) ?>" placeholder="Staff first name">
                            <?php if (isset($errors['first_name'])): ?><small><?= e($errors['first_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Last name</span>
                            <input type="text" name="last_name" value="<?= e((string) old_input('last_name')) ?>" placeholder="Staff last name">
                            <?php if (isset($errors['last_name'])): ?><small><?= e($errors['last_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Specialty</span>
                            <input type="text" name="specialty" value="<?= e((string) old_input('specialty')) ?>" placeholder="Deep tissue, aromatherapy, recovery">
                            <?php if (isset($errors['specialty'])): ?><small><?= e($errors['specialty']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Role / type</span>
                            <select name="role_type">
                                <option value="">— select a role —</option>
                                <?php foreach ($roleOptions as $role): ?>
                                    <option value="<?= e($role['name']) ?>" <?= old_input('role_type') === $role['name'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['role_type'])): ?><small><?= e($errors['role_type']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (['active', 'inactive', 'suspended', 'on_leave', 'terminated'] as $status): ?>
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
                                <input type="email" name="email" value="<?= e((string) old_input('email')) ?>" placeholder="staff@example.com">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Address line 1</span>
                            <input type="text" name="address_line_1" value="<?= e((string) old_input('address_line_1')) ?>" placeholder="Street address">
                        </label>
                        <label class="field">
                            <span>Address line 2</span>
                            <input type="text" name="address_line_2" value="<?= e((string) old_input('address_line_2')) ?>" placeholder="Apartment, suite, landmark">
                        </label>
                        <label class="field">
                            <span>City / town</span>
                            <input type="text" name="city_town" value="<?= e((string) old_input('city_town')) ?>" placeholder="Harare">
                        </label>
                        <label class="field">
                            <span>Country</span>
                            <input type="text" name="country" value="<?= e((string) old_input('country')) ?>" placeholder="Zimbabwe">
                        </label>
                        <label class="field">
                            <span>Profile picture</span>
                            <input type="file" name="profile_picture" accept="image/*">
                        </label>
                        <label class="field">
                            <span>Capacity</span>
                            <select name="capacity">
                                <?php foreach (['2 sessions/day', '4 sessions/day', '7 sessions/day'] as $cap): ?>
                                    <option value="<?= e($cap) ?>" <?= old_input('capacity', '4 sessions/day') === $cap ? 'selected' : '' ?>><?= e($cap) ?></option>
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
                        <button class="button-primary" type="submit">Add staff</button>
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
                        <p>Availability, blocked slots, and calendar boards all key off the staff profiles created here.</p>
                    </article>
                    <article class="info-item">
                        <strong>Assignments affect booking quality</strong>
                        <p>Specialties help the front desk pair guests with the right staff during booking creation.</p>
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
