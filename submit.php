<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$name = '';
$invite = '';
$categoryId = 0;
$description = '';
$image = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = clean_text((string) ($_POST['name'] ?? ''), 120);
    $inviteRaw = trim((string) ($_POST['invite_url'] ?? ''));
    $invite = normalize_invite_url($inviteRaw) ?? '';
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = clean_text((string) ($_POST['description'] ?? ''), 2000);
    $imageRaw = trim((string) ($_POST['image_url'] ?? ''));
    $imageNormalized = normalize_image_url($imageRaw);

    if (mb_strlen($name) < 2) {
        $errors[] = 'Enter the group name.';
    }
    if ($invite === '') {
        $errors[] = 'Only https://chat.whatsapp.com/ or https://wa.me/ links are allowed.';
    }
    if ($categoryId < 1 || !category_exists($categoryId)) {
        $errors[] = 'Choose a category.';
    }
    if (mb_strlen($description) < 10) {
        $errors[] = 'Write a short description.';
    }
    if ($imageNormalized === null) {
        $errors[] = 'The image URL must be http or https.';
    }

    if (!$errors) {
        $saved = create_group(
            $name,
            $invite,
            $categoryId,
            $description,
            $imageNormalized === '' ? null : $imageNormalized,
            'pending'
        );
        if (!$saved) {
            $errors[] = 'This invite link is already listed.';
        } else {
            set_flash('Your group is pending. It will appear after approval.');
            redirect(href('submit.php'));
        }
    }
}

$pageTitle = 'Submit a group';
$metaDescription = 'Submit your WhatsApp group to WhatsGrolink.com. New links appear after an admin approves them.';
require __DIR__ . '/includes/header.php';
?>
<h1>Submit a group</h1>
<p class="lede">A new group is not published immediately. It appears on the home page after an admin approves it.</p>
<?php if ($errors): ?>
    <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<form class="stack-form" method="post">
    <?= csrf_field() ?>
    <div>
        <label for="name">Name</label>
        <input id="name" name="name" maxlength="120" required value="<?= e($name) ?>">
    </div>
    <div>
        <label for="invite_url">Invite link</label>
        <input id="invite_url" name="invite_url" class="ltr" inputmode="url" maxlength="500" required placeholder="https://chat.whatsapp.com/..." value="<?= e((string) ($_POST['invite_url'] ?? '')) ?>">
    </div>
    <div>
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Choose</option>
            <?= category_id_options($categoryId) ?>
        </select>
    </div>
    <div>
        <label for="description">Description</label>
        <textarea id="description" name="description" maxlength="2000" required><?= e($description) ?></textarea>
    </div>
    <div>
        <label for="image_url">Image URL (optional)</label>
        <input id="image_url" name="image_url" class="ltr" inputmode="url" maxlength="500" value="<?= e((string) ($_POST['image_url'] ?? '')) ?>">
    </div>
    <div class="form-actions"><button type="submit">Submit</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
