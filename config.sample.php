<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and set your own database password.
 * base_url has no trailing slash. It is used for sitemap.xml, robots.txt, and password reset links.
 * Leave mail_from empty on a local copy. Forgot password then shows the reset link.
 * On the public site, set base_url to the real https address and set mail_from. The reset link is emailed and is not shown on the page.
 */
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'whatsgroup',
    'db_user' => 'whatsgroup',
    'db_pass' => 'change-me',
    'db_charset' => 'utf8mb4',
    'base_url' => 'http://localhost:8080',
    'mail_from' => '',
];
