<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
if (strlen($q) > 120) {
    $q = mb_substr($q, 0, 120);
}
if (!preg_match('/^[a-z0-9-]{0,140}$/', $categorySlug)) {
    $categorySlug = '';
}

$sql = 'SELECT g.id, g.name, g.description, g.invite_url, g.image_url,
               c.name AS category_name, c.name_ur AS category_name_ur, c.slug AS category_slug
        FROM groups g
        INNER JOIN categories c ON c.id = g.category_id
        WHERE g.status = \'approved\'';
$params = [];
if ($categorySlug !== '') {
    $sql .= ' AND c.slug = ?';
    $params[] = $categorySlug;
}
if ($q !== '') {
    $sql .= ' AND (g.name LIKE ? ESCAPE \'\\\\\' OR g.description LIKE ? ESCAPE \'\\\\\')';
    $like = like_term($q);
    $params[] = $like;
    $params[] = $like;
} elseif ($categorySlug === '') {
    $sql .= ' AND c.is_adult = 0';
}
$sql .= ' ORDER BY g.created_at DESC, g.id DESC LIMIT 60';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll();

$activeCategory = null;
if ($categorySlug !== '') {
    $catStmt = db()->prepare('SELECT name, name_ur, slug FROM categories WHERE slug = ?');
    $catStmt->execute([$categorySlug]);
    $activeCategory = $catStmt->fetch() ?: null;
}

$pageTitle = $activeCategory ? category_label($activeCategory) : 'WhatsApp groups';
$metaDescription = 'See approved WhatsApp groups on WhatsGrolink.com, search by name, and choose a category.';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>WhatsApp groups</h1>
    <p class="lede">Browse approved invite links on WhatsGrolink.com and join a group.</p>
</section>

<form class="filters" method="get" action="<?= e(href('index.php')) ?>">
    <div>
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($q) ?>" maxlength="120" placeholder="Group name or description">
    </div>
    <div>
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All categories</option>
            <?= category_slug_options($categorySlug) ?>
        </select>
    </div>
    <div class="form-actions" style="align-self:end">
        <button type="submit">Search</button>
        <a class="btn btn-muted" href="<?= e(href('categories.php')) ?>">Categories</a>
    </div>
</form>

<?php if (!$groups): ?>
    <p class="panel">No approved groups matched this search.</p>
    <?php render_ad_slot('home'); ?>
<?php else: ?>
    <?php $homeAdAfter = count($groups) === 1 ? 1 : intdiv(count($groups), 2); ?>
    <section class="cards">
        <?php foreach ($groups as $index => $group): ?>
            <?php if ($index === $homeAdAfter) { render_ad_slot('home'); } ?>
            <?php
            $invite = normalize_invite_url((string) $group['invite_url']);
            $image = normalize_image_url((string) ($group['image_url'] ?? ''));
            ?>
            <article class="card">
                <?= group_image_html($image) ?>
                <a class="tag" href="<?= e(href('index.php?category=' . rawurlencode((string) $group['category_slug']))) ?>"><?= e(category_label(['name' => $group['category_name'], 'name_ur' => $group['category_name_ur']])) ?></a>
                <h2><a href="<?= e(href('group.php?id=' . (int) $group['id'])) ?>"><?= e((string) $group['name']) ?></a></h2>
                <p><?= e(excerpt((string) $group['description'])) ?></p>
                <?php if ($invite): ?>
                    <a class="btn btn-join" href="<?= e($invite) ?>" target="_blank" rel="noopener noreferrer">Join</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if ($homeAdAfter >= count($groups)) { render_ad_slot('home'); } ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
