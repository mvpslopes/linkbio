<?php
require_once __DIR__ . '/db.php';

session_name('linkbio_session');
if (session_status() === PHP_SESSION_NONE) session_start();

function auth_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, username, role, page_slug FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(): array {
    $user = auth_user();
    if (!$user) {
        header('Location: /admin/login.php');
        exit;
    }
    return $user;
}

function require_root(): array {
    $user = require_auth();
    if ($user['role'] !== 'root') {
        header('Location: /admin/dashboard.php');
        exit;
    }
    return $user;
}

function login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return false;
    $_SESSION['user_id'] = $row['id'];
    session_regenerate_id(true);
    return true;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}
