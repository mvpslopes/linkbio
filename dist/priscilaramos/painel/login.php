<?php
require_once __DIR__ . '/includes/auth.php';

if (priscila_auth_user()) {
    header('Location: /painel/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($user && $pass && priscila_login($user, $pass)) {
        header('Location: /painel/');
        exit;
    }
    $error = 'Usuário ou senha inválidos, ou sem permissão para este painel.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#522530"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Login — Cartão fidelidade | Priscila Ramos</title>
  <link rel="icon" href="/favicon.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/painel/includes/painel.css?v=1"/>
</head>
<body class="login-page">
  <div class="login-card">
    <img src="/logo/logo-nav.png" alt="Priscila Ramos"/>
    <h1>Cartão fidelidade</h1>
    <p class="sub">Painel interno — acesso restrito</p>
    <?php if ($error): ?><div class="err"><?= loyalty_h($error) ?></div><?php endif; ?>
    <form method="POST">
      <label for="username">Usuário</label>
      <input type="text" id="username" name="username" required autocomplete="username" autofocus/>
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" required autocomplete="current-password"/>
      <button type="submit" class="btn btn-primary btn-full">Entrar</button>
    </form>
    <a class="back" href="/">← Voltar ao site</a>
  </div>
</body>
</html>
