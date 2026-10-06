<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__) . '/config.php';
    if (!is_file($path)) {
        http_response_code(500);
        exit('Missing config.php. Copy config.sample.php to config.php and set the database credentials.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        http_response_code(500);
        exit('config.php must return an array.');
    }

    $config = $loaded;
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) ($c['db_host'] ?? '127.0.0.1'),
        (int) ($c['db_port'] ?? 3306),
        (string) ($c['db_name'] ?? ''),
        (string) ($c['db_charset'] ?? 'utf8mb4')
    );

    try {
        $pdo = new PDO($dsn, (string) ($c['db_user'] ?? ''), (string) ($c['db_pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection failed. Check config.php and import schema.sql.');
    }

    return $pdo;
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (str_ends_with($dir, '/admin')) {
        $dir = dirname($dir);
    }
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }

    $base = $dir;
    return $base;
}

function href(string $path): string
{
    $prefix = base_path();
    if ($path === '' || $path === '/') {
        return $prefix === '' ? '/' : $prefix . '/';
    }
    return $prefix . '/' . ltrim($path, '/');
}

function site_base(): string
{
    return rtrim((string) (config()['base_url'] ?? ''), '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(403);
        exit('Invalid request token. Go back and try again.');
    }
}

function normalize_invite_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 500) {
        return null;
    }
    if (preg_match('/[\s\x00-\x1F\\\\@]/', $url)) {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return null;
    }
    if (isset($parts['port']) && (int) $parts['port'] !== 443) {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = rawurldecode((string) ($parts['path'] ?? ''));
    if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
        return null;
    }

    if ($host === 'chat.whatsapp.com') {
        if (!preg_match('#^/[A-Za-z0-9]{10,64}$#', $path)) {
            return null;
        }
    } elseif ($host === 'wa.me') {
        if (preg_match('#^/\+([0-9]{10,15})$#', $path, $match)) {
            $path = '/' . $match[1];
        }
        $phone = preg_match('#^/[0-9]{10,15}$#', $path) === 1;
        $code = preg_match('#^/[A-Za-z0-9]{10,64}$#', $path) === 1;
        $message = preg_match('#^/message/[A-Za-z0-9]{8,64}$#', $path) === 1;
        if (!$phone && !$code && !$message) {
            return null;
        }
    } else {
        return null;
    }

    return 'https://' . $host . $path;
}

function normalize_image_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (strlen($url) > 500 || preg_match('/[\s\x00-\x1F\\\\]/', $url)) {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    $host = (string) ($parts['host'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
        return null;
    }

    return $url;
}

function like_term(string $value): string
{
    return '%' . addcslashes($value, '%_\\') . '%';
}

function group_image_html(?string $url): string
{
    $image = normalize_image_url((string) $url);
    $img = '';
    if (is_string($image) && $image !== '') {
        $img = '<img src="' . e($image) . '" alt="" referrerpolicy="no-referrer" onerror="this.remove()">';
    }
    return '<span class="thumb" aria-hidden="true">' . $img . '</span>';
}

function excerpt(string $text, int $length = 160): string
{
    $text = trim((string) (preg_replace('/\s+/u', ' ', $text) ?? ''));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function category_label(array $category): string
{
    return (string) ($category['name'] ?? '');
}

function all_categories(): array
{
    return db()->query(
        'SELECT id, name, name_ur, slug, kind FROM categories ORDER BY kind ASC, name ASC'
    )->fetchAll();
}

function category_slug_options(string $selected = ''): string
{
    return category_option_html(all_categories(), $selected, 'slug');
}

function category_id_options(int $selected = 0): string
{
    return category_option_html(all_categories(), (string) $selected, 'id');
}

function category_option_html(array $rows, string $selected, string $valueKey): string
{
    $labels = ['country' => 'Countries', 'topic' => 'Topics'];
    $html = '';
    $current = null;
    foreach ($rows as $row) {
        $kind = (string) $row['kind'];
        if ($kind !== $current) {
            if ($current !== null) {
                $html .= '</optgroup>';
            }
            $current = $kind;
            $html .= '<optgroup label="' . e($labels[$kind] ?? $kind) . '">';
        }
        $value = (string) $row[$valueKey];
        $isSelected = $value === $selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e(category_label($row)) . '</option>';
    }
    if ($current !== null) {
        $html .= '</optgroup>';
    }
    return $html;
}

function category_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    return (bool) $stmt->fetch();
}

function category_slug_base(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim((string) $slug, '-');
    if (strlen($slug) > 120) {
        $slug = rtrim(substr($slug, 0, 120), '-');
    }
    return $slug;
}

function lookup_category_id(string $name): ?int
{
    $name = clean_text($name, 120);
    $slug = category_slug_base($name);
    if ($name === '' || $slug === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT id FROM categories WHERE slug = ? OR LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$slug, $name]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function find_or_create_category(string $name): array
{
    $name = clean_text($name, 120);
    if (mb_strlen($name) < 2) {
        $name = 'Imported';
    }
    $existing = lookup_category_id($name);
    if ($existing) {
        return ['id' => $existing, 'created' => false];
    }

    $slug = unique_slug($name);
    $stmt = db()->prepare('INSERT INTO categories (name, name_ur, slug, kind) VALUES (?, NULL, ?, ?)');
    try {
        $stmt->execute([$name, $slug, 'topic']);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $existing = lookup_category_id($name);
            if ($existing) {
                return ['id' => $existing, 'created' => false];
            }
        }
        throw $e;
    }

    return ['id' => (int) db()->lastInsertId(), 'created' => true];
}

function unique_slug(string $name): string
{
    $slug = category_slug_base($name);
    if ($slug === '') {
        $slug = 'cat';
    }

    $base = $slug;
    for ($i = 2; $i < 60; $i++) {
        $stmt = db()->prepare('SELECT id FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
    }

    return $base . '-' . bin2hex(random_bytes(3));
}

function create_group(
    string $name,
    string $inviteUrl,
    int $categoryId,
    string $description,
    ?string $imageUrl,
    string $status
): bool {
    $stmt = db()->prepare(
        'INSERT INTO groups (name, invite_url, category_id, description, image_url, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    try {
        $stmt->execute([$name, $inviteUrl, $categoryId, $description, $imageUrl, $status]);
        return true;
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return false;
        }
        throw $e;
    }
}

function current_admin(): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([(int) $id]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(href('admin/login.php'));
    }
    if (PHP_SAPI !== 'cli') {
        header('X-Robots-Tag: noindex, nofollow');
    }
    return $admin;
}

function ad_slot_names(): array
{
    return ['header', 'home', 'group', 'footer'];
}

function ad_codes(): array
{
    static $codes = null;
    if (is_array($codes)) {
        return $codes;
    }

    $codes = [];
    foreach (db()->query('SELECT slot, code FROM ad_slots') as $row) {
        $codes[(string) $row['slot']] = (string) $row['code'];
    }
    return $codes;
}

function ad_code(string $slot): string
{
    if (!in_array($slot, ad_slot_names(), true)) {
        return '';
    }
    $code = ad_codes()[$slot] ?? '';
    return trim($code) === '' ? '' : $code;
}

function render_ad_slot(string $slot): void
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, '/admin/')) {
        return;
    }
    $code = ad_code($slot);
    if ($code === '') {
        return;
    }
    $class = 'ad-slot ad-slot-' . $slot;
    if ($slot === 'header') {
        $class .= ' wrap';
    }
    echo '<div class="', $class, '">', $code, '</div>';
}

function clean_text(string $value, int $max): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    $value = trim((string) (preg_replace('/[ \t]+/u', ' ', $value) ?? ''));
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}
