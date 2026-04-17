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
  <style>body { background: #080c18; }</style>
</head>
<body class="text-slate-100 font-sans antialiased min-h-screen">
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <a href="/admin/dashboard.php?page=<?= urlencode($slug) ?>" class="text-sm text-slate-500 hover:text-white">← Painel</a>
        <h1 class="text-2xl font-bold mt-2">Planeje seu espaço — inscrições</h1>
        <p class="text-slate-500 text-sm mt-1"><?= h($slug) ?>.linkbio.api.br</p>
      </div>
      <a href="/admin/planeje_export.php?page=<?= urlencode($slug) ?>"
        class="inline-flex items-center gap-2 rounded-xl bg-[#2F80ED] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#2569c4] transition">
        Exportar Excel (.xlsx)
      </a>
    </div>

    <!-- Dashboard resumo -->
    <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.04] p-5 sm:p-6" aria-labelledby="dash-title">
      <h2 id="dash-title" class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Resumo das respostas</h2>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="rounded-xl bg-[#0c1224] border border-white/10 px-4 py-3">
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Total</p>
          <p class="text-2xl font-bold text-white tabular-nums"><?= $totalCount ?></p>
        </div>
        <div class="rounded-xl bg-[#0c1224] border border-white/10 px-4 py-3">
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Últimas 24 h</p>
          <p class="text-2xl font-bold text-[#7eb8f7] tabular-nums"><?= $count24h ?></p>
        </div>
        <div class="rounded-xl bg-[#0c1224] border border-white/10 px-4 py-3">
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Últimos 7 dias</p>
          <p class="text-2xl font-bold text-slate-200 tabular-nums"><?= $count7d ?></p>
        </div>
        <div class="rounded-xl bg-[#0c1224] border border-white/10 px-4 py-3">
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Últimos 30 dias</p>
          <p class="text-2xl font-bold text-slate-200 tabular-nums"><?= $count30d ?></p>
        </div>
      </div>

      <?php if ($totalCount > 0): ?>
      <div class="flex flex-wrap gap-6 text-sm text-slate-400 mb-6 pb-6 border-b border-white/10">
        <div><span class="text-slate-600">Primeira:</span> <?= h((string) $range['first_at']) ?></div>
        <div><span class="text-slate-600">Mais recente:</span> <?= h((string) $range['last_at']) ?></div>
        <?php if ($avgBaias !== null || $avgColab !== null): ?>
        <div>
          <span class="text-slate-600">Médias</span>
          <?php if ($avgBaias !== null): ?> · Baias <?= h((string) $avgBaias) ?><?php endif; ?>
          <?php if ($avgColab !== null): ?> · Colaboradores <?= h((string) $avgColab) ?><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($totalCount === 0): ?>
      <p class="text-slate-500 text-sm">Quando houver inscrições, aqui aparecem totais, períodos e distribuições.</p>
      <?php else: ?>
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Por onde veio</h3>
          <ul class="space-y-2.5">
            <?php foreach ($byOrigem as $o): ?>
            <?php $pct = bar_pct((int) $o['c'], $maxOrigem); ?>
            <li>
              <div class="flex justify-between text-xs mb-0.5 gap-2">
                <span class="text-slate-300 truncate" title="<?= h($o['canal']) ?>"><?= h($o['canal']) ?></span>
                <span class="text-slate-500 shrink-0 tabular-nums"><?= (int) $o['c'] ?></span>
              </div>
              <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full bg-[#2F80ED]" style="width:<?= $pct ?>%"></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="space-y-6">
          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Identidade visual</h3>
            <ul class="space-y-2">
              <?php foreach ($byIdentidade as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxIdent)); ?>
              <li>
                <div class="flex justify-between text-xs mb-0.5">
                  <span class="text-slate-300"><?= h($r['label']) ?></span>
                  <span class="text-slate-500 tabular-nums"><?= (int) $r['c'] ?></span>
                </div>
                <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                  <div class="h-full rounded-full bg-emerald-500/80" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Limpeza periódica</h3>
            <ul class="space-y-2">
              <?php foreach ($byLimpeza as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxLimp)); ?>
              <li>
                <div class="flex justify-between text-xs mb-0.5">
                  <span class="text-slate-300"><?= h($r['label']) ?></span>
                  <span class="text-slate-500 tabular-nums"><?= (int) $r['c'] ?></span>
                </div>
                <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                  <div class="h-full rounded-full bg-amber-500/80" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Banheiro (sanitário + chuveiro)</h3>
            <ul class="space-y-2">
              <?php foreach ($byBanheiro as $r): ?>
              <?php $pct = bar_pct((int) $r['c'], max(1, $maxBan)); ?>
              <li>
                <div class="flex justify-between text-xs mb-0.5">
                  <span class="text-slate-300"><?= h($r['label']) ?></span>
                  <span class="text-slate-500 tabular-nums"><?= (int) $r['c'] ?></span>
                </div>
                <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                  <div class="h-full rounded-full bg-violet-500/75" style="width:<?= $pct ?>%"></div>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <div class="rounded-2xl border border-white/10 bg-white/[0.04] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-slate-400 border-b border-white/10">
            <tr>
              <th class="px-4 py-3 font-medium">Data</th>
              <th class="px-4 py-3 font-medium">Nome</th>
              <th class="px-4 py-3 font-medium">E-mail</th>
              <th class="px-4 py-3 font-medium">Telefone</th>
              <th class="px-4 py-3 font-medium">Por onde veio</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <?php if (!$list): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma inscrição ainda.</td></tr>
            <?php else: foreach ($list as $r): ?>
            <tr class="hover:bg-white/[0.03]">
              <td class="px-4 py-3 text-slate-400 whitespace-nowrap"><?= h($r['created_at']) ?></td>
              <td class="px-4 py-3"><?= h($r['nome']) ?></td>
              <td class="px-4 py-3"><?= h($r['email']) ?></td>
              <td class="px-4 py-3 whitespace-nowrap"><?= h($r['telefone']) ?></td>
              <td class="px-4 py-3 max-w-[220px] truncate" title="<?= h($r['origem']) ?>"><?= h($r['origem']) ?></td>
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
