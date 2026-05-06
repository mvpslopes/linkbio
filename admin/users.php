<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_root();
$pdo  = db();

$success = '';
$error   = '';

// ── Ações POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Criar usuário
    if ($action === 'create') {
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] === 'root' ? 'root' : 'client';
        $page_slug = trim($_POST['page_slug'] ?? '') ?: null;
        $name      = trim($_POST['name'] ?? '') ?: null;

        if (!$username || !$password) {
            $error = 'Usuário e senha são obrigatórios.';
        } elseif (strlen($password) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, page_slug, name) VALUES (?,?,?,?,?)');
                $stmt->execute([$username, $hash, $role, $page_slug, $name]);
                $success = "Usuário <strong>$username</strong> criado com sucesso.";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Usuário '$username' já existe." : 'Erro ao criar usuário.';
            }
        }
    }

    // Resetar senha
    if ($action === 'reset_password') {
        $uid      = (int) ($_POST['user_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';
        if ($uid && strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
            $success = 'Senha atualizada com sucesso.';
        } else {
            $error = 'Senha inválida (mínimo 6 caracteres).';
        }
    }

    // Editar usuário
    if ($action === 'edit') {
        $uid       = (int) ($_POST['user_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '') ?: null;
        $page_slug = trim($_POST['page_slug'] ?? '') ?: null;
        $role      = $_POST['role'] === 'root' ? 'root' : 'client';
        if ($uid) {
            $pdo->prepare('UPDATE users SET name=?, page_slug=?, role=? WHERE id=?')
                ->execute([$name, $page_slug, $role, $uid]);
            $success = 'Usuário atualizado com sucesso.';
        } else {
            $error = 'Usuário inválido.';
        }
    }

    // Deletar usuário
    if ($action === 'delete') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$user['id']) {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            $success = 'Usuário removido.';
        } else {
            $error = 'Não é possível remover seu próprio usuário.';
        }
    }
}

// ── Listar usuários ──────────────────────────────────────────
$users = $pdo->query('SELECT id, username, role, page_slug, name, created_at FROM users ORDER BY role DESC, name ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Usuários — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif'] } } } };</script>
  <style>
    :root {
      --sidebar: #1e3a8a;
      --main-bg: #ffffff;
      --main-border: #e2e8f0;
      --main-text: #0f172a;
      --main-subtle: #64748b;
    }
    body { background: #0f172a; }
    .card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; }
    .sidebar-link.active { background: rgba(47,128,237,.15); color:#fff; border-color:rgba(47,128,237,.4); }
    .input { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); padding:.65rem 1rem; font-size:.875rem; color:#fff; outline:none; transition:border .2s; }
    .input:focus { border-color:rgba(47,128,237,.6); background:rgba(255,255,255,.08); }
    .input::placeholder { color:#475569; }
    .btn-primary { background:#2F80ED; color:#fff; font-weight:700; border-radius:.75rem; padding:.6rem 1.25rem; font-size:.875rem; transition:background .2s; }
    .btn-primary:hover { background:#2563EB; }
    .btn-danger { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.25); font-weight:600; border-radius:.75rem; padding:.4rem .85rem; font-size:.8rem; transition:background .2s; }
    .btn-danger:hover { background:rgba(239,68,68,.22); }
    .btn-ghost { background:rgba(255,255,255,.05); color:#94a3b8; border:1px solid rgba(255,255,255,.1); font-weight:600; border-radius:.75rem; padding:.4rem .85rem; font-size:.8rem; transition:background .2s; }
    .btn-ghost:hover { background:rgba(255,255,255,.1); color:#fff; }

    /* Sidebar azul com textos claros */
    aside { background: var(--sidebar) !important; border-right: 1px solid rgba(255,255,255,.2) !important; }
    aside .text-slate-600, aside .text-slate-500, aside .text-slate-400, aside .text-slate-300 { color: #dbeafe !important; }
    aside .bg-white\/8 { background: rgba(255,255,255,.15) !important; }
    aside .text-slate-400 svg, aside .text-slate-500 svg { color: #dbeafe !important; }

    /* Painel principal branco */
    .main-panel { background: var(--main-bg); color: var(--main-text); border-radius: 0; }
    .main-panel .card { background: #fff; border: 1px solid var(--main-border); box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
    .main-panel .text-white { color: var(--main-text) !important; }
    .main-panel .text-slate-500 { color: var(--main-subtle) !important; }
    .main-panel .text-slate-400 { color: #475569 !important; }
    .main-panel .text-slate-300 { color: #334155 !important; }
    .main-panel .text-slate-600 { color: #475569 !important; }
    .main-panel .input { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; }
    .main-panel .input:focus { border-color: #2F80ED; background: #fff; }
    .main-panel .btn-ghost { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .main-panel .btn-ghost:hover { background: #e2e8f0; color: #0f172a; }
  </style>
</head>
<body class="text-slate-100 font-sans antialiased min-h-screen flex">

  <!-- Sidebar -->
  <aside class="hidden md:flex flex-col w-60 shrink-0 border-r border-white/8 bg-[#060a14] px-3 py-5 gap-5">

    <div class="px-2 pt-1">
      <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-7 w-auto max-w-[140px] object-contain"/>
    </div>

    <nav class="flex flex-col gap-0.5 flex-1">
      <!-- Páginas -->
      <p class="px-3 pt-1 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Páginas</p>
      <a href="/admin/dashboard.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </span>
        Ver analytics
      </a>

      <!-- Administração -->
      <p class="px-3 pt-4 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Administração</p>
      <a href="/admin/users.php"
        class="flex items-center gap-2.5 rounded-xl border border-[#2F80ED]/30 bg-[#2F80ED]/12 px-3 py-2.5 text-[13px] font-medium text-white transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-[#2F80ED]/25">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#7eb8f7]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8z"/>
          </svg>
        </span>
        Usuários
      </a>
      <a href="/admin/validate_db.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-500 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </span>
        Validar banco
      </a>
      <a href="/admin/diagnostico_tracker.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-500 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </span>
        Diagnóstico tracker
      </a>
    </nav>

    <!-- Rodapé -->
    <div class="border-t border-white/8 pt-3 space-y-0.5">
      <div class="flex items-center gap-2.5 px-3 py-2">
        <span class="h-7 w-7 rounded-full shrink-0 flex items-center justify-center text-[11px] font-bold bg-[#2F80ED]/20 text-[#7eb8f7]">
          <?= strtoupper(substr($user['username'], 0, 2)) ?>
        </span>
        <div class="min-w-0">
          <p class="text-[12px] font-semibold text-slate-300 truncate"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></p>
          <p class="text-[10px] text-slate-600">Administrador</p>
        </div>
      </div>
      <a href="/admin/logout.php"
        class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] text-slate-500 hover:text-red-400 hover:bg-red-500/8 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
        </svg>
        Sair da conta
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="main-panel flex-1 min-w-0 px-4 sm:px-8 py-8 space-y-6 overflow-auto">

    <div>
      <p class="text-[11px] text-slate-500 uppercase tracking-widest mb-1">Administração</p>
      <h1 class="text-xl font-bold text-white">Gerenciar usuários</h1>
    </div>

    <?php if ($success): ?>
      <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/25 px-4 py-3 text-[13px] text-emerald-400"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="rounded-xl bg-red-500/10 border border-red-500/25 px-4 py-3 text-[13px] text-red-400"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[1fr,340px]">

      <!-- Lista de usuários -->
      <div class="card px-5 py-5 space-y-4">
        <p class="text-[13px] font-semibold text-slate-300">Usuários cadastrados</p>

        <div class="space-y-2">
          <?php foreach ($users as $u): ?>
          <div class="flex items-center justify-between gap-4 rounded-xl border border-white/6 bg-white/3 px-4 py-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
              <div class="h-9 w-9 rounded-full flex items-center justify-center shrink-0 text-[12px] font-bold <?= $u['role']==='root' ? 'bg-[#2F80ED]/20 text-[#2F80ED]' : 'bg-primary/15 text-primary' ?>"
                   style="<?= $u['role']==='client' ? 'background:rgba(217,70,239,.15);color:#D946EF' : '' ?>">
                <?= strtoupper(substr($u['username'], 0, 2)) ?>
              </div>
              <div class="min-w-0">
                <p class="text-[13px] font-semibold text-white truncate"><?= htmlspecialchars($u['name'] ?: $u['username']) ?></p>
                <p class="text-[11px] text-slate-500">
                  @<?= htmlspecialchars($u['username']) ?>
                  <?php if ($u['page_slug']): ?>· <span class="text-slate-400"><?= htmlspecialchars($u['page_slug']) ?></span><?php endif; ?>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
              <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $u['role']==='root' ? 'bg-[#2F80ED]/15 text-[#2F80ED]' : 'bg-white/8 text-slate-400' ?>">
                <?= $u['role'] ?>
              </span>
              <!-- Botão editar -->
              <button onclick="openEdit(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($u['page_slug'] ?? '')) ?>', '<?= $u['role'] ?>')" class="btn-ghost">Editar</button>
              <!-- Botão resetar senha -->
              <button onclick="openReset(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" class="btn-ghost">Senha</button>
              <!-- Botão deletar (não aparece para o próprio usuário logado) -->
              <?php if ((int)$u['id'] !== (int)$user['id']): ?>
              <form method="POST" onsubmit="return confirm('Remover usuário <?= htmlspecialchars($u['username']) ?>?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
                <button type="submit" class="btn-danger">Remover</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Criar novo usuário -->
      <div class="card px-5 py-5 self-start">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Novo usuário</p>
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="create"/>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Nome completo</label>
            <input type="text" name="name" class="input" placeholder="Ex: João Silva"/>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Usuário *</label>
            <input type="text" name="username" required class="input" placeholder="joao.silva"/>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Senha *</label>
            <input type="password" name="password" required class="input" placeholder="mínimo 6 caracteres"/>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Perfil</label>
            <select name="role" class="input" style="cursor:pointer">
              <option value="client">Cliente</option>
              <option value="root">Root (administrador)</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Slug da página</label>
            <input type="text" name="page_slug" class="input" placeholder="ex: joaosilva (deixe em branco para root)"/>
            <p class="text-[11px] text-slate-600 mt-1">Nome da pasta do subdomínio na Hostinger.</p>
          </div>
          <button type="submit" class="btn-primary w-full mt-1">Criar usuário</button>
        </form>
      </div>

    </div>
  </main>

  <!-- Modal editar usuário -->
  <div id="modal-edit" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm px-4">
    <div class="card w-full max-w-sm px-6 py-6 space-y-4">
      <h2 class="text-[16px] font-bold text-white">Editar usuário</h2>
      <p class="text-[13px] text-slate-500">@<strong id="edit-username-label" class="text-slate-300"></strong></p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="edit"/>
        <input type="hidden" name="user_id" id="edit-user-id"/>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Nome completo</label>
          <input type="text" name="name" id="edit-name" class="input" placeholder="Ex: Paty Silva"/>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Slug da página</label>
          <input type="text" name="page_slug" id="edit-slug" class="input" placeholder="ex: paty"/>
          <p class="text-[11px] text-slate-600 mt-1">Deixe em branco para usuários root.</p>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Perfil</label>
          <select name="role" id="edit-role" class="input" style="cursor:pointer">
            <option value="client">Cliente</option>
            <option value="root">Root (administrador)</option>
          </select>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="btn-primary flex-1">Salvar</button>
          <button type="button" onclick="closeEdit()" class="btn-ghost flex-1">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal resetar senha -->
  <div id="modal-reset" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm px-4">
    <div class="card w-full max-w-sm px-6 py-6 space-y-4">
      <h2 class="text-[16px] font-bold text-white">Resetar senha</h2>
      <p class="text-[13px] text-slate-400">Usuário: <strong id="modal-username" class="text-white"></strong></p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="reset_password"/>
        <input type="hidden" name="user_id" id="modal-user-id"/>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Nova senha</label>
          <input type="password" name="new_password" required class="input" placeholder="mínimo 6 caracteres"/>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="btn-primary flex-1">Salvar</button>
          <button type="button" onclick="closeReset()" class="btn-ghost flex-1">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEdit(id, username, name, slug, role) {
      document.getElementById('edit-user-id').value   = id;
      document.getElementById('edit-username-label').textContent = username;
      document.getElementById('edit-name').value      = name;
      document.getElementById('edit-slug').value      = slug;
      document.getElementById('edit-role').value      = role;
      document.getElementById('modal-edit').classList.remove('hidden');
      document.getElementById('modal-edit').classList.add('flex');
    }
    function closeEdit() {
      document.getElementById('modal-edit').classList.add('hidden');
      document.getElementById('modal-edit').classList.remove('flex');
    }
    document.getElementById('modal-edit').addEventListener('click', function(e) {
      if (e.target === this) closeEdit();
    });

    function openReset(id, username) {
      document.getElementById('modal-user-id').value = id;
      document.getElementById('modal-username').textContent = username;
      document.getElementById('modal-reset').classList.remove('hidden');
      document.getElementById('modal-reset').classList.add('flex');
    }
    function closeReset() {
      document.getElementById('modal-reset').classList.add('hidden');
      document.getElementById('modal-reset').classList.remove('flex');
    }
    document.getElementById('modal-reset').addEventListener('click', function(e) {
      if (e.target === this) closeReset();
    });
  </script>
</body>
</html>
