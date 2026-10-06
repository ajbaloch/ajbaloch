<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $countStmt = db()->prepare('SELECT COUNT(*) FROM groups WHERE category_id = ?');
        $countStmt->execute([$id]);
        if ((int) $countStmt->fetchColumn() > 0) {
            set_flash('This category still has groups, so it cannot be deleted.', 'bad');
        } else {
            $delete = db()->prepare('DELETE FROM categories WHERE id = ?');
            $delete->execute([$id]);
            set_flash('Category deleted.');
        }
        redirect(href('admin/categories.php'));
    }

    if ($action === 'add') {
        $name = clean_text((string) ($_POST['name'] ?? ''), 120);
        $nameUr = clean_text((string) ($_POST['name_ur'] ?? ''), 120);
        $kind = (string) ($_POST['kind'] ?? 'topic');
        if (!in_array($kind, ['country', 'topic'], true)) {
            $kind = 'topic';
        }
        $adult = isset($_POST['is_adult']) ? 1 : 0;
        if (mb_strlen($name) < 2) {
            $errors[] = 'Enter an English category name.';
        } else {
            $slug = unique_slug($name);
            $stmt = db()->prepare('INSERT INTO categories (name, name_ur, slug, kind, is_adult) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $nameUr === '' ? null : $nameUr, $slug, $kind, $adult]);
            set_flash('Category added.');
            redirect(href('admin/categories.php'));
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT c.id, c.name, c.name_ur, c.slug, c.kind, c.is_adult, COUNT(g.id) AS group_count
        FROM categories c
        LEFT JOIN groups g ON g.category_id = c.id';
$params = [];
if ($q !== '') {
    $like = like_term(mb_substr($q, 0, 80));
    $sql .= ' WHERE c.name LIKE ? ESCAPE \'\\\\\' OR IFNULL(c.name_ur, \'\') LIKE ? ESCAPE \'\\\\\' OR c.slug LIKE ? ESCAPE \'\\\\\'';
    $params = [$like, $like, $like];
}
$sql .= ' GROUP BY c.id ORDER BY c.kind ASC, c.name ASC LIMIT 800';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Categories';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Categories</h1>
<?php if ($errors): ?>
    <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<form class="stack-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div>
        <label for="name">English name</label>
        <input id="name" name="name" maxlength="120" required>
    </div>
    <div>
        <label for="name_ur">Urdu name (optional)</label>
        <input id="name_ur" name="name_ur" maxlength="120">
    </div>
    <div>
        <label for="kind">Kind</label>
        <select id="kind" name="kind">
            <option value="topic">Topic</option>
            <option value="country">Country</option>
        </select>
    </div>
    <div>
        <label for="is_adult"><input id="is_adult" name="is_adult" type="checkbox" value="1"> Adult</label>
    </div>
    <div class="form-actions"><button type="submit">Add category</button></div>
</form>

<form class="filters" method="get" action="<?= e(href('admin/categories.php')) ?>" style="margin-top:18px">
    <div>
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($q) ?>" maxlength="80">
    </div>
    <div class="form-actions" style="align-self:end"><button type="submit">Search</button></div>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Kind</th>
                <th>Adult</th>
                <th>Groups</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e((string) $row['name']) ?></td>
                <td class="ltr"><?= e((string) $row['slug']) ?></td>
                <td><?= $row['kind'] === 'country' ? 'Country' : 'Topic' ?></td>
                <td><?= (int) $row['is_adult'] === 1 ? 'Yes' : 'No' ?></td>
                <td><?= (int) $row['group_count'] ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="help">The list shows at most 800 categories. Use search to find the rest.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
