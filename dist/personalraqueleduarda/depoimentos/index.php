<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$flash = flash_get();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
$configured = google_configured();
$siteName = (string) cfg('site_name', 'Raquel Eduarda');
$mainUrl = rtrim((string) cfg('main_site_url', '/'), '/') . '/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Deixar depoimento | <?= e($siteName) ?></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../logo/icone-logo-branco.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --accent: #22c55e;
      --accent-dark: #16a34a;
      --bg: #070a08;
      --surface: #111814;
      --ink: #f2f7f4;
      --muted: #9aaba2;
      --border: rgba(255,255,255,.10);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: Inter, system-ui, sans-serif;
      color: var(--ink);
      background:
        radial-gradient(ellipse 70% 50% at 80% 0%, rgba(34,197,94,.12), transparent 55%),
        linear-gradient(165deg, #050705 0%, #0a100c 100%);
    }
    .wrap { max-width: 520px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
    .brand { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; }
    .brand img { height: 40px; width: auto; }
    .brand a { color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600; }
    .brand a:hover { color: var(--accent); }
    h1 { font-family: Sora, sans-serif; font-size: 1.75rem; line-height: 1.15; margin: 0 0 .75rem; }
    .lead { color: var(--muted); font-size: 15px; line-height: 1.55; margin: 0 0 1.5rem; }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 1.25rem;
      padding: 1.5rem;
      box-shadow: 0 16px 40px rgba(0,0,0,.25);
    }
    .flash {
      border-radius: 12px; padding: .9rem 1rem; margin-bottom: 1rem; font-size: 14px;
    }
    .flash.ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.35); color: #86efac; }
    .flash.error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: .6rem;
      width: 100%; border: 0; border-radius: 999px; padding: .95rem 1.25rem;
      font-size: 15px; font-weight: 700; cursor: pointer; text-decoration: none;
      transition: transform .2s, background .2s;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-google { background: #fff; color: #111; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { background: var(--accent-dark); }
    .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); margin-top: .75rem; }
    .user {
      display: flex; align-items: center; gap: .85rem; margin-bottom: 1.25rem;
      padding-bottom: 1.1rem; border-bottom: 1px solid var(--border);
    }
    .user img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(34,197,94,.4); }
    .user strong { display: block; font-size: 15px; }
    .user span { font-size: 12px; color: var(--muted); }
    label { display: block; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 0 0 .5rem; }
    textarea {
      width: 100%; min-height: 130px; resize: vertical; border-radius: 14px;
      border: 1px solid var(--border); background: rgba(0,0,0,.25); color: var(--ink);
      padding: .9rem 1rem; font: inherit; margin-bottom: 1rem;
    }
    textarea:focus { outline: 2px solid rgba(34,197,94,.45); border-color: transparent; }
    .stars { display: flex; gap: .35rem; margin-bottom: 1.1rem; }
    .stars button {
      background: transparent; border: 0; cursor: pointer; padding: .15rem;
      color: rgba(255,255,255,.25); font-size: 1.6rem; line-height: 1;
    }
    .stars button.active, .stars button:hover { color: var(--accent); }
    .hint { font-size: 12px; color: var(--muted); margin: .85rem 0 0; line-height: 1.45; }
    .warn { font-size: 13px; color: #fcd34d; line-height: 1.5; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <img src="../logo/logo-branco.png" alt="<?= e($siteName) ?>" />
      <a href="<?= e($mainUrl) ?>">← Voltar ao site</a>
    </div>

    <h1>Deixe seu depoimento</h1>
    <p class="lead">Entre com sua conta Google para publicar um comentário. Seu nome e foto do Google aparecem no site da <?= e($siteName) ?>.</p>

    <?php if ($flash): ?>
      <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="card">
      <?php if (!$configured): ?>
        <p class="warn">
          O login Google ainda não foi configurado. Preencha <code>depoimentos/config.php</code> com o Client ID e Client Secret do Google Cloud Console.
        </p>
      <?php elseif (!$user): ?>
        <a class="btn btn-google" href="login.php">
          <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.2C29.3 35.3 26.8 36 24 36c-5.3 0-9.7-3.1-11.3-7.5l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.1l.1.1 6.3 5.2C36.8 39 44 34 44 24c0-1.3-.1-2.3-.4-3.5z"/></svg>
          Continuar com Google
        </a>
        <p class="hint">Ao continuar, você autoriza a publicação do seu nome e foto de perfil no site. Sem renovação automática — apenas o depoimento que você escrever.</p>
      <?php else: ?>
        <div class="user">
          <?php if (!empty($user['picture'])): ?>
            <img src="<?= e($user['picture']) ?>" alt="" referrerpolicy="no-referrer" />
          <?php else: ?>
            <div style="width:48px;height:48px;border-radius:50%;background:rgba(34,197,94,.2);display:flex;align-items:center;justify-content:center;font-weight:800;"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
          <?php endif; ?>
          <div>
            <strong><?= e($user['name']) ?></strong>
            <span><?= e($user['email'] ?? 'Conta Google') ?></span>
          </div>
        </div>

        <form method="post" action="submit.php">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
          <input type="hidden" name="rating" id="rating" value="5" />

          <label>Avaliação</label>
          <div class="stars" id="stars" role="group" aria-label="Avaliação em estrelas">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" data-v="<?= $i ?>" class="active" aria-label="<?= $i ?> estrela<?= $i > 1 ? 's' : '' ?>">★</button>
            <?php endfor; ?>
          </div>

          <label for="comment">Seu depoimento</label>
          <textarea id="comment" name="comment" maxlength="800" minlength="10" required placeholder="Conte como foi treinar com a Raquel..."></textarea>

          <button type="submit" class="btn btn-primary">Publicar no site</button>
        </form>
        <a class="btn btn-ghost" href="logout.php">Sair da conta Google</a>
        <p class="hint">Seu comentário aparece automaticamente na seção Depoimentos do site, com sua foto do Google.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function () {
      var stars = document.getElementById('stars');
      var input = document.getElementById('rating');
      if (!stars || !input) return;
      var buttons = Array.prototype.slice.call(stars.querySelectorAll('button'));
      function paint(v) {
        buttons.forEach(function (b) {
          b.classList.toggle('active', Number(b.getAttribute('data-v')) <= v);
        });
      }
      buttons.forEach(function (b) {
        b.addEventListener('click', function () {
          var v = Number(b.getAttribute('data-v'));
          input.value = String(v);
          paint(v);
        });
      });
    })();
  </script>
</body>
</html>
