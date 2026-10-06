<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id > 0 && $action === 'delete') {
        $stmt = db()->prepare('DELETE FROM groups WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('Group deleted.');
    } elseif ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = db()->prepare('UPDATE groups SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        set_flash($status === 'approved' ? 'Group approved.' : 'Group rejected.');
    }
    $back = (string) ($_POST['back'] ?? '');
    if (!in_array($back, ['', 'pending', 'approved', 'rejected'], true)) {
        $back = '';
    }
    redirect(href('admin/groups.php' . ($back === '' ? '' : '?status=' . $back)));
}

$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
    $status = '';
}

$sql = 'SELECT g.id, g.name, g.invite_url, g.status, g.created_at, c.name AS category_name, c.name_ur
        FROM groups g
        INNER JOIN categories c ON c.id = g.category_id';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE g.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY g.created_at DESC, g.id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll();

$labels = [
    '' => 'All',
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];
$statusLabel = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

$pageTitle = 'Groups';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Groups</h1>
<div class="tabs">
    <?php foreach ($labels as $key => $label): ?>
        <a class="btn <?= $status === $key ? '' : 'btn-muted' ?> btn-small" href="<?= e(href('admin/groups.php' . ($key === '' ? '' : '?status=' . $key))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Link</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $group): ?>
            <tr>
                <td><?= e((string) $group['name']) ?></td>
                <td><?= e(category_label(['name' => $group['category_name'], 'name_ur' => $group['name_ur']])) ?></td>
                <td><?= e($statusLabel[(string) $group['status']] ?? (string) $group['status']) ?></td>
                <td class="ltr"><?= e((string) $group['invite_url']) ?></td>
                <td>
                    <div class="row-actions">
                        <?php if ($group['status'] !== 'approved'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($status) ?>">
                                <button class="btn-small" name="action" value="approve" type="submit">Approve</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($group['status'] !== 'rejected'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($status) ?>">
                                <button class="btn-small btn-muted" name="action" value="reject" type="submit">Reject</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this group?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($status) ?>">
                            <button class="btn-small btn-danger" name="action" value="delete" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
            <tr><td colspan="5">No groups.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
