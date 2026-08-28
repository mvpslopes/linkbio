<?php
require_once __DIR__ . '/includes/auth.php';
priscila_logout();
header('Location: /painel/login.php');
exit;
