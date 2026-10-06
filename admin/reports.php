<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$rows = db()->query(
    'SELECT r.id, r.reason, r.created_at, g.id AS group_id, g.name, g.status
     FROM reports r
     INNER JOIN groups g ON g.id = r.group_id
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 200'
)->fetchAll();

$pageTitle = 'Reports';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Reports</h1>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Group</th>
                <th>Reason</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e((string) $row['name']) ?></td>
                <td><?= e((string) $row['reason']) ?></td>
                <td class="ltr"><?= e((string) $row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="3">No reports.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
