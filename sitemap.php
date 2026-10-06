<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$base = site_base();
if ($base === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Set base_url in config.php.');
}

header('Content-Type: application/xml; charset=UTF-8');

$paths = [
    '/',
    '/index.php',
    '/categories.php',
    '/submit.php',
    '/about.php',
    '/contact.php',
    '/disclaimer.php',
    '/privacy.php',
    '/terms.php',
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$emit = static function (string $loc) use ($base): void {
    echo '  <url><loc>' . e($base . $loc) . '</loc></url>' . "\n";
};

foreach ($paths as $path) {
    $emit($path);
}

$categories = db()->query('SELECT slug FROM categories ORDER BY id ASC')->fetchAll();
foreach ($categories as $category) {
    $emit('/index.php?category=' . rawurlencode((string) $category['slug']));
}

$groups = db()->query('SELECT id FROM groups WHERE status = \'approved\' ORDER BY id ASC')->fetchAll();
foreach ($groups as $group) {
    $emit('/group.php?id=' . (int) $group['id']);
}

echo '</urlset>';
