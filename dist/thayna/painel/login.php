<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nav.php';

if (thayna_auth_user()) {
    header('Location: /painel/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user && $pass && thayna_login($user, $pass)) {
        header('Location: /painel/');
        exit;
    }
    $error = 'Usuário ou senha inválidos.';
}

$logoPath = is_file(dirname(__DIR__, 2) . '/logo/logo.png') ? '/logo/logo.png' : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <title>Login — Painel Thayna</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <?php thayna_painel_head(); ?>
</head>
<body class="login-page">
  <div class="login-card">
    <?php if ($logoPath): ?>
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="Thayna Freire" class="login-logo"/>
    <?php endif; ?>
    <h1>Painel de Relatórios</h1>
    <p class="sub">Thayna Freire — acesso restrito</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label class="field-label" for="username">Usuário</label>
      <input type="text" id="username" name="username" required autocomplete="username" inputmode="text"/>
      <label class="field-label" for="password">Senha</label>
      <input type="password" id="password" name="password" required autocomplete="current-password"/>
      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
  </div>
</body>
</html>
