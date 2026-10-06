<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$name = '';
$email = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = clean_text((string) ($_POST['name'] ?? ''), 120);
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = clean_text((string) ($_POST['message'] ?? ''), 2000);

    if (mb_strlen($name) < 2 || preg_match('/[\r\n]/', $name)) {
        $errors[] = 'Enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email.';
    }
    if (mb_strlen($message) < 10) {
        $errors[] = 'Write a message.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO messages (name, email, message) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $message]);
        set_flash('Your message was received.');
        redirect(href('contact.php'));
    }
}

$pageTitle = 'Contact';
$metaDescription = 'Contact WhatsGrolink.com. Send your name, email, and message.';
require __DIR__ . '/includes/header.php';
?>
<article class="prose">
    <h1>Contact</h1>
    <p>Send a question, correction, or general note. The message is stored so an admin can read it.</p>
</article>
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
        <label for="email">Email</label>
        <input id="email" name="email" class="ltr" type="email" maxlength="190" required value="<?= e($email) ?>">
    </div>
    <div>
        <label for="message">Message</label>
        <textarea id="message" name="message" maxlength="2000" required><?= e($message) ?></textarea>
    </div>
    <div class="form-actions"><button type="submit">Send</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
