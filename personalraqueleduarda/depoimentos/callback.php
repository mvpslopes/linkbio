<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    if (!google_configured()) {
        throw new RuntimeException('Credenciais Google não configuradas.');
    }

    $state = $_GET['state'] ?? '';
    $code  = $_GET['code'] ?? '';
    $err   = $_GET['error'] ?? '';

    if ($err !== '') {
        throw new RuntimeException('Login cancelado ou negado no Google.');
    }
    if ($code === '' || $state === '' || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
        throw new RuntimeException('Sessão de login inválida. Tente novamente.');
    }
    unset($_SESSION['oauth_state']);

    $token = google_exchange_code($code);
    $user  = google_fetch_userinfo($token['access_token']);

    $_SESSION['google_user'] = $user;
    flash_set('ok', 'Login realizado. Agora você pode publicar seu depoimento.');
    header('Location: ' . base_url() . '/');
    exit;
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    header('Location: ' . base_url() . '/');
    exit;
}
