<?php
require_once __DIR__ . '/db.php';

session_name('genealogy_painel');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
}

const GENEALOGY_SLUG = 'arvoregeneaologica';

function genealogy_can_access(array $user): bool
{
    return $user['role'] === 'root'
        || ($user['role'] === 'client' && ($user['page_slug'] ?? '') === GENEALOGY_SLUG);
}

function genealogy_auth_user(): ?array
{
    if (empty($_SESSION['genealogy_user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, role, page_slug, name FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['genealogy_user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || !genealogy_can_access($user)) {
        return null;
    }
    return $user;
}

function require_genealogy_auth(): array
{
    $user = genealogy_auth_user();
    if (!$user) {
        header('Location: /painel/login.php');
        exit;
    }
    return $user;
}

function genealogy_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, role, page_slug, name FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    if (!genealogy_can_access($row)) {
        return false;
    }
    $_SESSION['genealogy_user_id'] = (int) $row['id'];
    session_regenerate_id(true);
    return true;
}

function genealogy_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
