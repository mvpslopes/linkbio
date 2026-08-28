<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nav.php';

if (genealogy_auth_user()) {
    header('Location: /painel/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user && $pass && genealogy_login($user, $pass)) {
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
  <meta name="theme-color" content="#1B3A2D"/>
  <title>Login — Árvore Genealógica</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <?php genealogy_painel_head(); ?>
</head>
<body class="login-page">
  <div class="login-card">
    <h1>Árvore Genealógica</h1>
    <p class="sub">Painel de cadastro — acesso restrito</p>
    <?php if ($error): ?><div class="err"><?= genealogy_h($error) ?></div><?php endif; ?>
    <form method="POST">
      <label class="field-label" for="username">Usuário</label>
      <input type="text" id="username" name="username" required autocomplete="username"/>
      <label class="field-label" for="password">Senha</label>
      <input type="password" id="password" name="password" required autocomplete="current-password"/>
      <div class="form-actions" style="border:0;padding-top:20px;margin-top:8px">
        <button type="submit" class="btn btn-primary" style="width:100%">Entrar</button>
      </div>
    </form>
    <p class="field-hint" style="margin-top:20px;text-align:center">
      Use um usuário com slug <strong>arvoregeneaologica</strong> ou perfil root.
    </p>
  </div>
</body>
</html>
