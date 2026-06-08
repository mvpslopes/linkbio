<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_root();
$pdo  = db();

$tablesOk = in_array('interessados', array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true)
         && in_array('interessados_opcoes', array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true);

$success = '';
$error   = '';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadOpcoes(PDO $pdo, string $tipo): array
{
    $st = $pdo->prepare('SELECT id, valor FROM interessados_opcoes WHERE tipo = ? ORDER BY ordem ASC, valor ASC');
    $st->execute([$tipo]);
    return $st->fetchAll();
}

function parseComissao(?string $raw): ?float
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $v = str_replace(['.', ' '], ['', ''], trim($raw));
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? round((float) $v, 2) : null;
}

function fmtComissao(?float $v): string
{
    return $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
}

// ── Ações POST ───────────────────────────────────────────────
if ($tablesOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nome     = trim($_POST['nome_cliente'] ?? '');
        $segmento = trim($_POST['segmento'] ?? '') ?: null;
        $contato  = trim($_POST['contato'] ?? '') ?: null;
        $status   = trim($_POST['status'] ?? '') ?: 'Novo';
        $atendente = (int) ($_POST['atendente_id'] ?? 0) ?: null;
        $comissao = parseComissao($_POST['comissao'] ?? null);
        $statusComissao = trim($_POST['status_comissao'] ?? '') ?: null;
        $obs      = trim($_POST['observacoes'] ?? '') ?: null;

        if ($nome === '') {
            $error = 'Nome do cliente é obrigatório.';
        } else {
            $pdo->prepare(
                'INSERT INTO interessados (nome_cliente, segmento, contato, status, atendente_id, comissao, status_comissao, observacoes)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$nome, $segmento, $contato, $status, $atendente, $comissao, $statusComissao, $obs]);
            $success = 'Interessado cadastrado com sucesso.';
        }
    }

    if ($action === 'edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $nome     = trim($_POST['nome_cliente'] ?? '');
        $segmento = trim($_POST['segmento'] ?? '') ?: null;
        $contato  = trim($_POST['contato'] ?? '') ?: null;
        $status   = trim($_POST['status'] ?? '') ?: 'Novo';
        $atendente = (int) ($_POST['atendente_id'] ?? 0) ?: null;
        $comissao = parseComissao($_POST['comissao'] ?? null);
        $statusComissao = trim($_POST['status_comissao'] ?? '') ?: null;
        $obs      = trim($_POST['observacoes'] ?? '') ?: null;

        if (!$id || $nome === '') {
            $error = 'Dados inválidos para edição.';
        } else {
            $pdo->prepare(
                'UPDATE interessados SET nome_cliente=?, segmento=?, contato=?, status=?, atendente_id=?, comissao=?, status_comissao=?, observacoes=? WHERE id=?'
            )->execute([$nome, $segmento, $contato, $status, $atendente, $comissao, $statusComissao, $obs, $id]);
            $success = 'Registro atualizado.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM interessados WHERE id=?')->execute([$id]);
            $success = 'Registro removido.';
        }
    }

    if ($action === 'add_opcao') {
        $tipo  = $_POST['tipo'] ?? '';
        $valor = trim($_POST['valor'] ?? '');
        $tiposValidos = ['segmento', 'status', 'status_comissao'];
        if (!in_array($tipo, $tiposValidos, true) || $valor === '') {
            $error = 'Informe um valor válido para a opção.';
        } else {
            try {
                $stMax = $pdo->prepare('SELECT COALESCE(MAX(ordem),0) FROM interessados_opcoes WHERE tipo=?');
                $stMax->execute([$tipo]);
                $ordem = (int) $stMax->fetchColumn() + 1;
                $pdo->prepare('INSERT INTO interessados_opcoes (tipo, valor, ordem) VALUES (?,?,?)')
                    ->execute([$tipo, $valor, $ordem]);
                $success = 'Opção adicionada.';
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Esta opção já existe.' : 'Erro ao adicionar opção.';
            }
        }
    }

    if ($action === 'delete_opcao') {
        $opcaoId = (int) ($_POST['opcao_id'] ?? 0);
        if ($opcaoId) {
            $pdo->prepare('DELETE FROM interessados_opcoes WHERE id=?')->execute([$opcaoId]);
            $success = 'Opção removida.';
        }
    }
}

