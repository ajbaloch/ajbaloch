<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/password_reset.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$errors = [];
$row = valid_reset_admin($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = (string) ($_POST['new_password'] ?? '');
    $again = (string) ($_POST['confirm_password'] ?? '');
    if (!$row) {
        $errors[] = 'This reset link is invalid or expired.';
    }
    if (strlen($next) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    }
    if ($next !== $again) {
        $errors[] = 'Type the same new password in both fields.';
    }
    if (!$errors && spend_reset_token($token, $next)) {
        set_flash('Password updated. You can log in.');
        redirect(href('admin/login.php'));
    }
    if (!$errors) {
        $errors[] = 'This reset link is invalid or expired.';
        $row = null;
    }
}

$pageTitle = 'Set a new password';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<form class="login-card stack-form" method="post">
    <h1>Set a new password</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?php if ($errors): ?>
        <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($row): ?>
        <div>
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8">
        </div>
        <div>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">
        </div>
        <button type="submit">Save password</button>
    <?php elseif (!$errors): ?>
        <p class="flash flash-bad">This reset link is invalid or expired.</p>
        <p class="login-extra"><a href="<?= e(href('admin/forgot.php')) ?>">Forgot password</a></p>
    <?php endif; ?>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
