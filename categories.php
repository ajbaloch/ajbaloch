<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}

$sql = 'SELECT c.id, c.name, c.name_ur, c.slug, c.kind, COUNT(g.id) AS group_count
        FROM categories c
        LEFT JOIN groups g ON g.category_id = c.id AND g.status = \'approved\'';
$params = [];
if ($q !== '') {
    $like = like_term($q);
    $sql .= ' WHERE (c.name LIKE ? ESCAPE \'\\\\\' OR IFNULL(c.name_ur, \'\') LIKE ? ESCAPE \'\\\\\' OR c.slug LIKE ? ESCAPE \'\\\\\')';
    $params = [$like, $like, $like];
    $alias = [
        'usa' => 'united-states',
        'uk' => 'united-kingdom',
        'uae' => 'united-arab-emirates',
        'ksa' => 'saudi-arabia',
    ];
    $key = strtolower($q);
    if (isset($alias[$key])) {
        $sql .= ' OR c.slug = ? OR c.slug LIKE ?';
        $params[] = $alias[$key];
        $params[] = $alias[$key] . '-%';
    }
}
$sql .= ' GROUP BY c.id ORDER BY c.name ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$countries = [];
$topics = [];
foreach ($rows as $row) {
    if ($row['kind'] === 'country') {
        $countries[] = $row;
    } else {
        $topics[] = $row;
    }
}

$pageTitle = 'Categories';
$metaDescription = 'Browse country and topic categories on WhatsGrolink.com.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <h1>Categories</h1>
    <p class="lede">Countries and topics are listed separately. Search by name or slug.</p>
</section>
<form class="filters cat-search" method="get" action="<?= e(href('categories.php')) ?>">
    <div>
        <label for="q">Search categories</label>
        <input id="q" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="For example Pakistan, jobs, cricket">
    </div>
    <div class="form-actions" style="align-self:end">
        <button type="submit">Search</button>
    </div>
</form>

<?php
$sections = [
    'Countries' => $countries,
    'Topics' => $topics,
];
foreach ($sections as $heading => $items):
    if (!$items) {
        continue;
    }
    ?>
    <section class="cat-block">
        <h2><?= e($heading) ?> <span class="muted">(<?= count($items) ?>)</span></h2>
        <div class="cat-grid">
            <?php foreach ($items as $item): ?>
                <a class="cat-item" href="<?= e(href('index.php?category=' . rawurlencode((string) $item['slug']))) ?>">
                    <span><?= e(category_label($item)) ?></span>
                    <span><?= (int) $item['group_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if (!$countries && !$topics): ?>
    <p class="panel">No categories matched.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
