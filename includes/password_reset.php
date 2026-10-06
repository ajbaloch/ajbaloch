<?php

declare(strict_types=1);

function site_url_is_public(): bool
{
    $host = strtolower((string) (parse_url(site_base(), PHP_URL_HOST) ?? ''));
    $host = trim($host, '[]');
    return $host !== '' && $host !== 'localhost' && $host !== '127.0.0.1' && $host !== '::1';
}

function mail_from_address(): string
{
    $from = trim((string) (config()['mail_from'] ?? ''));
    if ($from === '' || preg_match('/[\r\n]/', $from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $from;
}

function reset_url(string $token): string
{
    $base = site_base();
    $path = href('admin/reset.php') . '?token=' . rawurlencode($token);
    return ($base !== '' ? $base : '') . $path;
}

function create_reset_token(int $adminId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $delete = db()->prepare('DELETE FROM password_resets WHERE admin_id = ?');
    $delete->execute([$adminId]);
    $insert = db()->prepare(
        'INSERT INTO password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $insert->execute([$adminId, $hash]);
    return $token;
}

function delete_reset_token(string $token): void
{
    $stmt = db()->prepare('DELETE FROM password_resets WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $token)]);
}

function find_admin_by_username(string $username): ?array
{
    $stmt = db()->prepare('SELECT id, username, email FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function send_reset_email(string $email, string $link): bool
{
    $from = mail_from_address();
    if ($from === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        return false;
    }
    $subject = 'Reset your WhatsGrolink.com admin password';
    $body = "Use this link to set a new admin password. It expires in 30 minutes and works once.\n\n" . $link . "\n";
    $headers = 'From: WhatsGrolink.com <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
    return mail($email, $subject, $body, $headers);
}

function valid_reset_admin(string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT r.admin_id, a.username
         FROM password_resets r
         INNER JOIN admins a ON a.id = r.admin_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function spend_reset_token(string $token, string $password): bool
{
    $row = valid_reset_admin($token);
    if (!$row) {
        return false;
    }
    $hash = hash('sha256', $token);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $spend = $pdo->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
        );
        $spend->execute([$hash]);
        if ($spend->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $update = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['admin_id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
