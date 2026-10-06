<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/password_reset.php';

$error = '';
$notice = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    if ($username === '' || strlen($username) > 60) {
        $error = 'Enter the admin username.';
    } else {
        $admin = find_admin_by_username($username);
        $public = site_url_is_public();
        $sent = false;
        $link = '';
        if ($admin) {
            $token = create_reset_token((int) $admin['id']);
            $link = reset_url($token);
            $email = trim((string) ($admin['email'] ?? ''));
            if ($email !== '' && mail_from_address() !== '') {
                $sent = send_reset_email($email, $link);
            }
            if ($public || $sent) {
                if (!$sent) {
                    delete_reset_token($token);
                }
                $link = '';
            }
        }

        if ($public) {
            $notice = 'If that username has an email on file, a reset link was sent. It expires in 30 minutes.';
        } elseif (!$admin) {
            $error = 'No admin account uses that username.';
        } elseif ($sent) {
            $notice = 'A reset link was sent to the email on that account. It expires in 30 minutes.';
        } else {
            $notice = 'Mail is not set up on this copy. Use this one-time link to set a new password. It expires in 30 minutes.';
            $resetLink = $link;
        }
    }
}

$pageTitle = 'Forgot password';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<form class="login-card stack-form" method="post">
    <h1>Forgot password</h1>
    <p class="help">Enter the admin username. The reset link expires in 30 minutes and works once.</p>
    <?= csrf_field() ?>
    <?php if ($error): ?><p class="flash flash-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><p class="flash flash-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($resetLink !== ''): ?>
        <p><a class="reset-link ltr" href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a></p>
    <?php endif; ?>
    <div>
        <label for="username">Username</label>
        <input id="username" name="username" class="ltr" autocomplete="username" required maxlength="60" value="<?= e((string) ($_POST['username'] ?? '')) ?>">
    </div>
    <button type="submit">Send reset link</button>
    <p class="login-extra"><a href="<?= e(href('admin/login.php')) ?>">Back to login</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
