<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'Configure depoimentos/config.php (copie de config.example.php).';
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configPath;

require_once dirname(__DIR__, 3) . '/admin/includes/db.php';
require_once __DIR__ . '/google.php';

function cfg(string $key, $default = null) {
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

function base_url(): string {
    $configured = rtrim((string) cfg('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/depoimentos';
}

function redirect_uri(): string {
    return base_url() . '/callback.php';
}

function is_logged_in(): bool {
    return !empty($_SESSION['google_user']['sub']);
}

function current_user(): ?array {
    return is_logged_in() ? $_SESSION['google_user'] : null;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url() . '/');
        exit;
    }
}

function e(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function google_configured(): bool {
    $id = (string) cfg('google_client_id', '');
    $secret = (string) cfg('google_client_secret', '');
    return $id !== ''
        && $secret !== ''
        && !str_contains($id, 'SEU_CLIENT_ID')
        && !str_contains($secret, 'SEU_CLIENT_SECRET');
}
