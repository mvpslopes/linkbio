<?php
require_once __DIR__ . '/includes/auth.php';
thayna_logout();
header('Location: /painel/login.php');
exit;
