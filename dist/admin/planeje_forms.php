<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_auth();
$isRoot = $user['role'] === 'root';
$pdo = db();

$slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['page'] ?? ''));
if ($slug !== 'cristianoladeira') {
    http_response_code(404);
    echo 'Página não encontrada.';
    exit;
}
if (!$isRoot && ($user['page_slug'] ?? '') !== 'cristianoladeira') {
    http_response_code(403);
    echo 'Sem permissão.';
    exit;
}

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_submission') {
    $deleteId = (int) ($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $del = $pdo->prepare('DELETE FROM planeje_submissions WHERE id = ? AND page_slug = ? LIMIT 1');
        $del->execute([$deleteId, $slug]);
        if ($del->rowCount() > 0) {
            $flash = ['type' => 'success', 'message' => 'Registro excluído com sucesso.'];
        } else {
            $flash = ['type' => 'error', 'message' => 'Registro não encontrado ou sem permissão para excluir.'];
        }
    } else {
        $flash = ['type' => 'error', 'message' => 'ID inválido para exclusão.'];
    }
}

$rows = $pdo->prepare(
    'SELECT id, nome, email, telefone, origem, created_at
     FROM planeje_submissions
     WHERE page_slug = ?
     ORDER BY created_at DESC
     LIMIT 500'
);
$rows->execute([$slug]);
$list = $rows->fetchAll();

// ── Resumo (dashboard) ─────────────────────────────────────
$st = $pdo->prepare('SELECT COUNT(*) FROM planeje_submissions WHERE page_slug = ?');
$st->execute([$slug]);
$totalCount = (int) $st->fetchColumn();

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM planeje_submissions WHERE page_slug = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
);
$st->execute([$slug]);
$count24h = (int) $st->fetchColumn();

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM planeje_submissions WHERE page_slug = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
);
$st->execute([$slug]);
$count7d = (int) $st->fetchColumn();

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM planeje_submissions WHERE page_slug = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
);
$st->execute([$slug]);
$count30d = (int) $st->fetchColumn();

$st = $pdo->prepare(
    'SELECT MIN(created_at) AS first_at, MAX(created_at) AS last_at FROM planeje_submissions WHERE page_slug = ?'
);
$st->execute([$slug]);
$range = $st->fetch() ?: ['first_at' => null, 'last_at' => null];

$st = $pdo->prepare(
    'SELECT COALESCE(NULLIF(TRIM(origem), \'\'), "(não informado)") AS canal, COUNT(*) AS c
     FROM planeje_submissions WHERE page_slug = ?
     GROUP BY canal ORDER BY c DESC LIMIT 12'
);
$st->execute([$slug]);
$byOrigem = $st->fetchAll();

$jsonField = static function (PDO $pdo, string $slug, string $path): array {
    $sql = 'SELECT COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload_json, ?))), \'\'), "(vazio)") AS label, COUNT(*) AS c
            FROM planeje_submissions WHERE page_slug = ?
            GROUP BY 1 ORDER BY c DESC';
    $st = $pdo->prepare($sql);
    $st->execute(['$.' . $path, $slug]);
    return $st->fetchAll();
};

$byIdentidade = $jsonField($pdo, $slug, 'identidade');
$byLimpeza = $jsonField($pdo, $slug, 'limpeza');
$byBanheiro = $jsonField($pdo, $slug, 'banheiro');

$st = $pdo->prepare(
    'SELECT AVG(CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.baias\'))), \'\') AS UNSIGNED)) AS avg_b,
            AVG(CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.colaboradores\'))), \'\') AS UNSIGNED)) AS avg_c
     FROM planeje_submissions WHERE page_slug = ?'
);
$st->execute([$slug]);
$avgRow = $st->fetch() ?: [];
$avgBaias = isset($avgRow['avg_b']) && $avgRow['avg_b'] !== null ? round((float) $avgRow['avg_b'], 1) : null;
$avgColab = isset($avgRow['avg_c']) && $avgRow['avg_c'] !== null ? round((float) $avgRow['avg_c'], 1) : null;

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function phone_br(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }

    return trim((string) $phone);
}

/** Barra horizontal proporcional (Tailwind-free width %) */
function bar_pct(int $value, int $max): float
{
    if ($max <= 0) {
        return 0.0;
    }

    return min(100.0, round($value / $max * 100, 1));
}

$maxOrigem = 0;
foreach ($byOrigem as $o) {
    $maxOrigem = max($maxOrigem, (int) $o['c']);
}

function h_max(array $rows): int
{
    $m = 0;
    foreach ($rows as $r) {
        $m = max($m, (int) $r['c']);
    }

    return $m;
}

