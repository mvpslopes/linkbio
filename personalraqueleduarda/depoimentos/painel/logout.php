<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
admin_logout();
header('Location: ' . painel_url() . '/login.php');
exit;
