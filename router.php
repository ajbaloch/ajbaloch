<?php

declare(strict_types=1);

/**
 * Router for the PHP built-in server:
 *   php -S localhost:8080 router.php
 *
 * Static files and normal PHP scripts are left to the server.
 * sitemap.xml and robots.txt are generated from the database and config.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

if ($uri === '/robots.txt') {
    require_once __DIR__ . '/includes/bootstrap.php';
    $base = rtrim((string) (config()['base_url'] ?? ''), '/');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\n\n";
    if ($base !== '') {
        echo 'Sitemap: ' . $base . "/sitemap.xml\n";
    }
    return true;
}

return false;
