<?php
/**
 * LinkBio — Diagnóstico do tracker / API
 * Acesso: apenas root. Testa se a API recebe dados e se o banco grava.
 */
require_once __DIR__ . '/includes/auth.php';
$user = require_root();
$pdo = db();

header('Content-Type: text/html; charset=utf-8');

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'linkbio.api.br');
$apiUrl  = $baseUrl . '/admin/api/track.php';
$trackerUrl = $baseUrl . '/tracker.js';

// Últimos registros
$lastViews = $pdo->query('SELECT id, page_slug, device, created_at FROM page_views ORDER BY id DESC LIMIT 10')->fetchAll();
$totalViews = (int) $pdo->query('SELECT COUNT(*) FROM page_views')->fetchColumn();
$totalClicks = (int) $pdo->query('SELECT COUNT(*) FROM click_events')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Diagnóstico do tracker — LinkBio</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{font-family:Inter,sans-serif;background:#0f172a;color:#e2e8f0}</style>
</head>
<body class="p-6 max-w-2xl mx-auto">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-white">Diagnóstico do tracker</h1>
    <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white">← Painel</a>
  </div>

  <!-- Resumo -->
  <div class="rounded-xl border border-slate-600 bg-slate-800/50 p-4 mb-4">
    <p class="text-slate-300 text-sm mb-2">Total no banco: <strong class="text-white"><?= $totalViews ?></strong> visitas · <strong class="text-white"><?= $totalClicks ?></strong> cliques.</p>
    <?php if ($totalViews === 0): ?>
    <p class="text-amber-400 text-sm">Nenhuma visita registrada ainda. Use o teste abaixo ou confira os passos.</p>
    <?php endif; ?>
  </div>

  <!-- Testar API (envia uma visita de teste) -->
  <div class="rounded-xl border border-slate-600 bg-slate-800/50 p-4 mb-4">
    <p class="font-semibold text-slate-200 mb-3">Testar API agora</p>
    <p class="text-slate-400 text-sm mb-3">Envia uma requisição de página vista para a API. Se der certo, um novo registro aparece em "Últimas visitas" e no dashboard.</p>
    <button id="btn-test" class="rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium px-4 py-2 text-sm">
      Enviar visita de teste
    </button>
    <pre id="test-result" class="mt-3 p-3 rounded-lg bg-black/40 text-xs text-slate-300 overflow-auto hidden"></pre>
  </div>

  <!-- Últimas visitas -->
  <div class="rounded-xl border border-slate-600 bg-slate-800/50 p-4 mb-4">
    <p class="font-semibold text-slate-200 mb-3">Últimas 10 visitas no banco</p>
    <?php if (empty($lastViews)): ?>
    <p class="text-slate-500 text-sm">Nenhum registro.</p>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-slate-500 border-b border-slate-600"><th class="pb-2">ID</th><th class="pb-2">Página</th><th class="pb-2">Dispositivo</th><th class="pb-2">Data</th></tr></thead>
      <tbody>
      <?php foreach ($lastViews as $r): ?>
        <tr class="border-b border-slate-700/50">
          <td class="py-1.5"><?= (int)$r['id'] ?></td>
          <td class="py-1.5"><?= htmlspecialchars($r['page_slug']) ?></td>
          <td class="py-1.5"><?= htmlspecialchars($r['device'] ?? '—') ?></td>
          <td class="py-1.5 text-slate-400"><?= htmlspecialchars($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Checklist -->
  <div class="rounded-xl border border-slate-600 bg-slate-800/50 p-4">
    <p class="font-semibold text-slate-200 mb-3">Se os dados continuam vazios, confira:</p>
    <ul class="text-sm text-slate-400 space-y-2 list-disc list-inside">
      <li><strong class="text-slate-300">tracker.js na raiz:</strong> O arquivo deve estar em <code class="bg-black/40 px-1 rounded text-slate-300"><?= htmlspecialchars($trackerUrl) ?></code> e abrir no navegador (código JS).</li>
      <li><strong class="text-slate-300">Script nas páginas:</strong> Todas as páginas (principal, paty, marcosblea) devem ter no final do <code class="bg-black/40 px-1 rounded">&lt;body&gt;</code>: <code class="bg-black/40 px-1 rounded text-slate-300">&lt;script src="<?= htmlspecialchars($trackerUrl) ?>" data-slug="linkbio"&gt;&lt;/script&gt;</code> (troque <code>linkbio</code> por <code>paty</code> ou <code>marcosblea</code> em cada site).</li>
      <li><strong class="text-slate-300">Cache:</strong> Limpe o cache do navegador ou teste em aba anônima ao visitar as páginas.</li>
      <li><strong class="text-slate-300">Console do navegador:</strong> Abra F12 → Aba Rede (Network). Recarregue a página e veja se aparece uma requisição POST para <code class="bg-black/40 px-1 rounded text-slate-300">track.php</code>. Status 200 = enviado; 404 = URL errada; 500 = erro no servidor.</li>
      <li><strong class="text-slate-300">Subdomínios (paty, marcosblea):</strong> Na hospedagem, a pasta de cada subdomínio (ex.: <code class="bg-black/40 px-1 rounded">paty</code>, <code class="bg-black/40 px-1 rounded">marcosblea</code>) deve ter o <code class="bg-black/40 px-1 rounded">index.html</code> atualizado com <code class="text-slate-300">&lt;script src="<?= htmlspecialchars($trackerUrl) ?>" data-slug="paty"&gt;&lt;/script&gt;</code> (ou <code>marcosblea</code>). Suba de novo os arquivos de <code>dist/paty/</code> e <code>dist/marcosblea/</code> para as pastas dos subdomínios.</li>
    </ul>
  </div>

  <script>
    document.getElementById('btn-test').addEventListener('click', function () {
      var btn = this;
      var out = document.getElementById('test-result');
      btn.disabled = true;
      out.classList.remove('hidden');
      out.textContent = 'Enviando...';

      var payload = {
        type: 'pageview',
        slug: 'linkbio',
        referrer: '',
        browser: 'Chrome',
        os: 'Windows 10'
      };

      fetch('<?= addslashes($apiUrl) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) {
          return r.text().then(function (text) {
            out.textContent = 'Status: ' + r.status + ' ' + r.statusText + '\nResposta: ' + text;
            if (r.ok) out.textContent += '\n\nSucesso! Atualize a página para ver o novo registro em "Últimas visitas".';
          });
        })
        .catch(function (err) {
          out.textContent = 'Erro: ' + err.message;
        })
        .then(function () {
          btn.disabled = false;
        });
    });
  </script>

</body>
</html>
