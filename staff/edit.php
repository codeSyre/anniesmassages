<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Staff.php';

$currentUser = require_login();
require_permission('staff.update');

$staffId = (string) ($_GET['id'] ?? '');
$member = $staffId !== '' ? Staff::find($staffId) : null;

if ($member === null) {
    redirect_to('/staff/list.php');
}

$errors = flash_get('staff_errors', []);
$pageTitle = 'Edit Staff Profile';
$pageEyebrow = $member['name'];
$currentRoute = 'staff';
$topbarAction = ['label' => 'View profile', 'href' => '/staff/view.php?id=' . urlencode($member['id'])];

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
                        <p class="section-kicker">Profile update</p>
                        <h3>Adjust therapist details</h3>
                    </div>
                    <p>Keep role, specialty, contact details, workload expectations, and compensation structure current.</p>
                </div>

                <?php if (isset($errors['staff'])): ?>
                    <p class="inline-error"><?= e($errors['staff']) ?></p>
                <?php endif; ?>

                <form class="module-form" method="post" action="/process/staff-save.php">
                    <input type="hidden" name="id" value="<?= e($member['id']) ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>First name</span>
                            <input type="text" name="first_name" value="<?= e((string) old_input('first_name', $member['first_name'])) ?>">
                            <?php if (isset($errors['first_name'])): ?><small><?= e($errors['first_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Last name</span>
                            <input type="text" name="last_name" value="<?= e((string) old_input('last_name', $member['last_name'])) ?>">
                            <?php if (isset($errors['last_name'])): ?><small><?= e($errors['last_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Specialty</span>
                            <input type="text" name="specialty" value="<?= e((string) old_input('specialty', $member['specialty'])) ?>">
                            <?php if (isset($errors['specialty'])): ?><small><?= e($errors['specialty']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Role / type</span>
                            <input type="text" name="role_type" value="<?= e((string) old_input('role_type', $member['role_type'])) ?>">
                            <?php if (isset($errors['role_type'])): ?><small><?= e($errors['role_type']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (['active', 'inactive', 'on_leave', 'terminated'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= old_input('status', $member['status']) === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Phone</span>
                            <input type="text" name="phone" value="<?= e((string) old_input('phone', $member['phone'])) ?>">
                        </label>
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e((string) old_input('email', $member['email'])) ?>">
                            <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Address line 1</span>
                            <input type="text" name="address_line_1" value="<?= e((string) old_input('address_line_1', $member['address_line_1'])) ?>">
                        </label>
                        <label class="field">
                            <span>Address line 2</span>
                            <input type="text" name="address_line_2" value="<?= e((string) old_input('address_line_2', $member['address_line_2'])) ?>">
                        </label>
                        <label class="field">
                            <span>City / town</span>
                            <input type="text" name="city_town" value="<?= e((string) old_input('city_town', $member['city_town'])) ?>">
                        </label>
                        <label class="field">
                            <span>Country</span>
                            <input type="text" name="country" value="<?= e((string) old_input('country', $member['country'])) ?>">
                        </label>
                        <label class="field">
                            <span>Profile picture path</span>
                            <input type="text" name="profile_picture_path" value="<?= e((string) old_input('profile_picture_path', $member['profile_picture_path'])) ?>">
                        </label>
                        <label class="field">
                            <span>Capacity note</span>
                            <input type="text" name="capacity" value="<?= e((string) old_input('capacity', $member['capacity'])) ?>">
                        </label>
                        <label class="field">
                            <span>Profile color</span>
                            <select name="color">
                                <?php foreach (['cyan', 'teal', 'amber', 'slate'] as $color): ?>
                                    <option value="<?= e($color) ?>" <?= old_input('color', $member['color']) === $color ? 'selected' : '' ?>><?= e(ucfirst($color)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Salary structure</span>
                            <select name="salary_structure">
                                <?php foreach (['fixed', 'commission', 'hybrid'] as $structure): ?>
                                    <option value="<?= e($structure) ?>" <?= old_input('salary_structure', $member['salary_structure']) === $structure ? 'selected' : '' ?>><?= e(ucfirst($structure)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['salary_structure'])): ?><small><?= e($errors['salary_structure']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Commission rate (%)</span>
                            <input type="number" min="0" step="0.01" name="commission_rate" value="<?= e((string) old_input('commission_rate', (string) $member['commission_rate'])) ?>">
                            <?php if (isset($errors['commission_rate'])): ?><small><?= e($errors['commission_rate']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Fixed pay</span>
                            <input type="number" min="0" step="0.01" name="fixed_pay" value="<?= e((string) old_input('fixed_pay', (string) $member['fixed_pay'])) ?>">
                            <?php if (isset($errors['fixed_pay'])): ?><small><?= e($errors['fixed_pay']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="field">
                        <span>Bio / internal context</span>
                        <textarea name="bio" rows="5"><?= e((string) old_input('bio', $member['bio'])) ?></textarea>
                    </label>

                    <div class="button-row">
                        <a class="button-muted" href="/staff/view.php?id=<?= e($member['id']) ?>">Cancel</a>
                        <button class="button-primary" type="submit">Update therapist</button>
                    </div>
                </form>
            </article>

            <aside class="activity-card">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Current summary</p>
                        <h3><?= e($member['name']) ?></h3>
                    </div>
                </div>

                <div class="info-list">
                    <article class="info-item">
                        <strong><?= e($member['status']) ?></strong>
                        <p><?= e($member['specialty']) ?> · <?= e($member['today_window']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e((string) $member['today_booking_count']) ?> bookings today</strong>
                        <p><?= e((string) $member['upcoming_count']) ?> upcoming · <?= e($member['capacity']) ?></p>
                    </article>
                    <article class="info-item">
                        <strong><?= e(ucfirst($member['salary_structure'])) ?></strong>
                        <p>Completed-booking value <?= e(format_money((float) $member['completed_value'])) ?></p>
                    </article>
                </div>
            </aside>
        </section>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </main>
</div>
<?php clear_old_input(); ?>