$segmentos       = $tablesOk ? loadOpcoes($pdo, 'segmento') : [];
$statusList      = $tablesOk ? loadOpcoes($pdo, 'status') : [];
$statusComissaoList = $tablesOk ? loadOpcoes($pdo, 'status_comissao') : [];
$atendentes      = $pdo->query("SELECT id, username, name FROM users WHERE role='root' ORDER BY name ASC, username ASC")->fetchAll();

$filtroStatus = trim($_GET['status'] ?? '');
$filtroSegmento = trim($_GET['segmento'] ?? '');

$rows = [];
if ($tablesOk) {
    $sql = 'SELECT i.*, u.name AS atendente_nome, u.username AS atendente_username
            FROM interessados i
            LEFT JOIN users u ON u.id = i.atendente_id
            WHERE 1=1';
    $params = [];
    if ($filtroStatus !== '') {
        $sql .= ' AND i.status = ?';
        $params[] = $filtroStatus;
    }
    if ($filtroSegmento !== '') {
        $sql .= ' AND i.segmento = ?';
        $params[] = $filtroSegmento;
    }
    $sql .= ' ORDER BY i.updated_at DESC, i.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}

$dashComissoes = [];
$totalPendente = 0.0;
$qtdPendenteTotal = 0;

if ($tablesOk) {
    $dashRows = $pdo->query(
        "SELECT u.id, u.name, u.username,
                COUNT(i.id) AS qtd,
                COALESCE(SUM(i.comissao), 0) AS total
         FROM users u
         LEFT JOIN interessados i ON i.atendente_id = u.id
             AND i.status_comissao = 'Pendente'
             AND i.comissao IS NOT NULL
         WHERE u.role = 'root'
         GROUP BY u.id, u.name, u.username
         ORDER BY total DESC, u.name ASC, u.username ASC"
    )->fetchAll();

    foreach ($dashRows as $d) {
        $total = (float) $d['total'];
        $qtd   = (int) $d['qtd'];
        $totalPendente += $total;
        $qtdPendenteTotal += $qtd;
        $label = $d['name'] ?: $d['username'];
        $dashComissoes[] = [
            'id'       => (int) $d['id'],
            'label'    => $label,
            'initials' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $label), 0, 2) ?: '??'),
            'qtd'      => $qtd,
            'total'    => $total,
        ];
    }

    $semAtendente = $pdo->query(
        "SELECT COUNT(*) AS qtd, COALESCE(SUM(comissao), 0) AS total
         FROM interessados
         WHERE atendente_id IS NULL
           AND status_comissao = 'Pendente'
           AND comissao IS NOT NULL"
    )->fetch();

    $semTotal = (float) ($semAtendente['total'] ?? 0);
    $semQtd   = (int) ($semAtendente['qtd'] ?? 0);
    if ($semQtd > 0) {
        $totalPendente += $semTotal;
        $qtdPendenteTotal += $semQtd;
        $dashComissoes[] = [
            'id'       => null,
            'label'    => 'Sem atendente',
            'initials' => '?',
            'qtd'      => $semQtd,
            'total'    => $semTotal,
        ];
    }
}

$statusColors = [
    'Novo'        => 'bg-sky-500/15 text-sky-600',
    'Em contato'  => 'bg-amber-500/15 text-amber-600',
    'Negociando'  => 'bg-violet-500/15 text-violet-600',
    'Fechado'     => 'bg-emerald-500/15 text-emerald-600',
    'Perdido'     => 'bg-slate-500/15 text-slate-500',
];
$comissaoColors = [
    'Pendente'  => 'bg-amber-500/15 text-amber-600',
    'Paga'      => 'bg-emerald-500/15 text-emerald-600',
    'Cancelada' => 'bg-slate-500/15 text-slate-500',
];

