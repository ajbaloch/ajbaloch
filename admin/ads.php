<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$labels = [
    'header' => 'Header, under the nav',
    'home' => 'Home, between the group cards',
    'group' => 'Group page, under the title',
    'footer' => 'Footer, above the footer links',
];

$errors = [];
$saved = ad_codes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = [];
    foreach (ad_slot_names() as $slot) {
        $code = str_replace("\0", '', (string) ($_POST[$slot] ?? ''));
        if (trim($code) === '') {
            $code = '';
        }
        if (strlen($code) > 100000) {
            $errors[] = $labels[$slot] . ' is too long.';
        }
        $next[$slot] = $code;
    }
    if (!$errors) {
        $upsert = db()->prepare(
            'INSERT INTO ad_slots (slot, code) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code)'
        );
        foreach ($next as $slot => $code) {
            $upsert->execute([$slot, $code]);
        }
        set_flash('Ad code saved.');
        redirect(href('admin/ads.php'));
    }
    $saved = $next;
}

$pageTitle = 'Ads';
$noindex = true;
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<h1>Ads</h1>
<p class="lede">Paste code from Google AdSense or another ad network. An empty slot is not shown. Script tags are kept.</p>
<?php if ($errors): ?>
    <ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<form class="stack-form" method="post">
    <?= csrf_field() ?>
    <?php foreach ($labels as $slot => $label): ?>
        <div>
            <label for="ad-<?= e($slot) ?>"><?= e($label) ?></label>
            <textarea class="ad-code ltr" id="ad-<?= e($slot) ?>" name="<?= e($slot) ?>" spellcheck="false"><?= e($saved[$slot] ?? '') ?></textarea>
        </div>
    <?php endforeach; ?>
    <div class="form-actions"><button type="submit">Save</button></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
