<?php declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = current_user();
if ($currentUser !== null) {
    redirect_to('/dashboard.php');
}

$redirect = trim((string) ($_GET['redirect'] ?? ''));
$errors = flash_get('login_errors', []);
$flashMessage = flash_get('auth_success');
$pageTitle = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle . ' | ' . app_config('app_name', "Annie's Massages Admin")) ?></title>
    <?php require __DIR__ . '/includes/css.php'; ?>
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-layout auth-layout-centered">
            <article class="auth-card">
                <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
                    <div class="notice-banner notice-banner-success"><?= e($flashMessage) ?></div>
                <?php endif; ?>

                <div class="section-head auth-card-head">
                    <div>
                        <p class="section-kicker">Authentication</p>
                        <h3>Sign in to your account</h3>
                    </div>
                </div>

                <form class="module-form" method="post" action="/process/login.php">
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <label class="field">
                        <span>Email address</span>
                        <input type="email" name="email" value="<?= e((string) old_input('email', 'codesyre@gmail.com')) ?>" placeholder="codesyre@gmail.com">
                        <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Enter your account password">
                        <?php if (isset($errors['password'])): ?><small><?= e($errors['password']) ?></small><?php endif; ?>
                    </label>

                    <div class="button-row auth-button-row">
                        <button class="button-primary auth-submit" type="submit">Signin</button>
                    </div>
                </form>
            </article>
        </section>
    </main>
</body>
<?php clear_old_input(); ?>
<?php require __DIR__ . '/includes/js.php'; ?>
</html>