$openCreateModal = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && $error !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Interessados — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif'] } } } };</script>
  <style>
    :root { --sidebar: #1e3a8a; --main-bg: #ffffff; --main-border: #e2e8f0; --main-text: #0f172a; --main-subtle: #64748b; }
    body { background: #0f172a; }
    .card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; }
    .input { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); padding:.65rem 1rem; font-size:.875rem; color:#fff; outline:none; transition:border .2s; }
    .input:focus { border-color:rgba(47,128,237,.6); background:rgba(255,255,255,.08); }
    .input::placeholder { color:#475569; }
    .btn-primary { background:#2F80ED; color:#fff; font-weight:700; border-radius:.75rem; padding:.6rem 1.25rem; font-size:.875rem; transition:background .2s; }
    .btn-primary:hover { background:#2563EB; }
    .btn-danger { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.25); font-weight:600; border-radius:.75rem; padding:.4rem .85rem; font-size:.8rem; }
    .btn-danger:hover { background:rgba(239,68,68,.22); }
    .btn-ghost { background:rgba(255,255,255,.05); color:#94a3b8; border:1px solid rgba(255,255,255,.1); font-weight:600; border-radius:.75rem; padding:.4rem .85rem; font-size:.8rem; }
    .btn-ghost:hover { background:rgba(255,255,255,.1); color:#fff; }
    aside { background: var(--sidebar) !important; border-right: 1px solid rgba(255,255,255,.2) !important; }
    aside .text-slate-600, aside .text-slate-500, aside .text-slate-400, aside .text-slate-300 { color: #dbeafe !important; }
    aside .bg-white\/8 { background: rgba(255,255,255,.15) !important; }
    .main-panel { background: var(--main-bg); color: var(--main-text); }
    .main-panel .card { background: #fff; border: 1px solid var(--main-border); box-shadow: 0 6px 18px rgba(15,23,42,.04); }
    .main-panel .text-white { color: var(--main-text) !important; }
    .main-panel .text-slate-500 { color: var(--main-subtle) !important; }
    .main-panel .text-slate-400 { color: #475569 !important; }
    .main-panel .text-slate-300 { color: #334155 !important; }
    .main-panel .input { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; }
    .main-panel .input:focus { border-color: #2F80ED; }
    .main-panel .btn-ghost { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .main-panel .btn-ghost:hover { background: #e2e8f0; color: #0f172a; }
    .tbl th { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; padding: .75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
    .tbl td { padding: .85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
    .tbl tr:hover td { background: #f8fafc; }
    .badge { display: inline-flex; align-items: center; border-radius: 9999px; padding: .2rem .65rem; font-size: 11px; font-weight: 600; }
    .opcao-tag { display: inline-flex; align-items: center; gap: .35rem; border-radius: .5rem; border: 1px solid #e2e8f0; background: #f8fafc; padding: .25rem .5rem; font-size: 12px; color: #334155; }
    .modal-overlay { background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(4px); }
    .modal-panel {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.28);
      color: #0f172a;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .modal-panel::-webkit-scrollbar { width: 6px; }
    .modal-panel::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .modal-panel .input { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; }
    .modal-panel .input:focus { border-color: #2F80ED; box-shadow: 0 0 0 3px rgba(47, 128, 237, 0.12); }
    .modal-panel .input::placeholder { color: #94a3b8; }
    .modal-panel .btn-ghost { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .modal-panel .btn-ghost:hover { background: #e2e8f0; color: #0f172a; }
    .modal-title { color: #0f172a; font-weight: 700; }
    .modal-subtitle { color: #64748b; }
    .modal-close { color: #94a3b8; line-height: 1; }
    .modal-close:hover { color: #475569; }
    .dash-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem 1.15rem; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
    .dash-card-total { border-color: #fcd34d; background: linear-gradient(135deg, #fffbeb 0%, #fff 100%); }
    .dash-avatar { height: 2.25rem; width: 2.25rem; border-radius: .65rem; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; background: #eff6ff; color: #2563eb; shrink: 0; }
    .dash-value { font-size: 1.35rem; font-weight: 800; color: #b45309; line-height: 1.2; }
    .dash-value-zero { color: #94a3b8; }
  </style>
</head>
<body class="text-slate-100 font-sans antialiased min-h-screen flex">

  <aside class="hidden md:flex flex-col w-60 shrink-0 px-3 py-5 gap-5">
    <div class="px-2 pt-1">
      <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-7 w-auto max-w-[140px] object-contain"/>
    </div>
    <nav class="flex flex-col gap-0.5 flex-1">
      <p class="px-3 pt-1 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Páginas</p>
      <a href="/admin/dashboard.php" class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </span>
        Ver analytics
      </a>
      <p class="px-3 pt-4 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Administração</p>
      <a href="/admin/interessados.php" class="flex items-center gap-2.5 rounded-xl border border-[#2F80ED]/30 bg-[#2F80ED]/12 px-3 py-2.5 text-[13px] font-medium text-white transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-[#2F80ED]/25">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#7eb8f7]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8zm0 0v-1a4 4 0 014-4h2a4 4 0 014 4v1"/></svg>
        </span>
        Interessados
      </a>
      <a href="/admin/users.php" class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-500 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8z"/></svg>
        </span>
        Usuários
      </a>
      <a href="/admin/briefing_forms.php" class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-500 hover:text-white hover:bg-white/5 transition">Briefings</a>
    </nav>
    <div class="border-t border-white/8 pt-3">
      <a href="/admin/logout.php" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] text-slate-500 hover:text-red-400 transition">Sair da conta</a>
    </div>
  </aside>

  <main class="main-panel flex-1 min-w-0 px-4 sm:px-8 py-8 space-y-6 overflow-auto">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-[11px] text-slate-500 uppercase tracking-widest mb-1">Administração</p>
        <h1 class="text-xl font-bold text-white">Interessados</h1>
        <p class="text-[13px] text-slate-500 mt-1">Controle de leads: cliente, segmento, contato, status, atendente e comissão.</p>
      </div>
      <?php if ($tablesOk): ?>
      <button type="button" onclick="openCreate()" class="btn-primary inline-flex items-center gap-2 shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo interessado
      </button>
      <?php endif; ?>
    </div>

    <?php if (!$tablesOk): ?>
      <div class="rounded-xl bg-amber-500/10 border border-amber-500/25 px-4 py-4 text-[13px] text-amber-700">
        <p class="font-semibold mb-1">Tabelas não encontradas</p>
        <p>Execute o script <code class="bg-slate-100 px-1 rounded">admin/sql/08_interessados.sql</code> no phpMyAdmin antes de usar esta página.</p>
      </div>
    <?php else: ?>

    <?php if ($success): ?>
      <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/25 px-4 py-3 text-[13px] text-emerald-600"><?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="rounded-xl bg-red-500/10 border border-red-500/25 px-4 py-3 text-[13px] text-red-600"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Dash comissões pendentes -->
    <div class="space-y-3">
      <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
          <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">Comissões pendentes</p>
          <p class="text-[12px] text-slate-500 mt-0.5">Valores com status de comissão <strong class="text-amber-600">Pendente</strong>, por atendente root.</p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
        <div class="dash-card dash-card-total">
          <p class="text-[10px] font-semibold text-amber-700 uppercase tracking-widest mb-2">Total geral</p>
          <p class="dash-value"><?= h(fmtComissao($totalPendente)) ?></p>
          <p class="text-[11px] text-slate-500 mt-1"><?= $qtdPendenteTotal ?> comissão(ões) pendente(s)</p>
        </div>
        <?php foreach ($dashComissoes as $dash): ?>
        <div class="dash-card">
          <div class="flex items-center gap-3 mb-3">
            <span class="dash-avatar"><?= h($dash['initials']) ?></span>
            <div class="min-w-0">
              <p class="text-[13px] font-semibold text-slate-800 truncate"><?= h($dash['label']) ?></p>
              <p class="text-[10px] text-slate-500 uppercase tracking-widest">Atendente root</p>
            </div>
          </div>
          <p class="dash-value <?= $dash['total'] <= 0 ? 'dash-value-zero' : '' ?>"><?= h(fmtComissao($dash['total'])) ?></p>
          <p class="text-[11px] text-slate-500 mt-1">
            <?= $dash['qtd'] ?> pendente(s)
            <?php if ($dash['total'] <= 0): ?> · sem valores<?php endif; ?>
          </p>
        </div>
        <?php endforeach; ?>
        <?php if (!$dashComissoes): ?>
        <div class="dash-card sm:col-span-2">
          <p class="text-[13px] text-slate-500">Nenhum usuário root cadastrado para exibir comissões.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="space-y-4">
        <!-- Filtros -->
        <div class="card px-4 py-4 flex flex-wrap gap-3 items-end">
          <form method="GET" class="flex flex-wrap gap-3 items-end flex-1">
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status</label>
              <select name="status" class="input" style="min-width:160px;cursor:pointer">
                <option value="">Todos</option>
                <?php foreach ($statusList as $op): ?>
                <option value="<?= h($op['valor']) ?>" <?= $filtroStatus === $op['valor'] ? 'selected' : '' ?>><?= h($op['valor']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Segmento</label>
              <select name="segmento" class="input" style="min-width:160px;cursor:pointer">
                <option value="">Todos</option>
                <?php foreach ($segmentos as $op): ?>
                <option value="<?= h($op['valor']) ?>" <?= $filtroSegmento === $op['valor'] ? 'selected' : '' ?>><?= h($op['valor']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn-primary">Filtrar</button>
            <?php if ($filtroStatus || $filtroSegmento): ?>
            <a href="/admin/interessados.php" class="btn-ghost">Limpar</a>
            <?php endif; ?>
          </form>
          <p class="text-[12px] text-slate-500"><?= count($rows) ?> registro(s)</p>
        </div>

        <!-- Tabela -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="tbl w-full">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Segmento</th>
                  <th>Contato</th>
                  <th>Status</th>
                  <th>Atendente</th>
                  <th>Comissão</th>
                  <th>St. comissão</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-slate-500 py-8">Nenhum interessado cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                  $stClass = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600';
                  $scClass = $comissaoColors[$r['status_comissao']] ?? 'bg-slate-100 text-slate-600';
                  $atendenteLabel = $r['atendente_nome'] ?: ($r['atendente_username'] ?: '—');
                ?>
                <tr>
                  <td class="font-semibold text-slate-800"><?= h($r['nome_cliente']) ?></td>
                  <td><?= h($r['segmento'] ?: '—') ?></td>
                  <td><?= h($r['contato'] ?: '—') ?></td>
                  <td><span class="badge <?= $stClass ?>"><?= h($r['status'] ?: '—') ?></span></td>
                  <td><?= h($atendenteLabel) ?></td>
                  <td><?= h(fmtComissao($r['comissao'] !== null ? (float) $r['comissao'] : null)) ?></td>
                  <td>
                    <?php if ($r['status_comissao']): ?>
                    <span class="badge <?= $scClass ?>"><?= h($r['status_comissao']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td class="whitespace-nowrap">
                    <button type="button" class="btn-ghost" onclick='openEdit(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Editar</button>
                    <form method="POST" class="inline" onsubmit="return confirm('Remover este registro?')">
                      <input type="hidden" name="action" value="delete"/>
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                      <button type="submit" class="btn-danger">Remover</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
    </div>

    <!-- Opções configuráveis -->
    <div class="card px-5 py-5 space-y-5">
      <div>
        <p class="text-[13px] font-semibold text-slate-300">Opções do sistema</p>
        <p class="text-[12px] text-slate-500 mt-1">Cadastre segmentos, status e status de comissão usados nos formulários.</p>
      </div>
      <div class="grid gap-6 md:grid-cols-3">
        <?php
        $grupos = [
          'segmento' => ['label' => 'Segmentos', 'itens' => $segmentos],
          'status' => ['label' => 'Status', 'itens' => $statusList],
          'status_comissao' => ['label' => 'Status comissão', 'itens' => $statusComissaoList],
        ];
        foreach ($grupos as $tipo => $grupo):
        ?>
        <div class="rounded-xl border border-slate-200 p-4 space-y-3">
          <p class="text-[12px] font-bold text-slate-700 uppercase tracking-widest"><?= h($grupo['label']) ?></p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($grupo['itens'] as $op): ?>
            <span class="opcao-tag">
              <?= h($op['valor']) ?>
              <form method="POST" class="inline" onsubmit="return confirm('Remover opção?')">
                <input type="hidden" name="action" value="delete_opcao"/>
                <input type="hidden" name="opcao_id" value="<?= (int) $op['id'] ?>"/>
                <button type="submit" class="text-red-500 hover:text-red-700 text-[11px] font-bold leading-none" title="Remover">×</button>
              </form>
            </span>
            <?php endforeach; ?>
            <?php if (!$grupo['itens']): ?>
            <span class="text-[12px] text-slate-400">Nenhuma opção cadastrada.</span>
            <?php endif; ?>
          </div>
          <form method="POST" class="flex gap-2">
            <input type="hidden" name="action" value="add_opcao"/>
            <input type="hidden" name="tipo" value="<?= h($tipo) ?>"/>
            <input type="text" name="valor" required class="input flex-1" placeholder="Nova opção"/>
            <button type="submit" class="btn-primary shrink-0">+</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php endif; ?>
  </main>

  <!-- Modal novo interessado -->
  <div id="modal-create" class="modal-overlay fixed inset-0 z-50 hidden items-center justify-center px-4 py-6">
    <div class="modal-panel w-full max-w-lg px-6 py-6 space-y-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="modal-title text-[16px]">Novo interessado</h2>
          <p class="modal-subtitle text-[12px] mt-1">Preencha os dados do lead.</p>
        </div>
        <button type="button" onclick="closeCreate()" class="modal-close text-2xl font-light shrink-0" aria-label="Fechar">×</button>
      </div>
      <form method="POST" class="space-y-3" id="form-create">
        <input type="hidden" name="action" value="create"/>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Nome do cliente *</label>
          <input type="text" name="nome_cliente" id="create-nome" required class="input" placeholder="Ex: Maria Silva" value="<?= h($_POST['nome_cliente'] ?? '') ?>"/>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Segmento</label>
          <select name="segmento" id="create-segmento" class="input" style="cursor:pointer">
            <option value="">— Selecione —</option>
            <?php foreach ($segmentos as $op): ?>
            <option value="<?= h($op['valor']) ?>" <?= ($_POST['segmento'] ?? '') === $op['valor'] ? 'selected' : '' ?>><?= h($op['valor']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Contato</label>
          <input type="text" name="contato" id="create-contato" class="input" placeholder="WhatsApp, e-mail ou telefone" value="<?= h($_POST['contato'] ?? '') ?>"/>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status</label>
          <select name="status" id="create-status" class="input" style="cursor:pointer">
            <?php foreach ($statusList as $op): ?>
            <option value="<?= h($op['valor']) ?>" <?= ($_POST['status'] ?? 'Novo') === $op['valor'] ? 'selected' : '' ?>><?= h($op['valor']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Atendente</label>
          <select name="atendente_id" id="create-atendente" class="input" style="cursor:pointer">
            <option value="">— Nenhum —</option>
            <?php foreach ($atendentes as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= (int)($_POST['atendente_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= h($a['name'] ?: $a['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Comissão (R$)</label>
            <input type="text" name="comissao" id="create-comissao" class="input" placeholder="0,00" value="<?= h($_POST['comissao'] ?? '') ?>"/>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status comissão</label>
            <select name="status_comissao" id="create-status-comissao" class="input" style="cursor:pointer">
              <option value="">—</option>
              <?php foreach ($statusComissaoList as $op): ?>
              <option value="<?= h($op['valor']) ?>" <?= ($_POST['status_comissao'] ?? '') === $op['valor'] ? 'selected' : '' ?>><?= h($op['valor']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Observações</label>
          <textarea name="observacoes" id="create-obs" rows="2" class="input" placeholder="Anotações internas"><?= h($_POST['observacoes'] ?? '') ?></textarea>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="btn-primary flex-1">Cadastrar</button>
          <button type="button" onclick="closeCreate()" class="btn-ghost flex-1">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal editar -->
  <div id="modal-edit" class="modal-overlay fixed inset-0 z-50 hidden items-center justify-center px-4 py-6">
    <div class="modal-panel w-full max-w-lg px-6 py-6 space-y-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-3">
        <h2 class="modal-title text-[16px]">Editar interessado</h2>
        <button type="button" onclick="closeEdit()" class="modal-close text-2xl font-light shrink-0" aria-label="Fechar">×</button>
      </div>
      <form method="POST" class="space-y-3" id="form-edit">
        <input type="hidden" name="action" value="edit"/>
        <input type="hidden" name="id" id="edit-id"/>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Nome do cliente *</label>
          <input type="text" name="nome_cliente" id="edit-nome" required class="input"/>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Segmento</label>
          <select name="segmento" id="edit-segmento" class="input" style="cursor:pointer">
            <option value="">— Selecione —</option>
            <?php foreach ($segmentos as $op): ?>
            <option value="<?= h($op['valor']) ?>"><?= h($op['valor']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Contato</label>
          <input type="text" name="contato" id="edit-contato" class="input"/>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status</label>
          <select name="status" id="edit-status" class="input" style="cursor:pointer">
            <?php foreach ($statusList as $op): ?>
            <option value="<?= h($op['valor']) ?>"><?= h($op['valor']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Atendente</label>
          <select name="atendente_id" id="edit-atendente" class="input" style="cursor:pointer">
            <option value="">— Nenhum —</option>
            <?php foreach ($atendentes as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= h($a['name'] ?: $a['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Comissão (R$)</label>
            <input type="text" name="comissao" id="edit-comissao" class="input"/>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Status comissão</label>
            <select name="status_comissao" id="edit-status-comissao" class="input" style="cursor:pointer">
              <option value="">—</option>
              <?php foreach ($statusComissaoList as $op): ?>
              <option value="<?= h($op['valor']) ?>"><?= h($op['valor']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">Observações</label>
          <textarea name="observacoes" id="edit-obs" rows="2" class="input"></textarea>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="btn-primary flex-1">Salvar</button>
          <button type="button" onclick="closeEdit()" class="btn-ghost flex-1">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openCreate() {
      const m = document.getElementById('modal-create');
      m.classList.remove('hidden');
      m.classList.add('flex');
      document.getElementById('create-nome').focus();
    }
    function closeCreate() {
      const m = document.getElementById('modal-create');
      m.classList.add('hidden');
      m.classList.remove('flex');
    }
    document.getElementById('modal-create').addEventListener('click', function(e) {
      if (e.target === this) closeCreate();
    });

    function openEdit(row) {
      document.getElementById('edit-id').value = row.id;
      document.getElementById('edit-nome').value = row.nome_cliente || '';
      document.getElementById('edit-segmento').value = row.segmento || '';
      document.getElementById('edit-contato').value = row.contato || '';
      document.getElementById('edit-status').value = row.status || 'Novo';
      document.getElementById('edit-atendente').value = row.atendente_id || '';
      document.getElementById('edit-comissao').value = row.comissao != null
        ? Number(row.comissao).toFixed(2).replace('.', ',') : '';
      document.getElementById('edit-status-comissao').value = row.status_comissao || '';
      document.getElementById('edit-obs').value = row.observacoes || '';
      const m = document.getElementById('modal-edit');
      m.classList.remove('hidden');
      m.classList.add('flex');
    }
    function closeEdit() {
      const m = document.getElementById('modal-edit');
      m.classList.add('hidden');
      m.classList.remove('flex');
    }
    document.getElementById('modal-edit').addEventListener('click', function(e) {
      if (e.target === this) closeEdit();
    });

    <?php if ($openCreateModal): ?>openCreate();<?php endif; ?>
  </script>
</body>
</html>
