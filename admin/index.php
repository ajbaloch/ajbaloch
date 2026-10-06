<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = (string) ($_POST['current_password'] ?? '');
    $next = (string) ($_POST['new_password'] ?? '');
    $again = (string) ($_POST['confirm_password'] ?? '');
    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([(int) $admin['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, (string) $row['password_hash'])) {
        $errors[] = 'The current password is not correct.';
    }
    if (strlen($next) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    }
    if ($next !== $again) {
        $errors[] = 'Type the same new password in both fields.';
    }
    if (!$errors) {
        $update = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($next, PASSWORD_DEFAULT), (int) $admin['id']]);
        set_flash('Password changed.');
        redirect(href('admin/index.php'));
    }
}

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
foreach (db()->query('SELECT status, COUNT(*) AS total FROM groups GROUP BY status') as $row) {
    $counts[(string) $row['status']] = (int) $row['total'];
}
$reports = (int) db()->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$messages = (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$categories = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();

$pageTitle = 'Dashboard';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Dashboard</h1>
<p class="lede">Welcome, <?= e((string) $admin['username']) ?>. Change the default password.</p>
<section class="counts">
    <div class="count"><strong><?= $counts['pending'] ?></strong>Pending</div>
    <div class="count"><strong><?= $counts['approved'] ?></strong>Approved</div>
    <div class="count"><strong><?= $counts['rejected'] ?></strong>Rejected</div>
    <div class="count"><strong><?= $reports ?></strong>Reports</div>
    <div class="count"><strong><?= $messages ?></strong>Messages</div>
    <div class="count"><strong><?= $categories ?></strong>Categories</div>
</section>

<section class="stack-form">
    <h2>Change password</h2>
    <?php if ($errors): ?>
        <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div>
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        </div>
        <div>
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8">
        </div>
        <div>
            <label for="confirm_password">New password again</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">
        </div>
        <div class="form-actions"><button type="submit">Save</button></div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
