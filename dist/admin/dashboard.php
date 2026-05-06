<?php
require_once __DIR__ . '/includes/auth.php';
$user    = require_auth();
$isRoot  = $user['role'] === 'root';
$pdo     = db();

// ── Slugs ────────────────────────────────────────────────────
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

// ── Período anterior ─────────────────────────────────────────
$prevMap = [
    'today' => "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    '7d'    => "created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
    'all'   => "1=0",
];
$wherePrev = $prevMap[$period];

// ── Helpers ──────────────────────────────────────────────────
function q(PDO $pdo, string $sql, array $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s;
}
function trend(int $now, int $prev): array {
    if ($prev === 0) return $now > 0 ? ['+∞', true] : ['—', null];
    $pct = round(($now - $prev) / $prev * 100);
    return [($pct >= 0 ? '+' : '') . $pct . '%', $pct >= 0];
}

// ── Dados atuais ─────────────────────────────────────────────
$total_views   = (int) q($pdo,"SELECT COUNT(*) FROM page_views WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$uniq_visitors = (int) q($pdo,"SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$total_clicks  = (int) q($pdo,"SELECT COUNT(*) FROM click_events WHERE page_slug=? AND $whereTime",[$selected])->fetchColumn();
$online_now    = (int) q($pdo,"SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",[$selected])->fetchColumn();
$last_visit    = q($pdo,"SELECT MAX(created_at) FROM page_views WHERE page_slug=?",[$selected])->fetchColumn();

// ── Dados anteriores ─────────────────────────────────────────
$prev_views    = (int) q($pdo,"SELECT COUNT(*) FROM page_views WHERE page_slug=? AND $wherePrev",[$selected])->fetchColumn();
$prev_visitors = (int) q($pdo,"SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND $wherePrev",[$selected])->fetchColumn();
$prev_clicks   = (int) q($pdo,"SELECT COUNT(*) FROM click_events WHERE page_slug=? AND $wherePrev",[$selected])->fetchColumn();

[$trend_views,    $tv_up]    = trend($total_views,   $prev_views);
[$trend_visitors, $ts_up]    = trend($uniq_visitors, $prev_visitors);
[$trend_clicks,   $tc_up]    = trend($total_clicks,  $prev_clicks);

$conv_rate   = $total_views > 0 ? round($total_clicks / $total_views * 100, 1) : 0;
$prev_conv   = $prev_views  > 0 ? round($prev_clicks  / $prev_views  * 100, 1) : 0;
[$trend_conv, $tconv_up] = trend((int)($conv_rate * 10), (int)($prev_conv * 10));

$views_per_visitor  = $uniq_visitors > 0 ? round($total_views / $uniq_visitors, 1) : 0;
$lastVisitFormatted = $last_visit ? date('d/m/Y \à\s H:i', strtotime($last_visit)) : '—';

// ── Gráfico ──────────────────────────────────────────────────
$days_interval     = match($period) { 'today' => 0, '7d' => 6, '30d' => 29, '90d' => 89, 'all' => 29, default => 6 };
$daily_views_rows  = q($pdo,"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM page_views   WHERE page_slug=? AND $whereTime GROUP BY day ORDER BY day",[$selected])->fetchAll();
$daily_clicks_rows = q($pdo,"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM click_events WHERE page_slug=? AND $whereTime GROUP BY day ORDER BY day",[$selected])->fetchAll();

$chartLabels = []; $chartData = []; $chartClicks = [];
for ($i = $days_interval; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $fmt  = $period === 'today' ? '' : date('d/m', strtotime($date));
    if ($fmt) {
        $chartLabels[] = $fmt;
        $fv = 0; foreach ($daily_views_rows  as $d) { if ($d['day']===$date) { $fv=(int)$d['total']; break; } }
        $fc = 0; foreach ($daily_clicks_rows as $d) { if ($d['day']===$date) { $fc=(int)$d['total']; break; } }
        $chartData[]   = $fv;
        $chartClicks[] = $fc;
    }
}
if ($period === 'today') {
    $hourly_v = q($pdo,"SELECT HOUR(created_at) AS h, COUNT(*) AS total FROM page_views   WHERE page_slug=? AND DATE(created_at)=CURDATE() GROUP BY h ORDER BY h",[$selected])->fetchAll();
    $hourly_c = q($pdo,"SELECT HOUR(created_at) AS h, COUNT(*) AS total FROM click_events WHERE page_slug=? AND DATE(created_at)=CURDATE() GROUP BY h ORDER BY h",[$selected])->fetchAll();
    for ($h = 0; $h < 24; $h++) {
        $chartLabels[] = str_pad($h,2,'0',STR_PAD_LEFT).':00';
        $fv = 0; foreach ($hourly_v as $r) { if ((int)$r['h']===$h) { $fv=(int)$r['total']; break; } }
        $fc = 0; foreach ($hourly_c as $r) { if ((int)$r['h']===$h) { $fc=(int)$r['total']; break; } }
        $chartData[]   = $fv;
        $chartClicks[] = $fc;
    }
}

// ── Heatmap (dia da semana × hora) ───────────────────────────
$heatmap_rows = q($pdo,"
    SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS h, COUNT(*) AS total
    FROM page_views WHERE page_slug=? AND $whereTime GROUP BY dow, h
",[$selected])->fetchAll();
$heatmap = [];
for ($d = 1; $d <= 7; $d++) for ($h = 0; $h < 24; $h++) $heatmap[$d][$h] = 0;
foreach ($heatmap_rows as $r) $heatmap[(int)$r['dow']][(int)$r['h']] = (int)$r['total'];
$heatmap_max = max(array_merge([1], array_map('max', $heatmap)));

// ── Dispositivos ─────────────────────────────────────────────
$devices   = q($pdo,"SELECT device, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY device ORDER BY total DESC",[$selected])->fetchAll();
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
    FROM page_views WHERE page_slug=? AND $whereTime GROUP BY source ORDER BY total DESC
",[$selected])->fetchAll();
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
",[$selected])->fetchAll();

// ── Helpers de exibição ──────────────────────────────────────
$slugName = $selected;
foreach ($slugs as $s) { if ($s['page_slug']===$selected) { $slugName = $s['name'] ?: $selected; break; } }
$clientPages = [];
foreach ($slugs as $s) { if ($s['page_slug']) $clientPages[$s['page_slug']] = 'https://'.$s['page_slug'].'.linkbio.api.br'; }
$periodLabels = ['today'=>'Hoje','7d'=>'7 dias','30d'=>'30 dias','90d'=>'90 dias','all'=>'Todo período'];
$pageUrl = 'https://'.$selected.'.linkbio.api.br';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Dashboard — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif'] } } } };</script>
  <style>
    :root {
      --bg:      #0f172a;
      --bg2:     #1e293b;
      --sidebar: #172554;
      --card:    rgba(255,255,255,0.08);
      --border:  rgba(255,255,255,0.15);
      --border2: rgba(255,255,255,0.24);
    }
    * { box-sizing: border-box; }
    html, body { background: radial-gradient(1200px 500px at 10% -20%, #1d4ed8 0%, transparent 45%), linear-gradient(180deg, var(--bg2) 0%, var(--bg) 100%); }

    .sidebar  { background: var(--sidebar); border-right: 1px solid var(--border); }
    .card     { background: var(--card); border: 1px solid var(--border); border-radius: 14px; transition: border-color .2s; backdrop-filter: blur(3px); }
    .card:hover { border-color: var(--border2); }

    .nav-item { border-radius: 9px; border: 1px solid transparent; transition: background .15s, border-color .15s; }
    .nav-item:hover { background: rgba(255,255,255,.05); }
    .nav-item.active { background: rgba(59,130,246,.14); border-color: rgba(59,130,246,.22); }

    .period-btn { border-radius: 7px; padding: 4px 13px; font-size: 12px; font-weight: 600; transition: all .15s; }
    .period-btn.active { background: #ffffff; color: #0c1428; }
    .period-btn:not(.active) { color: #cbd5e1; }
    .period-btn:not(.active):hover { background: rgba(255,255,255,.12); color: #ffffff; }

    .bar-track { height: 4px; background: rgba(255,255,255,.18); border-radius: 99px; overflow: hidden; }
    .bar-fill  { height: 100%; border-radius: 99px; transition: width .5s ease; }

    .mobile-select { background: #1e3a8a !important; color: #fff !important; border-color: var(--border) !important; }
    .mobile-select option { background: #1e3a8a; color: #fff; }

    .kpi-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    .badge-up   { color: #34d399; font-size: 11px; font-weight: 700; }
    .badge-down { color: #f87171; font-size: 11px; font-weight: 700; }
    .badge-flat { color: #cbd5e1; font-size: 11px; }

    .heat-cell { border-radius: 3px; height: 18px; min-width: 0; transition: opacity .2s; }
    .heat-cell:hover { opacity: .75; cursor: default; }

    ::-webkit-scrollbar { width: 4px; height: 4px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.22); border-radius: 99px; }

    /* Painel principal claro */
    .main-panel {
      background: #ffffff;
      color: #0f172a;
    }
    .main-panel .card {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
      backdrop-filter: none;
    }
    .main-panel .card:hover { border-color: #cbd5e1; }
    .main-panel .period-btn:not(.active) { color: #475569; }
    .main-panel .period-btn:not(.active):hover { background: #f1f5f9; color: #0f172a; }
    .main-panel .bar-track { background: #e2e8f0; }
    .main-panel .text-white { color: #0f172a !important; }
    .main-panel .text-slate-700 { color: #64748b !important; }
    .main-panel .text-slate-600 { color: #475569 !important; }
    .main-panel .text-slate-500 { color: #64748b !important; }
    .main-panel .text-slate-400 { color: #334155 !important; }
    .main-panel .text-slate-300 { color: #1e293b !important; }

    /* Sidebar com fontes brancas */
    .sidebar .text-white { color: #ffffff !important; }
    .sidebar .text-slate-700 { color: #dbeafe !important; }
    .sidebar .text-slate-600 { color: #bfdbfe !important; }
    .sidebar .text-slate-500 { color: #bfdbfe !important; }
    .sidebar .text-slate-400 { color: #e2e8f0 !important; }
    .sidebar .text-slate-300 { color: #f8fafc !important; }
    .sidebar .text-blue-400 { color: #bfdbfe !important; }
    .sidebar .nav-item:hover { background: rgba(255,255,255,.12); }

    /* Contraste extra no painel branco */
    .main-panel .period-btn.active { background: #1d4ed8; color: #ffffff; }
    .main-panel .period-btn:not(.active) { color: #334155; border: 1px solid #cbd5e1; }
    .main-panel .period-btn:not(.active):hover { background: #e2e8f0; color: #0f172a; border-color: #94a3b8; }
    .main-panel .text-blue-400 { color: #1d4ed8 !important; }
    .main-panel .text-blue-400:hover { color: #1e40af !important; }
    .main-panel .btn-primary {
      color: #ffffff !important;
      background: #1d4ed8 !important;
    }
    .main-panel .btn-primary:hover { background: #1e40af !important; }
    .main-panel .kpi-solid {
      border: none !important;
      box-shadow: none !important;
    }
    .main-panel .kpi-solid .kpi-label,
    .main-panel .kpi-solid .kpi-sub,
    .main-panel .kpi-solid .kpi-value,
    .main-panel .kpi-solid .badge-up,
    .main-panel .kpi-solid .badge-down,
    .main-panel .kpi-solid .badge-flat {
      color: #ffffff !important;
    }
    .main-panel .kpi-solid .kpi-icon {
      background: rgba(255,255,255,.22) !important;
    }
    .main-panel .kpi-solid .kpi-icon svg {
      stroke: #ffffff !important;
    }

    @keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    .fu  { animation: fadeUp .45s ease both; }
    .fu1 { animation-delay: .04s; }
    .fu2 { animation-delay: .08s; }
    .fu3 { animation-delay: .12s; }
    .fu4 { animation-delay: .16s; }
    .fu5 { animation-delay: .20s; }
    .fu6 { animation-delay: .24s; }
    .fu7 { animation-delay: .28s; }
  </style>
</head>
<body class="text-slate-100 font-sans antialiased min-h-screen flex">

<!-- ── Mobile header ──────────────────────────────────────── -->
<header class="md:hidden fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-4 py-3" style="background:#1e3a8a;border-bottom:1px solid var(--border)">
  <a href="?page=<?= urlencode($selected) ?>&period=<?= urlencode($period) ?>">
    <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-6 w-auto max-w-[110px] object-contain"/>
  </a>
  <a href="/admin/logout.php" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] text-white hover:text-red-200 hover:bg-red-500/20 transition">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
    </svg>
    Sair
  </a>
</header>

<!-- ── Sidebar ────────────────────────────────────────────── -->
<aside class="sidebar hidden md:flex flex-col w-60 shrink-0 px-3 py-5 gap-4">
  <div class="px-2 pb-1">
    <img src="/logo/logo-link-bio-2.png" alt="LinkBio" class="h-7 w-auto max-w-[130px] object-contain"/>
  </div>

  <nav class="flex flex-col gap-0.5 flex-1 overflow-y-auto">
    <p class="px-2 pt-1 pb-2 text-[10px] font-semibold text-slate-700 uppercase tracking-widest">Páginas</p>

    <?php foreach ($slugs as $s):
      $isActive = $s['page_slug'] === $selected;
      $label    = $s['name'] ?: $s['page_slug'];
      $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/','',$label), 0, 2));
    ?>
    <div class="flex items-center gap-1 group nav-item <?= $isActive ? 'active' : '' ?>">
      <a href="?page=<?= urlencode($s['page_slug']) ?>&period=<?= $period ?>"
        class="flex items-center gap-2.5 flex-1 min-w-0 px-2.5 py-2.5">
        <span class="h-7 w-7 rounded-lg shrink-0 flex items-center justify-center text-[10px] font-bold"
          style="background:<?= $isActive ? 'rgba(59,130,246,.22)' : 'rgba(255,255,255,.06)' ?>;color:<?= $isActive ? '#93c5fd' : '#475569' ?>">
          <?= $initials ?>
        </span>
        <div class="min-w-0">
          <p class="text-[13px] font-medium leading-tight truncate <?= $isActive ? 'text-white' : 'text-slate-400' ?>"><?= htmlspecialchars($label) ?></p>
          <p class="text-[10px] text-slate-700 truncate"><?= $s['page_slug'] ?>.linkbio</p>
        </div>
      </a>
      <a href="https://<?= $s['page_slug'] ?>.linkbio.api.br" target="_blank"
        class="shrink-0 px-2 py-2.5 text-slate-700 hover:text-blue-400 transition opacity-0 group-hover:opacity-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
      </a>
    </div>
    <?php endforeach; ?>

    <?php if (($isRoot && $selected === 'cristianoladeira') || (!$isRoot && ($user['page_slug'] ?? '') === 'cristianoladeira')): ?>
    <?php $isPlanejeForms = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '') === 'planeje_forms.php'; ?>
    <p class="px-2 pt-4 pb-2 text-[10px] font-semibold text-slate-700 uppercase tracking-widest">Formulários</p>
    <a href="/admin/planeje_forms.php?page=cristianoladeira"
      class="flex items-center gap-2.5 nav-item px-2.5 py-2.5 text-[13px] <?= $isPlanejeForms ? 'bg-white/20 border border-white/30 text-white' : 'text-slate-400 hover:text-white' ?> transition">
      <span class="h-7 w-7 rounded-lg shrink-0 flex items-center justify-center" style="background:<?= $isPlanejeForms ? 'rgba(255,255,255,.25)' : 'rgba(255,255,255,.08)' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 <?= $isPlanejeForms ? 'text-white' : 'text-slate-500' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </span>
      Planeje espaço
    </a>
    <?php endif; ?>

    <?php if ($isRoot): ?>
    <?php $isUsersPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '') === 'users.php'; ?>
    <p class="px-2 pt-4 pb-2 text-[10px] font-semibold text-slate-700 uppercase tracking-widest">Administração</p>
    <a href="/admin/users.php" class="flex items-center gap-2.5 nav-item px-2.5 py-2.5 text-[13px] <?= $isUsersPage ? 'bg-white/20 border border-white/30 text-white' : 'text-slate-400 hover:text-white' ?> transition">
      <span class="h-7 w-7 rounded-lg shrink-0 flex items-center justify-center" style="background:<?= $isUsersPage ? 'rgba(255,255,255,.25)' : 'rgba(255,255,255,.08)' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 <?= $isUsersPage ? 'text-white' : 'text-slate-500' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8z"/>
        </svg>
      </span>
      Usuários
    </a>
    <a href="/admin/validate_db.php" class="flex items-center gap-2.5 nav-item px-2.5 py-2.5 text-[13px] text-slate-400 hover:text-white transition">
      <span class="h-7 w-7 rounded-lg shrink-0 flex items-center justify-center" style="background:rgba(255,255,255,.05)">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </span>
      Validar banco
    </a>
    <a href="/admin/diagnostico_tracker.php" class="flex items-center gap-2.5 nav-item px-2.5 py-2.5 text-[13px] text-slate-400 hover:text-white transition">
      <span class="h-7 w-7 rounded-lg shrink-0 flex items-center justify-center" style="background:rgba(255,255,255,.05)">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        </svg>
      </span>
      Diagnóstico tracker
    </a>
    <?php endif; ?>
  </nav>

  <div class="pt-3 space-y-0.5" style="border-top:1px solid var(--border)">
    <div class="flex items-center gap-2.5 px-2.5 py-2">
      <span class="h-7 w-7 rounded-full shrink-0 flex items-center justify-center text-[11px] font-bold bg-blue-500/15 text-blue-400">
        <?= strtoupper(substr($user['username'], 0, 2)) ?>
      </span>
      <div class="min-w-0">
        <p class="text-[12px] font-semibold text-white truncate"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></p>
        <p class="text-[10px] text-slate-700"><?= $isRoot ? 'Administrador' : 'Cliente' ?></p>
      </div>
    </div>
    <a href="/admin/logout.php"
      class="flex items-center gap-2.5 nav-item px-2.5 py-2 text-[13px] text-slate-600 hover:text-red-400 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
      </svg>
      Sair da conta
    </a>
  </div>
</aside>

<!-- ── Main ───────────────────────────────────────────────── -->
<main class="main-panel flex-1 min-w-0 px-4 sm:px-8 pt-16 md:pt-8 pb-10 space-y-5 overflow-auto">

  <!-- Cabeçalho -->
  <div class="fu flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0 flex-1">
      <?php if ($isRoot && count($slugs) > 1): ?>
      <form method="GET" class="md:hidden mb-3">
        <select name="page" onchange="this.form.submit()" class="mobile-select w-full max-w-[280px] rounded-xl border px-3 py-2 text-[13px] font-medium focus:outline-none">
          <?php foreach ($slugs as $s): $lbl = $s['name'] ?: $s['page_slug']; ?>
          <option value="<?= htmlspecialchars($s['page_slug']) ?>" <?= $s['page_slug']===$selected?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>"/>
      </form>
      <?php endif; ?>
      <p class="text-[10px] text-slate-700 uppercase tracking-widest mb-0.5 font-semibold">Analytics</p>
      <h1 class="text-xl font-bold text-white"><?= htmlspecialchars($slugName) ?></h1>
      <p class="text-[12px] text-slate-700 mt-0.5"><?= htmlspecialchars($selected) ?>.linkbio.api.br</p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
      <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-medium text-emerald-400"
        style="border:1px solid rgba(52,211,153,.18);background:rgba(52,211,153,.08)">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
        <?= $online_now ?> online agora
      </span>
      <!-- Mobile: select período -->
      <form method="GET" class="md:hidden">
        <input type="hidden" name="page" value="<?= htmlspecialchars($selected) ?>"/>
        <select name="period" onchange="this.form.submit()" class="mobile-select rounded-xl border px-3 py-2 text-[13px] focus:outline-none">
          <?php foreach ($periodLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $k===$period?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- Desktop: botões período -->
      <div class="hidden md:flex items-center gap-0.5 rounded-xl p-1" style="background:rgba(255,255,255,.03);border:1px solid var(--border)">
        <?php foreach ($periodLabels as $k => $v): ?>
        <a href="?page=<?= urlencode($selected) ?>&period=<?= $k ?>" class="period-btn <?= $k===$period?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Card de resumo da página -->
  <div class="card fu fu1 px-5 py-4 flex flex-wrap items-center gap-5 justify-between">
    <div class="flex items-center gap-4 min-w-0">
      <div class="h-11 w-11 rounded-xl shrink-0 flex items-center justify-center text-[13px] font-bold"
        style="background:rgba(59,130,246,.12);color:#93c5fd">
        <?= strtoupper(substr(preg_replace('/[^a-zA-Z]/','',$slugName), 0, 2)) ?>
      </div>
      <div class="min-w-0">
        <p class="text-[15px] font-semibold text-white truncate"><?= htmlspecialchars($slugName) ?></p>
        <div class="flex items-center gap-2 mt-0.5">
          <a href="<?= htmlspecialchars($pageUrl) ?>" target="_blank"
            class="text-[12px] text-blue-400 hover:text-blue-300 transition truncate"><?= htmlspecialchars($pageUrl) ?></a>
          <button id="copy-btn" onclick="
            navigator.clipboard.writeText('<?= htmlspecialchars($pageUrl) ?>').then(()=>{
              const b = document.getElementById('copy-btn');
              b.textContent = '✓ Copiado'; b.style.color = '#34d399';
              setTimeout(()=>{ b.textContent = 'Copiar'; b.style.color = ''; }, 1800);
            })"
            class="shrink-0 text-[10px] font-semibold text-slate-700 hover:text-slate-900 border rounded px-1.5 py-0.5 transition"
            style="border-color:#94a3b8;background:#f8fafc">
            Copiar
          </button>
        </div>
      </div>
    </div>
    <div class="flex items-center gap-6 flex-wrap">
      <div>
        <p class="text-[10px] text-slate-700 uppercase tracking-widest font-semibold mb-0.5">Último acesso</p>
        <p class="text-[13px] font-medium text-white"><?= $lastVisitFormatted ?></p>
      </div>
      <div>
        <p class="text-[10px] text-slate-700 uppercase tracking-widest font-semibold mb-0.5">Status</p>
        <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-emerald-400">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
          Online
        </span>
      </div>
      <a href="<?= htmlspecialchars($pageUrl) ?>" target="_blank"
        class="btn-primary inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        Ver página
      </a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <?php
      $kpis = [
        ['Visitantes únicos', number_format($uniq_visitors), $trend_visitors, $ts_up,   'Total de visitas: '.number_format($total_views), 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197', '#3b82f6'],
        ['Visualizações',     number_format($total_views),   $trend_views,    $tv_up,   'Média por visitante: '.$views_per_visitor,       'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', '#10b981'],
        ['Cliques',           number_format($total_clicks),  $trend_clicks,   $tc_up,   'Interações registradas',                          'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5', '#f59e0b'],
        ['Conversão',         $conv_rate.'%',                $trend_conv,     $tconv_up,'Cliques ÷ visualizações',                        'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', '#8b5cf6'],
      ];
      $delays = ['fu2','fu3','fu4','fu5'];
      foreach ($kpis as $i => [$label, $value, $tval, $tup, $sub, $path, $color]):
    ?>
    <div class="card kpi-solid <?= $delays[$i] ?> fu px-5 py-4" style="background:<?= $color ?>">
      <div class="flex items-center justify-between mb-3">
        <p class="kpi-label text-[11px] font-semibold uppercase tracking-widest"><?= $label ?></p>
        <div class="kpi-icon">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/>
          </svg>
        </div>
      </div>
      <p class="kpi-value text-[30px] font-extrabold tracking-tight leading-none"><?= $value ?></p>
      <div class="flex items-center justify-between mt-2.5">
        <p class="kpi-sub text-[11px]"><?= $sub ?></p>
        <?php if ($tval !== '—'): ?>
          <span class="<?= $tup === true ? 'badge-up' : ($tup === false ? 'badge-down' : 'badge-flat') ?>">
            <?= $tup === true ? '↑' : ($tup === false ? '↓' : '') ?> <?= $tval ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Gráfico -->
  <div class="card fu fu6 px-5 py-5">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
      <p class="text-[13px] font-semibold text-white">Atividade — <?= $periodLabels[$period] ?></p>
      <div class="flex items-center gap-5">
        <span class="inline-flex items-center gap-2 text-[11px] text-slate-500 font-medium">
          <span class="inline-block w-8 h-0.5 rounded" style="background:#3b82f6"></span>Visitantes
        </span>
        <span class="inline-flex items-center gap-2 text-[11px] text-slate-500 font-medium">
          <span class="inline-block w-8 h-0.5 rounded" style="background:#f59e0b"></span>Cliques
        </span>
      </div>
    </div>
    <canvas id="chart" height="80"></canvas>
  </div>

  <!-- Heatmap -->
  <div class="card fu fu7 px-5 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <p class="text-[13px] font-semibold text-white">Mapa de calor — horário × dia da semana</p>
      <div class="flex items-center gap-2">
        <span class="text-[10px] text-slate-700">Menos</span>
        <div class="flex gap-0.5">
          <?php foreach ([0, .15, .30, .50, .70, 1.0] as $a): ?>
          <div class="w-4 h-3 rounded-sm" style="background:<?= $a === 0 ? 'rgba(255,255,255,0.04)' : 'rgba(59,130,246,'.$a.')' ?>"></div>
          <?php endforeach; ?>
        </div>
        <span class="text-[10px] text-slate-700">Mais</span>
      </div>
    </div>
    <div class="overflow-x-auto">
      <div style="min-width:580px">
        <div class="flex mb-1 pl-9">
          <?php for ($h = 0; $h < 24; $h++): ?>
          <div class="flex-1 text-center text-[9px] text-slate-700"><?= $h % 3 === 0 ? str_pad($h,2,'0',STR_PAD_LEFT) : '' ?></div>
          <?php endfor; ?>
        </div>
        <?php
          $dow_labels = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
          for ($d = 1; $d <= 7; $d++):
        ?>
        <div class="flex items-center gap-0.5 mb-0.5">
          <span class="w-8 shrink-0 text-[10px] text-slate-700 text-right pr-1.5"><?= $dow_labels[$d-1] ?></span>
          <?php for ($h = 0; $h < 24; $h++):
            $val = $heatmap[$d][$h];
            $pct = $heatmap_max > 0 ? $val / $heatmap_max : 0;
            $bg  = $val === 0
              ? 'rgba(255,255,255,0.04)'
              : 'rgba(59,130,246,'.number_format(0.12 + $pct * 0.88, 2).')';
          ?>
          <div class="heat-cell flex-1" style="background:<?= $bg ?>"
            title="<?= $dow_labels[$d-1] ?> <?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00 — <?= $val ?> visits"></div>
          <?php endfor; ?>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <!-- Dispositivos + Tráfego + Browsers -->
  <div class="grid gap-4 lg:grid-cols-3">

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Dispositivos</p>
      <?php
        $devColors = ['mobile'=>'#3b82f6','desktop'=>'#10b981','tablet'=>'#f59e0b'];
        $devIcons  = [
          'mobile'  => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
          'desktop' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
          'tablet'  => 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        ];
        foreach ($devices as $dv):
          $dName = $dv['device'] ?: 'desktop';
          $dPct  = round($dv['total'] / $dev_total * 100);
          $dCol  = $devColors[$dName] ?? '#475569';
          $dPath = $devIcons[$dName]  ?? $devIcons['desktop'];
      ?>
      <div class="mb-3.5 last:mb-0">
        <div class="flex items-center justify-between mb-1.5">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="<?= $dCol ?>" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="<?= $dPath ?>"/>
            </svg>
            <span class="text-[13px] text-slate-300 capitalize"><?= $dName ?></span>
          </div>
          <span class="text-[12px] text-white font-medium"><?= number_format($dv['total']) ?><span class="text-slate-700 font-normal ml-1"><?= $dPct ?>%</span></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $dPct ?>%;background:<?= $dCol ?>"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($devices)): ?><p class="text-[12px] text-slate-700">Sem dados ainda.</p><?php endif; ?>
    </div>

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Origem do tráfego</p>
      <?php
        $trColors = ['Redes Sociais'=>'#3b82f6','Buscadores'=>'#10b981','Direto'=>'#8b5cf6','Outros'=>'#475569'];
        foreach ($traffic as $tr):
          $pct = round($tr['total'] / $tr_total * 100);
          $col = $trColors[$tr['source']] ?? '#475569';
      ?>
      <div class="mb-3.5 last:mb-0">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[13px] text-slate-300"><?= $tr['source'] ?></span>
          <span class="text-[12px] text-white font-medium"><?= number_format($tr['total']) ?><span class="text-slate-700 font-normal ml-1"><?= $pct ?>%</span></span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($traffic)): ?><p class="text-[12px] text-slate-700">Sem dados ainda.</p><?php endif; ?>
    </div>

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Navegadores</p>
      <div class="space-y-3.5">
        <?php foreach ($browsers as $br):
          $pct = round($br['total'] / $br_total * 100);
        ?>
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[13px] text-slate-300"><?= htmlspecialchars($br['browser']) ?></span>
            <span class="text-[12px] text-white font-medium"><?= number_format($br['total']) ?><span class="text-slate-700 font-normal ml-1"><?= $pct ?>%</span></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#8b5cf6"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($browsers)): ?><p class="text-[12px] text-slate-700">Sem dados ainda.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- OS + Países + Cidades -->
  <div class="grid gap-4 lg:grid-cols-3">

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Sistemas Operacionais</p>
      <div class="space-y-3.5">
        <?php foreach ($os_rows as $os):
          $pct = round($os['total'] / $os_total * 100);
        ?>
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[13px] text-slate-300"><?= htmlspecialchars($os['os']) ?></span>
            <span class="text-[12px] text-white font-medium"><?= number_format($os['total']) ?><span class="text-slate-700 font-normal ml-1"><?= $pct ?>%</span></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#f59e0b"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($os_rows)): ?><p class="text-[12px] text-slate-700">Sem dados ainda.</p><?php endif; ?>
      </div>
    </div>

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Acessos por país</p>
      <div class="space-y-2.5">
        <?php foreach ($countries as $i => $c):
          $maxC = (int)($countries[0]['visitors'] ?? 1);
          $pct  = round($c['visitors'] / max(1,$maxC) * 100);
        ?>
        <div>
          <div class="flex items-center justify-between mb-1">
            <div class="flex items-center gap-2 min-w-0">
              <span class="text-[10px] text-slate-700 shrink-0"><?= $i+1 ?></span>
              <span class="text-[13px] text-slate-300 truncate"><?= htmlspecialchars($c['country']) ?></span>
            </div>
            <div class="text-right shrink-0 ml-2">
              <span class="text-[12px] text-white font-medium"><?= $c['visitors'] ?></span>
              <span class="text-[11px] text-slate-700 ml-1"><?= $c['views'] ?> views</span>
            </div>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#3b82f6"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($countries)): ?><p class="text-[12px] text-slate-700">Sem dados ainda.</p><?php endif; ?>
      </div>
    </div>

    <div class="card px-5 py-5">
      <p class="text-[13px] font-semibold text-white mb-4">Acessos por cidade</p>
      <div class="space-y-2.5">
        <?php foreach ($cities as $i => $c): ?>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 min-w-0">
            <span class="text-[10px] text-slate-700 shrink-0"><?= $i+1 ?></span>
            <div class="min-w-0">
              <p class="text-[13px] text-slate-300 truncate"><?= htmlspecialchars($c['city']) ?></p>
              <p class="text-[10px] text-slate-700"><?= htmlspecialchars($c['country']) ?></p>
            </div>
          </div>
          <span class="text-[12px] text-white font-medium shrink-0 ml-2"><?= $c['visitors'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cities)): ?><p class="text-[12px] text-slate-700">Sem dados de cidade ainda.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top cliques -->
  <?php if (!empty($top_clicks)): ?>
  <div class="card px-5 py-5">
    <p class="text-[13px] font-semibold text-white mb-4">Elementos mais clicados</p>
    <div class="space-y-3">
      <?php
        $maxClicks = (int)($top_clicks[0]['total'] ?? 1);
        foreach ($top_clicks as $i => $ck):
          $pct = round($ck['total'] / $maxClicks * 100);
      ?>
      <div class="flex items-center gap-3">
        <span class="text-[11px] text-slate-700 w-4 shrink-0 text-right"><?= $i+1 ?></span>
        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold shrink-0"
          style="background:rgba(245,158,11,.1);color:#f59e0b"><?= htmlspecialchars($ck['element_type']) ?></span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between mb-1">
            <p class="text-[12px] text-slate-300 truncate"><?= htmlspecialchars($ck['element_text'] ?: '—') ?></p>
            <span class="text-[13px] font-bold text-white shrink-0 ml-2"><?= $ck['total'] ?></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#2F80ED"></div></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>

<script>
(function () {
  const labels = <?= json_encode($chartLabels) ?>;
  const views  = <?= json_encode($chartData)   ?>;
  const clicks = <?= json_encode($chartClicks) ?>;
  if (!labels.length) return;

  const ctx = document.getElementById('chart').getContext('2d');

  const gradBlue = ctx.createLinearGradient(0, 0, 0, 280);
  gradBlue.addColorStop(0, 'rgba(59,130,246,.20)');
  gradBlue.addColorStop(1, 'rgba(59,130,246,0)');

  const gradAmber = ctx.createLinearGradient(0, 0, 0, 280);
  gradAmber.addColorStop(0, 'rgba(245,158,11,.14)');
  gradAmber.addColorStop(1, 'rgba(245,158,11,0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Visitantes',
          data: views,
          borderColor: '#3b82f6',
          backgroundColor: gradBlue,
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#3b82f6',
          pointRadius: labels.length > 20 ? 0 : 3,
          pointHoverRadius: 5,
        },
        {
          label: 'Cliques',
          data: clicks,
          borderColor: '#f59e0b',
          backgroundColor: gradAmber,
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#f59e0b',
          pointRadius: labels.length > 20 ? 0 : 3,
          pointHoverRadius: 5,
        }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0c1428',
          borderColor: 'rgba(255,255,255,.08)',
          borderWidth: 1,
          titleColor: '#64748b',
          bodyColor: '#f1f5f9',
          padding: 10,
          cornerRadius: 10,
        }
      },
      scales: {
        x: {
          grid:   { color: 'rgba(255,255,255,.03)' },
          ticks:  { color: '#334155', font: { size: 11 } },
          border: { color: 'rgba(255,255,255,.04)' },
        },
        y: {
          grid:       { color: 'rgba(255,255,255,.03)' },
          ticks:      { color: '#334155', font: { size: 11 }, stepSize: 1 },
          border:     { color: 'rgba(255,255,255,.04)' },
          beginAtZero: true,
        }
      }
    }
  });
})();
</script>
</body>
</html>
