<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!google_configured()) {
    flash_set('error', 'Login Google ainda não configurado. Preencha depoimentos/config.php.');
    header('Location: ' . base_url() . '/');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

header('Location: ' . google_auth_url($state));
exit;