$maxIdent = h_max($byIdentidade);
$maxLimp = h_max($byLimpeza);
$maxBan = h_max($byBanheiro);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Planeje seu espaço — Inscrições</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background: #ffffff; color: #0f172a; }
    .panel-card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 16px 45px rgba(15, 23, 42, 0.05); }
    .soft-text { color: #64748b; }
    .line-soft { border-color: #e2e8f0; }
    .metric-card { border: 1px solid #dbeafe; border-radius: 1rem; padding: 1rem; background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%); min-height: 92px; }
    .metric-label { color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .metric-value { color: #0f172a; font-size: 1.7rem; font-weight: 800; line-height: 1.1; margin-top: .35rem; }
    .meta-pill { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: .85rem; padding: .7rem .9rem; }
    .meta-label { display: block; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; margin-bottom: .18rem; }
    .meta-value { color: #0f172a; font-size: 13px; font-weight: 600; }
    .chart-card { border: 1px solid #e2e8f0; border-radius: 1rem; background: #ffffff; padding: 1rem; }
    .chart-title { color: #475569; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; margin-bottom: .85rem; }
    .bar-bg { background: #e2e8f0; height: .55rem; border-radius: 999px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 999px; min-width: 7px; }
    .bar-label { color: #334155; font-size: 12px; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bar-count { color: #64748b; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; flex-shrink: 0; }
    .export-btn { color: #ffffff !important; }
    .table-head { color: #475569; border-color: #e2e8f0; }
    .table-row { border-color: #f1f5f9; }
    .table-row:hover { background: #f8fafc; }
    .submissions-table { min-width: 1080px; table-layout: fixed; }
    .submissions-table th { font-size: 12px; font-weight: 700; color: #475569; background: #f8fafc; }
    .submissions-table td { color: #334155; vertical-align: middle; }
    .cell-ellipsis { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .origin-badge { display: inline-flex; align-items: center; max-width: 100%; border-radius: 999px; border: 1px solid #dbeafe; background: #eff6ff; color: #1d4ed8; padding: .32rem .62rem; font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .delete-btn { color: #be123c !important; border-color: #fecdd3; background: #fff1f2; }
    .delete-btn:hover { background: #ffe4e6; color: #9f1239 !important; }
    .text-slate-600 { color: #475569 !important; }
    .text-slate-500 { color: #64748b !important; }
    .text-slate-400 { color: #64748b !important; }
    .text-slate-300 { color: #334155 !important; }
    .text-slate-200 { color: #0f172a !important; }
    .text-white { color: #0f172a !important; }
  </style>
</head>
<body class="font-sans antialiased min-h-screen">
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <a href="/admin/dashboard.php?page=<?= urlencode($slug) ?>" class="text-sm soft-text hover:text-slate-900">← Painel</a>
        <h1 class="text-2xl font-bold mt-2">Planeje seu espaço — inscrições</h1>
        <p class="soft-text text-sm mt-1"><?= h($slug) ?>.linkbio.api.br</p>
      </div>
      <a href="/admin/planeje_export.php?page=<?= urlencode($slug) ?>"
        class="export-btn inline-flex items-center gap-2 rounded-xl bg-[#2F80ED] px-4 py-2.5 text-sm font-semibold hover:bg-[#2569c4] transition">
        Exportar Excel (.xlsx)
      </a>
    </div>

    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success'
        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'
        : 'border-red-500/40 bg-red-500/10 text-red-300' ?>">
      <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Dashboard resumo -->
    <section class="panel-card mb-8 rounded-2xl overflow-hidden" aria-labelledby="dash-title">
      <div class="border-b line-soft px-5 sm:px-6 py-5">
        <p class="text-[11px] font-bold uppercase tracking-[.18em] text-[#2F80ED] mb-1">Planeje seu espaço</p>
        <h2 id="dash-title" class="text-lg sm:text-xl font-bold text-slate-900">Resumo das respostas</h2>
        <p class="soft-text text-sm mt-1">Visão rápida das inscrições recebidas e das principais preferências marcadas no formulário.</p>
      </div>

      <div class="p-5 sm:p-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <div class="metric-card">
          <p class="metric-label">Total</p>
          <p class="metric-value tabular-nums"><?= $totalCount ?></p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Últimas 24 h</p>
          <p class="metric-value tabular-nums" style="color:#1d4ed8"><?= $count24h ?></p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Últimos 7 dias</p>
          <p class="metric-value tabular-nums"><?= $count7d ?></p>
        </div>
        <div class="metric-card">
          <p class="metric-label">Últimos 30 dias</p>
          <p class="metric-value tabular-nums"><?= $count30d ?></p>
        </div>
      </div>

      <?php if ($totalCount > 0): ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <div class="meta-pill">
          <span class="meta-label">Primeira resposta</span>
          <span class="meta-value"><?= h((string) $range['first_at']) ?></span>
        </div>
        <div class="meta-pill">
          <span class="meta-label">Mais recente</span>
          <span class="meta-value"><?= h((string) $range['last_at']) ?></span>
        </div>
        <?php if ($avgBaias !== null || $avgColab !== null): ?>
        <div class="meta-pill">
          <span class="meta-label">Médias</span>
          <span class="meta-value">
          <?php if ($avgBaias !== null): ?> · Baias <?= h((string) $avgBaias) ?><?php endif; ?>
          <?php if ($avgColab !== null): ?> · Colaboradores <?= h((string) $avgColab) ?><?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($totalCount === 0): ?>
      <p class="soft-text text-sm">Quando houver inscrições, aqui aparecem totais, períodos e distribuições.</p>
      <?php else: ?>
      <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="chart-card">
          <h3 class="chart-title">Por onde veio</h3>
          <ul class="space-y-3">
            <?php foreach ($byOrigem as $o): ?>
            <?php $pct = bar_pct((int) $o['c'], $maxOrigem); ?>
            <li>
              <div class="flex justify-between mb-1.5 gap-3">
                <span class="bar-label" title="<?= h($o['canal']) ?>"><?= h($o['canal']) ?></span>
                <span class="bar-count"><?= (int) $o['c'] ?></span>
              </div>
              <div class="bar-bg">
                <div class="bar-fill bg-[#2F80ED]" style="width:<?= $pct ?>%"></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Identidade visual</h3>
            <ul class="space-y-3">
              <?php foreach ($byIdentidade as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxIdent)); ?>
              <li>
                <div class="flex justify-between mb-1.5 gap-3">
                  <span class="bar-label"><?= h($r['label']) ?></span>
                  <span class="bar-count"><?= (int) $r['c'] ?></span>
                </div>
                <div class="bar-bg">
                  <div class="bar-fill bg-emerald-500/80" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Limpeza periódica</h3>
            <ul class="space-y-3">
              <?php foreach ($byLimpeza as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxLimp)); ?>
              <li>
                <div class="flex justify-between mb-1.5 gap-3">
                  <span class="bar-label"><?= h($r['label']) ?></span>
                  <span class="bar-count"><?= (int) $r['c'] ?></span>
                </div>
                <div class="bar-bg">
                  <div class="bar-fill bg-amber-500/80" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Banheiro e chuveiro</h3>
            <ul class="space-y-3">
              <?php foreach ($byBanheiro as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxBan)); ?>
              <li>
                <div class="flex justify-between mb-1.5 gap-3">
                  <span class="bar-label"><?= h($r['label']) ?></span>
                  <span class="bar-count"><?= (int) $r['c'] ?></span>
                </div>
                <div class="bar-bg">
                  <div class="bar-fill bg-violet-500/75" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
        </div>
      </div>
      <?php endif; ?>
      </div>
    </section>

    <div class="panel-card rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="submissions-table w-full text-sm text-left">
          <colgroup>
            <col style="width:160px"/>
            <col style="width:180px"/>
            <col style="width:310px"/>
            <col style="width:170px"/>
            <col style="width:170px"/>
            <col style="width:90px"/>
          </colgroup>
          <thead class="table-head border-b">
            <tr>
              <th class="px-4 py-3 font-medium whitespace-nowrap">Data</th>
              <th class="px-4 py-3 font-medium">Nome</th>
              <th class="px-4 py-3 font-medium">E-mail</th>
              <th class="px-4 py-3 font-medium whitespace-nowrap text-center">Telefone</th>
              <th class="px-4 py-3 font-medium text-center">Por onde veio</th>
              <th class="px-4 py-3 font-medium text-center">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y table-row">
            <?php if (!$list): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center soft-text">Nenhuma inscrição ainda.</td></tr>
            <?php else: foreach ($list as $r): ?>
            <tr class="table-row">
              <td class="px-4 py-3 soft-text whitespace-nowrap tabular-nums"><?= h($r['created_at']) ?></td>
              <td class="px-4 py-3 font-semibold"><span class="cell-ellipsis" title="<?= h($r['nome']) ?>"><?= h($r['nome']) ?></span></td>
              <td class="px-4 py-3"><span class="cell-ellipsis" title="<?= h($r['email']) ?>"><?= h($r['email']) ?></span></td>
              <td class="px-4 py-3 text-center whitespace-nowrap tabular-nums font-medium"><?= h(phone_br($r['telefone'])) ?></td>
              <td class="px-4 py-3 text-center"><span class="origin-badge" title="<?= h($r['origem']) ?>"><?= h($r['origem']) ?></span></td>
              <td class="px-4 py-3 text-center">
                <form method="POST" class="inline-block" onsubmit="return confirm('Tem certeza que deseja excluir este registro?');">
                  <input type="hidden" name="action" value="delete_submission"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <button type="submit"
                    class="delete-btn inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-bold transition">
                    Excluir
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-slate-600 text-xs mt-4">Últimas 500 linhas na tabela. Exporte o Excel para ver todos os campos e linhas completas.</p>
  </div>
</body>
</html>
