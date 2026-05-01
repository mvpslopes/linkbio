<?php
require_once __DIR__ . '/includes/auth.php';

if (auth_user()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (login($u, $p)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Acessar — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif'] } } }
    };
  </script>
  <style>
    .splash-bg {
      background: url('/splash/Splash%20820%20x%201180.png') center/cover no-repeat;
      min-height: 100vh;
      position: relative;
    }
    
    .splash-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(8, 12, 24, 0.4);
      z-index: 1;
    }
    
    .splash-content {
      position: relative;
      z-index: 2;
    }
    
    /* Desktop splash background */
    @media (min-width: 1024px) {
      .splash-bg {
        background: url('/splash/Splash%202560x1440.png') center/cover no-repeat;
      }
    }
  </style>
</head>
<body class="min-h-screen splash-bg flex items-center justify-center px-4 font-sans">

  <div class="splash-content w-full max-w-sm">

    <!-- Logo -->
    <div class="flex flex-col items-center mb-8">
      <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-10 w-auto max-w-[160px] object-contain mb-3"/>
      <p class="text-slate-400 text-[14px]">Painel de gestão</p>
    </div>

    <!-- Card -->
    <div class="rounded-2xl border border-white/10 bg-white/4 backdrop-blur-sm px-7 py-8 shadow-[0_30px_80px_rgba(0,0,0,0.5)]">

      <h1 class="text-[20px] font-bold text-white mb-6">Entrar</h1>

      <?php if ($error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/30 px-4 py-3 text-[13px] text-red-400">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">

        <div>
          <label class="block text-[12px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">Usuário</label>
          <input
            type="text" name="username" autocomplete="username" required
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-[14px] text-white placeholder-slate-600 focus:outline-none focus:border-blue-500/60 focus:bg-white/8 transition"
            placeholder="seu.usuario"
          />
        </div>

        <div>
          <label class="block text-[12px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">Senha</label>
          <input
            type="password" name="password" autocomplete="current-password" required
            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-[14px] text-white placeholder-slate-600 focus:outline-none focus:border-blue-500/60 focus:bg-white/8 transition"
            placeholder="••••••••"
          />
        </div>

        <button type="submit"
          class="w-full mt-2 rounded-xl bg-[#2F80ED] py-3 text-[15px] font-bold text-white hover:bg-[#2563EB] transition shadow-[0_0_30px_rgba(47,128,237,.35)]">
          Entrar
        </button>

      </form>
    </div>

    <p class="text-center text-[12px] text-slate-600 mt-6">
      <a href="/" class="hover:text-slate-400 transition">← Voltar ao site</a>
    </p>
  </div>

</body>
</html>
