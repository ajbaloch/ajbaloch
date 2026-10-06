<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (current_admin()) {
    redirect(href('admin/index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    $hash = $admin['password_hash'] ?? '$2y$10$taHMrjN4jUQ2lhikAOzsQuRiM7Dzb38ILpRbQVi3jcKFydR9HZBfS';
    $valid = password_verify($password, (string) $hash);
    if ($admin && $valid) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        redirect(href('admin/index.php'));
    }
    $error = 'Wrong username or password.';
}

$pageTitle = 'Admin login';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<form class="login-card stack-form" method="post">
    <h1>Admin login</h1>
    <?= csrf_field() ?>
    <?php if ($error): ?><p class="flash flash-bad"><?= e($error) ?></p><?php endif; ?>
    <div>
        <label for="username">Username</label>
        <input id="username" name="username" class="ltr" autocomplete="username" required maxlength="60">
    </div>
    <div>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit">Log in</button>
    <p class="login-extra"><a href="<?= e(href('admin/forgot.php')) ?>">Forgot password</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
