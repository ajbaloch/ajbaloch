<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT g.id, g.name, g.description, g.invite_url, g.image_url, g.status,
            c.name AS category_name, c.name_ur AS category_name_ur, c.slug AS category_slug
     FROM groups g
     INNER JOIN categories c ON c.id = g.category_id
     WHERE g.id = ? AND g.status = \'approved\''
);
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    http_response_code(404);
    $pageTitle = 'Group not found';
    require __DIR__ . '/includes/header.php';
    echo '<h1>Group not found</h1><p>This link does not exist, or it has not been approved yet.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $reason = clean_text((string) ($_POST['reason'] ?? ''), 500);
    if (mb_strlen($reason) < 5) {
        $errors[] = 'Write a reason of at least 5 characters.';
    }
    if (!$errors) {
        $insert = db()->prepare('INSERT INTO reports (group_id, reason) VALUES (?, ?)');
        $insert->execute([(int) $group['id'], $reason]);
        set_flash('Report saved. Thank you.');
        redirect(href('group.php?id=' . (int) $group['id']));
    }
}

$invite = normalize_invite_url((string) $group['invite_url']);
$image = normalize_image_url((string) ($group['image_url'] ?? ''));
$pageTitle = (string) $group['name'];
$metaDescription = excerpt((string) $group['description'], 150);
require __DIR__ . '/includes/header.php';
?>
<article class="detail">
    <?= group_image_html($image) ?>
    <a class="tag" href="<?= e(href('index.php?category=' . rawurlencode((string) $group['category_slug']))) ?>"><?= e(category_label(['name' => $group['category_name'], 'name_ur' => $group['category_name_ur']])) ?></a>
    <h1><?= e((string) $group['name']) ?></h1>
    <?php render_ad_slot('group'); ?>
    <p><?= nl2br(e((string) $group['description'])) ?></p>
    <?php if ($invite): ?>
        <p><a class="btn btn-join" href="<?= e($invite) ?>" target="_blank" rel="noopener noreferrer">Join</a></p>
        <p class="help ltr"><?= e($invite) ?></p>
    <?php endif; ?>
</article>

<section class="stack-form" style="margin-top:18px">
    <h2>Report</h2>
    <p class="help">If the link is wrong, closed, or inappropriate, send a short reason.</p>
    <?php if ($errors): ?>
        <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="reason">Reason</label>
        <textarea id="reason" name="reason" maxlength="500" required><?= e((string) ($_POST['reason'] ?? '')) ?></textarea>
        <div class="form-actions"><button type="submit">Send report</button></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
