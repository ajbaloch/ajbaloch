<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$rows = db()->query(
    'SELECT id, name, email, message, created_at
     FROM messages
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
)->fetchAll();

$pageTitle = 'Messages';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Messages</h1>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Message</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e((string) $row['name']) ?></td>
                <td class="ltr"><?= e((string) $row['email']) ?></td>
                <td><?= nl2br(e((string) $row['message'])) ?></td>
                <td class="ltr"><?= e((string) $row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="4">No messages.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
