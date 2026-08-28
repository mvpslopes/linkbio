<?php
require_once __DIR__ . '/includes/auth.php';
genealogy_logout();
header('Location: /painel/login.php');
exit;
