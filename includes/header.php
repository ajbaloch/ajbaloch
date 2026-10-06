<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'WhatsGrolink.com';
$metaDescription = $metaDescription ?? 'Browse WhatsApp group invite links on WhatsGrolink.com, filter by category, and submit your own group.';
$noindex = $noindex ?? false;
$isAdmin = $isAdmin ?? false;
$fullTitle = $pageTitle === 'WhatsGrolink.com' ? 'WhatsGrolink.com' : $pageTitle . ' | WhatsGrolink.com';
$canonical = site_base();
if ($canonical !== '' && !$noindex) {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $canonical .= $requestUri === '' ? '/' : $requestUri;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta property="og:site_name" content="WhatsGrolink.com">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <?php if ($canonical !== '' && !$noindex): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <?php if ($noindex): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <link rel="icon" href="<?= e(href('assets/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(href('assets/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(href('assets/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(href('assets/style.css')) ?>">
</head>
<body class="<?= $isAdmin ? 'is-admin' : 'is-public' ?>">
<header class="site-header">
    <div class="wrap header-bar">
        <a class="brand" href="<?= e(href('index.php')) ?>">
            <img src="<?= e(href('assets/logo.png')) ?>" alt="WhatsGrolink.com">
        </a>
        <?php if ($isAdmin): ?>
            <nav class="nav" aria-label="Admin">
                <a href="<?= e(href('admin/index.php')) ?>">Dashboard</a>
                <a href="<?= e(href('admin/groups.php')) ?>">Groups</a>
                <a href="<?= e(href('admin/add-group.php')) ?>">Add group</a>
                <a href="<?= e(href('admin/fetch.php')) ?>">Fetch group</a>
                <a href="<?= e(href('admin/scan.php')) ?>">Scan</a>
                <a href="<?= e(href('admin/categories.php')) ?>">Categories</a>
                <a href="<?= e(href('admin/reports.php')) ?>">Reports</a>
                <a href="<?= e(href('admin/messages.php')) ?>">Messages</a>
                <a href="<?= e(href('admin/ads.php')) ?>">Ads</a>
                <a href="<?= e(href('index.php')) ?>">Site</a>
                <form method="post" action="<?= e(href('admin/logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="linkish">Log out</button>
                </form>
            </nav>
        <?php else: ?>
            <nav class="nav" aria-label="Main">
                <a href="<?= e(href('index.php')) ?>">Home</a>
                <a href="<?= e(href('categories.php')) ?>">Categories</a>
                <a href="<?= e(href('submit.php')) ?>">Submit a group</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
<?php if (!$isAdmin) { render_ad_slot('header'); } ?>
<main class="wrap">
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
    <p class="flash flash-<?= e($flash['type'] ?? 'ok') ?>"><?= e($flash['message'] ?? '') ?></p>
<?php endif; ?>
