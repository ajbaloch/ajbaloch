<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/scan.php';
require_admin();

$errors = [];
$invite = trim((string) ($_POST['invite_url'] ?? ''));
$name = '';
$description = '';
$image = '';
$categoryId = 0;
$showDetails = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'fetch') {
        try {
            $preview = fetch_invite_preview($invite);
            $invite = (string) $preview['invite_url'];
            $name = (string) $preview['name'];
            $description = (string) $preview['description'];
            $image = (string) $preview['image_url'];
            $showDetails = true;
        } catch (ScanException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($action === 'save') {
        $showDetails = true;
        $invite = normalize_invite_url($invite) ?? '';
        $name = clean_text((string) ($_POST['name'] ?? ''), 120);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $description = clean_text((string) ($_POST['description'] ?? ''), 2000);
        $imageNormalized = normalize_image_url(trim((string) ($_POST['image_url'] ?? '')));

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
            $errors[] = 'Write a description.';
        }
        if ($imageNormalized === null) {
            $errors[] = 'The image URL must be http or https.';
        } elseif ($imageNormalized !== '' && placeholder_invite_image($imageNormalized)) {
            $errors[] = 'Use the group image, not the default WhatsApp logo.';
        }

        if (!$errors) {
            $saved = create_group(
                $name,
                $invite,
                $categoryId,
                $description,
                $imageNormalized === '' ? null : $imageNormalized,
                'approved'
            );
            if (!$saved) {
                $errors[] = 'This invite link is already listed.';
                $image = is_string($imageNormalized) ? $imageNormalized : '';
            } else {
                set_flash('Group fetched and approved.');
                redirect(href('admin/fetch.php'));
            }
        } else {
            $image = is_string($imageNormalized) ? $imageNormalized : trim((string) ($_POST['image_url'] ?? ''));
        }
    }
}

$pageTitle = 'Fetch group';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Fetch group</h1>
<p class="lede">Paste one WhatsApp invite link. The site reads the group name from the public invite page, plus the description and image when that page includes them.</p>
<?php if ($errors): ?>
    <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<form class="stack-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="fetch">
    <div>
        <label for="invite_url">Invite link</label>
        <input id="invite_url" name="invite_url" class="ltr" maxlength="500" required placeholder="https://chat.whatsapp.com/..." value="<?= e($invite) ?>">
    </div>
    <div class="form-actions"><button type="submit">Fetch group</button></div>
</form>

<?php if ($showDetails): ?>
    <form class="stack-form" method="post" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="invite_url" value="<?= e($invite) ?>">
        <?php if ($image !== '' && normalize_image_url($image)): ?>
            <div><?= group_image_html($image) ?></div>
        <?php endif; ?>
        <div>
            <label for="name">Name</label>
            <input id="name" name="name" maxlength="120" required value="<?= e($name) ?>">
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
            <?php if ($description === ''): ?>
                <p class="help">The invite page did not include a description. Write one before saving.</p>
            <?php endif; ?>
        </div>
        <div>
            <label for="image_url">Image URL (optional)</label>
            <input id="image_url" name="image_url" class="ltr" maxlength="500" value="<?= e($image) ?>">
        </div>
        <div class="form-actions"><button type="submit">Save group</button></div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
