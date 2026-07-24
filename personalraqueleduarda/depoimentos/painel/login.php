<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_admin_logged_in()) {
    header('Location: ' . painel_url() . '/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($user === '' || $pass === '') {
        $error = 'Informe usuário e senha.';
    } elseif (admin_try_login($user, $pass)) {
        header('Location: ' . painel_url() . '/');
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}

$siteName = (string) cfg('site_name', 'Raquel Eduarda');
$mainUrl = rtrim((string) cfg('main_site_url', '/'), '/') . '/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login · Painel de depoimentos | <?= e($siteName) ?></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../../logo/icone-logo-branco.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/painel.css" />
</head>
<body class="login-body">
  <section class="login-brand">
    <img class="logo" src="../../logo/logo-branco.png" alt="<?= e($siteName) ?>" />
    <div>
      <h1>Central de moderação de depoimentos</h1>
      <p>Gerencie o que aparece no site: aprove comentários reais, oculte temporariamente ou exclua conteúdo indesejado.</p>
      <ul class="login-features">
        <li><span>1</span> Fila de pendentes para revisão</li>
        <li><span>2</span> Publicação controlada no carrossel</li>
        <li><span>3</span> Histórico com nome, e-mail e nota</li>
      </ul>
    </div>
    <p class="login-foot">Acesso restrito · <?= e($siteName) ?></p>
  </section>

  <section class="login-panel">
    <div class="login-card">
      <p class="eyebrow">Sistema interno</p>
      <h2>Entrar no painel</h2>
      <p class="sub">Use o usuário e a senha configurados no servidor.</p>
      <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <label for="username">Usuário</label>
        <input type="text" id="username" name="username" required autocomplete="username" autofocus />
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" />
        <button type="submit">Acessar sistema</button>
      </form>
      <a class="back" href="<?= e($mainUrl) ?>">← Voltar ao site público</a>
    </div>
  </section>
</body>
</html>
