<?php
require_once __DIR__ . '/db.php';

session_name('priscilaramos_painel');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

const PRISCILA_SLUG = 'priscilaramos';

function priscila_can_access(array $user): bool
{
    return $user['role'] === 'root'
        || ($user['role'] === 'client' && ($user['page_slug'] ?? '') === PRISCILA_SLUG);
}

function priscila_auth_user(): ?array
{
    if (empty($_SESSION['priscila_user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, role, page_slug, name FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['priscila_user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || !priscila_can_access($user)) {
        return null;
    }
    return $user;
}

function require_priscila_auth(): array
{
    $user = priscila_auth_user();
    if (!$user) {
        header('Location: /painel/login.php');
        exit;
    }
    return $user;
}

function priscila_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, role, page_slug, name FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    if (!priscila_can_access($row)) {
        return false;
    }
    $_SESSION['priscila_user_id'] = (int) $row['id'];
    session_regenerate_id(true);
    if (empty($_SESSION['loyalty_csrf'])) {
        $_SESSION['loyalty_csrf'] = bin2hex(random_bytes(16));
    }
    return true;
}

function priscila_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

if (!function_exists('loyalty_h')) {
    function loyalty_h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function loyalty_csrf_boot(): void
{
    if (empty($_SESSION['loyalty_csrf'])) {
        $_SESSION['loyalty_csrf'] = bin2hex(random_bytes(16));
    }
}

function loyalty_csrf_field(): string
{
    loyalty_csrf_boot();
    return '<input type="hidden" name="_csrf" value="' . loyalty_h($_SESSION['loyalty_csrf']) . '"/>';
}

function loyalty_csrf_ok(): bool
{
    $t = $_POST['_csrf'] ?? '';
    return is_string($t) && hash_equals((string) ($_SESSION['loyalty_csrf'] ?? ''), $t);
}
