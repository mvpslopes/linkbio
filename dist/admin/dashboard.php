<?php
require_once __DIR__ . '/includes/auth.php';
$user    = require_auth();
$isRoot  = $user['role'] === 'root';
$pdo     = db();

// ── Slugs disponíveis ────────────────────────────────────────
if ($isRoot) {
    $slugs = $pdo->query("SELECT DISTINCT page_slug, name FROM users WHERE role='client' ORDER BY name")->fetchAll();
} else {
    $slugs = [['page_slug' => $user['page_slug'], 'name' => $user['username']]];
}
$selected = preg_replace('/[^a-z0-9_\-]/', '', $_GET['page'] ?? $slugs[0]['page_slug'] ?? '');
if (!$isRoot && $selected !== $user['page_slug']) $selected = $user['page_slug'];

// ── Período ──────────────────────────────────────────────────
$period = $_GET['period'] ?? '7d';
$periodMap = [
    'today' => "DATE(created_at) = CURDATE()",
    '7d'    => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    'all'   => "1=1",
];
if (!isset($periodMap[$period])) $period = '7d';
$whereTime = $periodMap[$period];

// ── Helper para queries ──────────────────────────────────────
function q(PDO $pdo, string $sql, array $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s;
}

// ── Dados do período ─────────────────────────────────────────
$total_views   = (int) q($pdo,"SELECT COUNT(*) FROM page_views WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$uniq_visitors = (int) q($pdo,"SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$total_clicks  = (int) q($pdo,"SELECT COUNT(*) FROM click_events WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$online_now    = (int) q($pdo,"SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",[$selected])->fetchColumn();

// Taxa de conversão e bounce
$conv_rate = $total_views > 0 ? round($total_clicks / $total_views * 100, 1) : 0;
$views_per_visitor = $uniq_visitors > 0 ? round($total_views / $uniq_visitors, 1) : 0;

// ── Gráfico por dia ──────────────────────────────────────────
$days_interval = match($period) { 'today' => 0, '7d' => 6, '30d' => 29, '90d' => 89, 'all' => 29, default => 6 };
$daily_rows = q($pdo,"
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM page_views WHERE page_slug=? AND $whereTime
    GROUP BY day ORDER BY day ASC
", [$selected])->fetchAll();

$chartLabels = []; $chartData = [];
for ($i = $days_interval; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $fmt  = $period === 'today' ? '' : date('d/m', strtotime($date));
    if ($fmt) $chartLabels[] = $fmt;
    $found = 0;
    foreach ($daily_rows as $d) { if ($d['day'] === $date) { $found = (int)$d['total']; break; } }
    if ($fmt) $chartData[] = $found;
}
if ($period === 'today') {
    // Agrupado por hora
    $hourly = q($pdo,"SELECT HOUR(created_at) AS h, COUNT(*) AS total FROM page_views WHERE page_slug=? AND DATE(created_at)=CURDATE() GROUP BY h ORDER BY h",[$selected])->fetchAll();
    for ($h = 0; $h < 24; $h++) {
        $chartLabels[] = str_pad($h,2,'0',STR_PAD_LEFT).':00';
        $found = 0;
        foreach ($hourly as $row) { if ((int)$row['h'] === $h) { $found = (int)$row['total']; break; } }
        $chartData[] = $found;
    }
}

// ── Pico de horário ──────────────────────────────────────────
$peak_hours = q($pdo,"
    SELECT HOUR(created_at) AS h, COUNT(*) AS total
    FROM page_views WHERE page_slug=? AND $whereTime
    GROUP BY h ORDER BY h
", [$selected])->fetchAll();
$peak_max = max(array_column($peak_hours, 'total') ?: [1]);

// ── Dia da semana ────────────────────────────────────────────
$dow_names = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$dow_rows  = q($pdo,"
    SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total
    FROM page_views WHERE page_slug=? AND $whereTime
    GROUP BY dow ORDER BY dow
", [$selected])->fetchAll();
$dow_data = array_fill(1, 7, 0);
foreach ($dow_rows as $r) $dow_data[(int)$r['dow']] = (int)$r['total'];
$dow_max = max($dow_data ?: [1]);

// ── Dispositivos ─────────────────────────────────────────────
$devices = q($pdo,"SELECT device, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY device ORDER BY total DESC",[$selected])->fetchAll();
$dev_total = array_sum(array_column($devices,'total')) ?: 1;

// ── Browsers ─────────────────────────────────────────────────
$browsers = q($pdo,"SELECT COALESCE(browser,'Unknown') AS browser, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY browser ORDER BY total DESC LIMIT 6",[$selected])->fetchAll();
$br_total  = array_sum(array_column($browsers,'total')) ?: 1;

// ── Sistemas operacionais ────────────────────────────────────
$os_rows  = q($pdo,"SELECT COALESCE(os,'Unknown') AS os, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY os ORDER BY total DESC LIMIT 6",[$selected])->fetchAll();
$os_total = array_sum(array_column($os_rows,'total')) ?: 1;

// ── Origem do tráfego ────────────────────────────────────────
$traffic = q($pdo,"
    SELECT
      CASE
        WHEN referrer='' OR referrer IS NULL THEN 'Direto'
        WHEN referrer REGEXP '(google|bing|yahoo|duckduckgo|baidu|yandex)' THEN 'Buscadores'
        WHEN referrer REGEXP '(instagram|facebook|twitter|tiktok|linkedin|youtube|t\\.co|whatsapp)' THEN 'Redes Sociais'
        ELSE 'Outros'
      END AS source,
      COUNT(*) AS total
    FROM page_views WHERE page_slug=? AND $whereTime
    GROUP BY source ORDER BY total DESC
", [$selected])->fetchAll();
$tr_total = array_sum(array_column($traffic,'total')) ?: 1;

// ── Países ───────────────────────────────────────────────────
$countries = q($pdo,"SELECT COALESCE(country,'Desconhecido') AS country, COUNT(DISTINCT ip_hash) AS visitors, COUNT(*) AS views FROM page_views WHERE page_slug=? AND $whereTime GROUP BY country ORDER BY visitors DESC LIMIT 10",[$selected])->fetchAll();

// ── Cidades ──────────────────────────────────────────────────
$cities = q($pdo,"SELECT COALESCE(city,'Desconhecida') AS city, COALESCE(country,'') AS country, COUNT(DISTINCT ip_hash) AS visitors FROM page_views WHERE page_slug=? AND $whereTime AND city IS NOT NULL GROUP BY city, country ORDER BY visitors DESC LIMIT 10",[$selected])->fetchAll();

// ── Top cliques ──────────────────────────────────────────────
$top_clicks = q($pdo,"
    SELECT element_text, element_type, COUNT(*) AS total
    FROM click_events WHERE page_slug=? AND $whereTime
    GROUP BY element_text, element_type ORDER BY total DESC LIMIT 10
", [$selected])->fetchAll();

// ── Nome amigável do slug ────────────────────────────────────
$slugName = $selected;
foreach ($slugs as $s) { if ($s['page_slug'] === $selected) { $slugName = $s['name'] ?: $selected; break; } }

// ── clientPages dinâmico ─────────────────────────────────────
$clientPages = [];
foreach ($slugs as $s) {
    if ($s['page_slug']) $clientPages[$s['page_slug']] = 'https://' . $s['page_slug'] . '.linkbio.api.br';
}

$periodLabels = ['today'=>'Hoje','7d'=>'7 dias','30d'=>'30 dias','90d'=>'90 dias','all'=>'Todo período'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Dashboard — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif'] } } } };</script>
  <style>
    body { background: #080c18; }
    .sidebar-link.active { background: rgba(47,128,237,.15); color:#fff; border-color:rgba(47,128,237,.4); }
    .card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; }
    .bar-track { height:6px; background:rgba(255,255,255,.07); border-radius:99px; overflow:hidden; }
    .bar-fill  { height:100%; border-radius:99px; transition:width .4s ease; }
    .period-btn { border-radius:.6rem; padding:.35rem .85rem; font-size:.75rem; font-weight:600; transition:background .15s,color .15s; }
    .period-btn.active { background:#2F80ED; color:#fff; }
    .period-btn:not(.active) { background:rgba(255,255,255,.06); color:#64748b; }
    .period-btn:not(.active):hover { background:rgba(255,255,255,.1); color:#cbd5e1; }
    /* Mobile: contraste do seletor de página e período */
    .mobile-select { background:#0f172a !important; color:#e2e8f0 !important; border-color:rgba(148,163,184,.3) !important; }
    .mobile-select option { background:#0f172a; color:#e2e8f0; }
  </style>
</head>
<body class="text-slate-100 font-sans antialiased min-h-screen flex">

  <!-- Barra mobile: logo + Sair (sidebar fica escondida no mobile) -->
  <header class="md:hidden fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-4 py-3 border-b border-white/10 bg-[#060a14]">
    <a href="?page=<?= urlencode($selected) ?>&period=<?= urlencode($period) ?>" class="flex items-center gap-2 min-w-0">
      <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-6 w-auto max-w-[120px] object-contain"/>
    </a>
    <a href="/admin/logout.php" class="shrink-0 flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-medium text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
      </svg>
      Sair
    </a>
  </header>

  <!-- ── Sidebar ─────────────────────────────────────── -->
  <aside class="hidden md:flex flex-col w-60 shrink-0 border-r border-white/8 bg-[#060a14] px-3 py-5 gap-5">

    <div class="px-2 pt-1">
      <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-7 w-auto max-w-[140px] object-contain"/>
    </div>

    <nav class="flex flex-col gap-0.5 flex-1 overflow-y-auto">
      <p class="px-3 pt-1 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Páginas</p>

      <?php foreach ($slugs as $s):
        $isActive = $s['page_slug'] === $selected;
        $label    = $s['name'] ?: $s['page_slug'];
        $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/','',$label), 0, 2));
      ?>
      <div class="flex items-center gap-1 group rounded-xl <?= $isActive ? 'bg-[#2F80ED]/12 border border-[#2F80ED]/30' : 'border border-transparent hover:bg-white/5' ?> transition">
        <a href="?page=<?= urlencode($s['page_slug']) ?>&period=<?= $period ?>"
          class="flex items-center gap-2.5 flex-1 min-w-0 px-3 py-2.5 <?= $isActive ? 'text-white' : 'text-slate-400 hover:text-white' ?> transition">
          <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center text-[10px] font-bold"
            style="background:<?= $isActive ? 'rgba(47,128,237,.25)' : 'rgba(255,255,255,.08)' ?>;color:<?= $isActive ? '#7eb8f7' : '#94a3b8' ?>">
            <?= $initials ?>
          </span>
          <div class="min-w-0">
            <p class="text-[13px] font-medium leading-tight truncate"><?= htmlspecialchars($label) ?></p>
            <p class="text-[10px] text-slate-600 truncate"><?= htmlspecialchars($s['page_slug']) ?>.linkbio.api.br</p>
          </div>
        </a>
        <a href="https://<?= $s['page_slug'] ?>.linkbio.api.br" target="_blank" title="Abrir página"
          class="shrink-0 px-2 py-2.5 text-slate-600 hover:text-[#2F80ED] transition opacity-0 group-hover:opacity-100">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
        </a>
      </div>
      <?php endforeach; ?>

      <?php if (($isRoot && $selected === 'cristianoladeira') || (!$isRoot && ($user['page_slug'] ?? '') === 'cristianoladeira')): ?>
      <p class="px-3 pt-4 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Formulários</p>
      <a href="/admin/planeje_forms.php?page=cristianoladeira"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </span>
        Planeje espaço (inscrições)
      </a>
      <?php endif; ?>

      <?php if ($isRoot): ?>
      <p class="px-3 pt-4 pb-1 text-[10px] font-semibold text-slate-600 uppercase tracking-widest">Administração</p>
      <a href="/admin/users.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8z"/>
          </svg>
        </span>
        Usuários
      </a>
      <a href="/admin/validate_db.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </span>
        Validar banco
      </a>
      <a href="/admin/diagnostico_tracker.php"
        class="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-medium text-slate-400 hover:text-white hover:bg-white/5 transition">
        <span class="h-6 w-6 rounded-lg shrink-0 flex items-center justify-center bg-white/8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </span>
        Diagnóstico tracker
      </a>
      <?php endif; ?>
    </nav>

    <div class="border-t border-white/8 pt-3 space-y-0.5">
      <div class="flex items-center gap-2.5 px-3 py-2">
        <span class="h-7 w-7 rounded-full shrink-0 flex items-center justify-center text-[11px] font-bold bg-[#2F80ED]/20 text-[#7eb8f7]">
          <?= strtoupper(substr($user['username'], 0, 2)) ?>
        </span>
        <div class="min-w-0">
          <p class="text-[12px] font-semibold text-slate-300 truncate"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></p>
          <p class="text-[10px] text-slate-600"><?= $isRoot ? 'Administrador' : 'Cliente' ?></p>
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

  <!-- ── Main ─────────────────────────────────────────── -->
  <main class="flex-1 min-w-0 px-4 sm:px-8 pt-16 md:pt-8 pb-8 space-y-6 overflow-auto">

    <!-- Header -->
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div class="min-w-0 flex-1">
        <!-- Seletor de página (só mobile; no desktop a sidebar já lista as páginas) -->
        <?php if ($isRoot && count($slugs) > 1): ?>
        <form method="GET" class="md:hidden mb-3">
          <label for="mobile-page-select" class="sr-only">Página / subdomínio</label>
          <select id="mobile-page-select" name="page" onchange="this.form.submit()"
            class="mobile-select w-full max-w-[280px] rounded-xl border px-3 py-2.5 text-[14px] font-medium focus:outline-none focus:ring-2 focus:ring-[#2F80ED]/50">
            <?php foreach ($slugs as $s):
              $label = $s['name'] ?: $s['page_slug'];
            ?>
            <option value="<?= htmlspecialchars($s['page_slug']) ?>" <?= $s['page_slug'] === $selected ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?> (<?= htmlspecialchars($s['page_slug']) ?>.linkbio.api.br)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>"/>
        </form>
        <?php endif; ?>
        <p class="text-[11px] text-slate-500 uppercase tracking-widest mb-1">Analytics</p>
        <h1 class="text-xl font-bold text-white leading-tight"><?= htmlspecialchars($slugName) ?></h1>
        <p class="text-[12px] text-slate-600 mt-0.5"><?= htmlspecialchars($selected) ?>.linkbio.api.br</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <!-- Online agora -->
        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-[12px] text-emerald-400 font-medium">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
          <?= $online_now ?> online agora
        </span>
        <!-- Seletor de período mobile -->
        <form method="GET" class="md:hidden">
          <input type="hidden" name="page" value="<?= htmlspecialchars($selected) ?>"/>
          <select name="period" onchange="this.form.submit()" class="mobile-select rounded-xl border px-3 py-2 text-[13px] focus:outline-none">
            <?php foreach ($periodLabels as $k => $v): ?>
              <option value="<?= $k ?>" <?= $k===$period ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <!-- Botões de período desktop -->
        <div class="hidden md:flex items-center gap-1 bg-white/4 rounded-xl p-1 border border-white/8">
          <?php foreach ($periodLabels as $k => $v): ?>
            <a href="?page=<?= urlencode($selected) ?>&period=<?= $k ?>"
              class="period-btn <?= $k===$period ? 'active' : '' ?>"><?= $v ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- KPIs principais -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <?php
        $kpis = [
          ['Visitantes únicos', number_format($uniq_visitors), 'Visitas totais: '.number_format($total_views), 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197', '#2F80ED'],
          ['Visualizações',     number_format($total_views),   'Média por visitante: '.$views_per_visitor,     'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', '#10b981'],
          ['Total de cliques',  number_format($total_clicks),  'Interações registradas',                       'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5', '#f59e0b'],
          ['Taxa de conversão', $conv_rate.'%',                'Cliques por visita',                           'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', '#8b5cf6'],
        ];
        foreach ($kpis as [$label, $value, $sub, $path, $color]):
      ?>
      <div class="card px-5 py-4">
        <div class="flex items-center justify-between mb-3">
          <p class="text-[11px] text-slate-500 font-medium"><?= $label ?></p>
          <div class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0" style="background:<?= $color ?>22">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="<?= $color ?>" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/>
            </svg>
          </div>
        </div>
        <p class="text-2xl font-extrabold text-white"><?= $value ?></p>
        <p class="text-[11px] text-slate-600 mt-1"><?= $sub ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Gráfico visitantes ao longo do tempo -->
    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-slate-300 mb-4">Visitantes ao longo do tempo — <?= $periodLabels[$period] ?></p>
      <canvas id="chart" height="80"></canvas>
    </div>

    <!-- Linha 3: Horários de pico + Dia da semana -->
    <div class="grid gap-4 lg:grid-cols-2">

      <!-- Horários de pico -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Horários de pico</p>
        <div class="space-y-1.5">
          <?php for ($h = 0; $h < 24; $h++):
            $hVal = 0;
            foreach ($peak_hours as $r) { if ((int)$r['h'] === $h) { $hVal = (int)$r['total']; break; } }
            $pct = $peak_max > 0 ? round($hVal / $peak_max * 100) : 0;
          ?>
          <div class="flex items-center gap-2 text-[11px]">
            <span class="w-10 text-right text-slate-600 shrink-0"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00</span>
            <div class="flex-1 bar-track">
              <div class="bar-fill" style="width:<?= $pct ?>%;background:#2F80ED<?= $hVal>0?'':'00' ?>"></div>
            </div>
            <span class="w-5 text-slate-500 shrink-0"><?= $hVal > 0 ? $hVal : '' ?></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Atividade por dia da semana -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Atividade por dia da semana</p>
        <div class="space-y-2.5">
          <?php for ($d = 1; $d <= 7; $d++):
            $dVal = $dow_data[$d];
            $pct  = $dow_max > 0 ? round($dVal / $dow_max * 100) : 0;
          ?>
          <div class="flex items-center gap-3 text-[12px]">
            <span class="w-8 text-slate-500 shrink-0"><?= $dow_names[$d-1] ?></span>
            <div class="flex-1 bar-track">
              <div class="bar-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#10b981,#34d399)"></div>
            </div>
            <span class="w-6 text-right text-slate-400 shrink-0"><?= $dVal ?></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <!-- Linha 4: Dispositivos + Browsers + OS -->
    <div class="grid gap-4 lg:grid-cols-3">

      <!-- Dispositivos -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Dispositivos</p>
        <?php
          $devColors = ['mobile'=>'#2F80ED','desktop'=>'#10b981','tablet'=>'#f59e0b'];
          $devIcons  = [
            'mobile'  => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
            'desktop' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'tablet'  => 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
          ];
          foreach ($devices as $dv):
            $dName = $dv['device'] ?: 'desktop';
            $dPct  = round($dv['total']/$dev_total*100);
            $dCol  = $devColors[$dName] ?? '#64748b';
            $dPath = $devIcons[$dName] ?? $devIcons['desktop'];
        ?>
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="<?= $dCol ?>" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $dPath ?>"/>
              </svg>
              <span class="text-[13px] text-slate-300 capitalize"><?= $dName ?></span>
            </div>
            <span class="text-[12px] text-slate-400"><?= $dv['total'] ?> <span class="text-slate-600">(<?= $dPct ?>%)</span></span>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $dPct ?>%;background:<?= $dCol ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($devices)): ?><p class="text-[12px] text-slate-600">Sem dados ainda.</p><?php endif; ?>
      </div>

      <!-- Browsers -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Navegadores</p>
        <div class="space-y-3">
          <?php foreach ($browsers as $br):
            $pct = round($br['total']/$br_total*100);
          ?>
          <div>
            <div class="flex items-center justify-between mb-1">
              <span class="text-[13px] text-slate-300"><?= htmlspecialchars($br['browser']) ?></span>
              <span class="text-[12px] text-slate-400"><?= $br['total'] ?> <span class="text-slate-600">(<?= $pct ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#8b5cf6"></div></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($browsers)): ?><p class="text-[12px] text-slate-600">Sem dados ainda.</p><?php endif; ?>
        </div>
      </div>

      <!-- Sistemas Operacionais -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Sistemas Operacionais</p>
        <div class="space-y-3">
          <?php foreach ($os_rows as $os):
            $pct = round($os['total']/$os_total*100);
          ?>
          <div>
            <div class="flex items-center justify-between mb-1">
              <span class="text-[13px] text-slate-300"><?= htmlspecialchars($os['os']) ?></span>
              <span class="text-[12px] text-slate-400"><?= $os['total'] ?> <span class="text-slate-600">(<?= $pct ?>%)</span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#f59e0b"></div></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($os_rows)): ?><p class="text-[12px] text-slate-600">Sem dados ainda.</p><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Linha 5: Origem do tráfego + Países + Cidades -->
    <div class="grid gap-4 lg:grid-cols-3">

      <!-- Origem do tráfego -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Origem do tráfego</p>
        <?php
          $trColors = ['Redes Sociais'=>'#2F80ED','Buscadores'=>'#10b981','Direto'=>'#8b5cf6','Outros'=>'#64748b'];
          foreach ($traffic as $tr):
            $pct = round($tr['total']/$tr_total*100);
            $col = $trColors[$tr['source']] ?? '#64748b';
        ?>
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-[13px] text-slate-300"><?= $tr['source'] ?></span>
            <span class="text-[12px] text-slate-400"><?= $tr['total'] ?> <span class="text-slate-600">(<?= $pct ?>%)</span></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($traffic)): ?><p class="text-[12px] text-slate-600">Sem dados ainda.</p><?php endif; ?>
      </div>

      <!-- Países -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Acessos por país</p>
        <div class="space-y-2">
          <?php foreach ($countries as $c): ?>
          <div class="flex items-center justify-between text-[12px]">
            <span class="text-slate-300 truncate"><?= htmlspecialchars($c['country']) ?></span>
            <div class="text-right shrink-0 ml-2">
              <span class="text-slate-300 font-medium"><?= $c['visitors'] ?></span>
              <span class="text-slate-600 ml-1"><?= $c['views'] ?> views</span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($countries)): ?><p class="text-[12px] text-slate-600">Sem dados ainda. Os países aparecem conforme novos acessos chegarem.</p><?php endif; ?>
        </div>
      </div>

      <!-- Cidades -->
      <div class="card px-5 py-5">
        <p class="text-[13px] font-semibold text-slate-300 mb-4">Acessos por cidade</p>
        <div class="space-y-2">
          <?php foreach ($cities as $c): ?>
          <div class="flex items-center justify-between text-[12px]">
            <div class="min-w-0">
              <span class="text-slate-300 truncate block"><?= htmlspecialchars($c['city']) ?></span>
              <span class="text-slate-600 text-[10px]"><?= htmlspecialchars($c['country']) ?></span>
            </div>
            <span class="text-slate-400 shrink-0 ml-2"><?= $c['visitors'] ?> <?= $c['visitors']==1?'sessão':'sessões' ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($cities)): ?><p class="text-[12px] text-slate-600">Sem dados de cidade ainda.</p><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Top cliques -->
    <?php if (!empty($top_clicks)): ?>
    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-slate-300 mb-4">Elementos mais clicados</p>
      <div class="space-y-2">
        <?php
          $maxClicks = (int)($top_clicks[0]['total'] ?? 1);
          foreach ($top_clicks as $ck):
            $pct = round($ck['total']/$maxClicks*100);
        ?>
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-white/8 text-slate-400 shrink-0"><?= htmlspecialchars($ck['element_type']) ?></span>
          <div class="flex-1 min-w-0">
            <p class="text-[12px] text-slate-300 truncate"><?= htmlspecialchars($ck['element_text'] ?: '—') ?></p>
            <div class="bar-track mt-1"><div class="bar-fill" style="width:<?= $pct ?>%;background:#f59e0b"></div></div>
          </div>
          <span class="text-[13px] font-bold text-slate-300 shrink-0"><?= $ck['total'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <script>
    // Gráfico principal
    (function() {
      const labels = <?= json_encode($chartLabels) ?>;
      const data   = <?= json_encode($chartData)   ?>;
      if (!labels.length) return;
      const ctx = document.getElementById('chart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data,
            borderColor: '#2F80ED',
            backgroundColor: 'rgba(47,128,237,.12)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#2F80ED',
            pointRadius: labels.length > 20 ? 0 : 3,
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#475569', font: { size: 11 } } },
            y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#475569', font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
          }
        }
      });
    })();
  </script>
</body>
</html>
