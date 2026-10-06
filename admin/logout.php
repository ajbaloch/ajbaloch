<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(href('admin/index.php'));
}
csrf_verify();
$_SESSION = [];
session_destroy();
redirect(href('admin/login.php'));
