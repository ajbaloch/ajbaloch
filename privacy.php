<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy Policy';
$metaDescription = 'What WhatsGrolink.com stores: group submissions, contact messages, and the admin password hash.';
require __DIR__ . '/includes/header.php';
?>
<article class="prose">
    <h1>Privacy Policy</h1>
    <p>When you submit a group, WhatsGrolink.com stores the name, invite link, category, description, and optional image URL. Images are not uploaded. Only the URL is kept.</p>
    <p>The contact form stores your name, email, and message so an admin can read it.</p>
    <p>The admin password is stored only as a password_hash value. We do not sell group links.</p>
    <p>An approved group's name, category, description, and invite link are shown on the public site.</p>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
