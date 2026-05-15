<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/briefing_upload.php';
require_root();
$pdo = db();

$tableOk = in_array(
    'briefing_submissions',
    array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0),
    true
);

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_submission') {
    $deleteId = (int) ($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $del = $pdo->prepare('DELETE FROM briefing_submissions WHERE id = ? LIMIT 1');
        $del->execute([$deleteId]);
        if ($del->rowCount() > 0) {
            briefing_delete_submission_files($deleteId);
            $flash = ['type' => 'success', 'message' => 'Briefing excluído.'];
        } else {
            $flash = ['type' => 'error', 'message' => 'Registro não encontrado.'];
        }
    }
}

$detailId = (int) ($_GET['id'] ?? 0);
$detail = null;
if ($tableOk && $detailId > 0) {
    $st = $pdo->prepare('SELECT * FROM briefing_submissions WHERE id = ? LIMIT 1');
    $st->execute([$detailId]);
    $detail = $st->fetch() ?: null;
}

$rows = [];
$totalCount = 0;
$count24h = 0;
$count7d = 0;
$count30d = 0;
$range = ['first_at' => null, 'last_at' => null];
$byOrigem = [];

if ($tableOk) {
    $rows = $pdo->query(
        'SELECT id, nome, email, telefone, subdominio_desejado, origem, created_at, payload_json
         FROM briefing_submissions
         ORDER BY created_at DESC
         LIMIT 500'
    )->fetchAll();

    $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM briefing_submissions')->fetchColumn();
    $count24h = (int) $pdo->query(
        'SELECT COUNT(*) FROM briefing_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
    )->fetchColumn();
    $count7d = (int) $pdo->query(
        'SELECT COUNT(*) FROM briefing_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
    )->fetchColumn();
    $count30d = (int) $pdo->query(
        'SELECT COUNT(*) FROM briefing_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    )->fetchColumn();

    $range = $pdo->query(
        'SELECT MIN(created_at) AS first_at, MAX(created_at) AS last_at FROM briefing_submissions'
    )->fetch() ?: ['first_at' => null, 'last_at' => null];

    $byOrigem = $pdo->query(
        'SELECT COALESCE(NULLIF(TRIM(origem), \'\'), "(não informado)") AS canal, COUNT(*) AS c
         FROM briefing_submissions GROUP BY canal ORDER BY c DESC LIMIT 12'
    )->fetchAll();
}

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

function bar_pct(int $value, int $max): float
{
    if ($max <= 0) {
        return 0.0;
    }

    return min(100.0, round($value / $max * 100, 1));
}

function briefing_parse_payload(?string $json): array
{
    $p = json_decode((string) $json, true);

    return is_array($p) ? $p : [];
}

function briefing_format_val(string $key, mixed $val): string
{
    if ($val === null || $val === '') {
        return '';
    }
    if ($key === 'secoes' && is_array($val)) {
        return implode(', ', $val);
    }
    if ($key === 'lgpd_consent') {
        return $val ? 'Sim' : 'Não';
    }

    return is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
}

/** Grupos de campos para exibição no detalhe */
function briefing_field_groups(): array
{
    return [
        'Contato e perfil' => [
            'profissao' => 'Profissão / nicho',
            'cidade' => 'Cidade',
            'estado' => 'Estado',
            'instagram' => 'Instagram',
        ],
        'Textos da página' => [
            'headline' => 'Headline (título principal)',
            'subtitulo' => 'Subtítulo',
            'sobre' => 'Sobre',
            'diferencial' => 'Diferencial',
        ],
        'Serviços e conversão' => [
            'servicos' => 'Serviços',
            'formacao' => 'Formação / credenciais',
            'faq' => 'FAQ',
            'cta_texto' => 'Texto do botão',
            'whatsapp_msg' => 'Mensagem WhatsApp',
            'links_extras' => 'Links extras',
        ],
        'Visual e referências' => [
            'cores' => 'Cores / visual',
            'referencias' => 'Referências',
            'midia_link' => 'Link de mídia (nuvem)',
        ],
        'Outros' => [
            'secoes' => 'Seções desejadas',
            'observacoes' => 'Observações',
            'lgpd_consent' => 'Consentimento LGPD',
        ],
    ];
}

$maxOrigem = 0;
foreach ($byOrigem as $o) {
    $maxOrigem = max($maxOrigem, (int) $o['c']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Briefings — LinkBio</title>
  <link rel="icon" href="/logo/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background: #f8fafc; color: #0f172a; }
    .panel-card { background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(15,23,42,.06); }
    .soft-text { color: #64748b; }
    .metric-card { border: 1px solid #dbeafe; border-radius: 1rem; padding: 1rem; background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%); }
    .metric-label { color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .metric-value { color: #0f172a; font-size: 1.7rem; font-weight: 800; line-height: 1.1; margin-top: .35rem; }
    .bar-bg { background: #e2e8f0; height: .55rem; border-radius: 999px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 999px; min-width: 7px; background: #2F80ED; }
    .section-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; background: #fff; }
    .section-card h3 { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #2F80ED; margin-bottom: 1rem; }
    .field-row { margin-bottom: .85rem; }
    .field-row:last-child { margin-bottom: 0; }
    .field-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
    .field-value { font-size: 14px; color: #334155; white-space: pre-wrap; margin-top: .2rem; line-height: 1.55; }
    .file-card { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #f8fafc; }
    .file-card img { width: 100%; max-height: 140px; object-fit: contain; background: #fff; display: block; }
    .file-card-body { padding: .75rem; }
    .btn-dl { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: #2F80ED; color: #fff; font-size: 12px; font-weight: 700; text-decoration: none; }
    .btn-dl:hover { background: #2569c4; }
    .btn-zip { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; background: #059669; color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; }
    .btn-zip:hover { background: #047857; }
    .delete-btn { color: #be123c !important; border-color: #fecdd3; background: #fff1f2; }
    .subdomain-badge { display: inline-flex; padding: .25rem .6rem; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; }
    .badge-files { display: inline-flex; padding: .2rem .5rem; border-radius: 6px; background: #ecfdf5; color: #047857; font-size: 11px; font-weight: 700; }
    .badge-none { background: #f1f5f9; color: #64748b; }
  </style>
</head>
<body class="font-sans antialiased min-h-screen">
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <a href="/admin/dashboard.php" class="text-sm soft-text hover:text-slate-900">← Painel</a>
        <h1 class="text-2xl font-bold mt-2">Briefings — criar página</h1>
        <p class="soft-text text-sm mt-1">Respostas de <a href="https://criar.linkbio.api.br" target="_blank" class="text-[#2F80ED] hover:underline">criar.linkbio.api.br</a></p>
      </div>
      <?php if (!$detail): ?>
      <a href="/admin/briefing_export.php"
        class="inline-flex items-center gap-2 rounded-xl bg-[#2F80ED] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#2569c4] transition">
        Exportar Excel (.xlsx)
      </a>
      <?php endif; ?>
    </div>

    <?php if (!$tableOk): ?>
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      A tabela <code class="bg-amber-100 px-1 rounded">briefing_submissions</code> ainda não existe.
      Execute o script <code class="bg-amber-100 px-1 rounded">admin/sql/07_briefing_submissions.sql</code> no phpMyAdmin.
    </div>
    <?php endif; ?>

    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-red-200 bg-red-50 text-red-800' ?>">
      <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($detail):
      $p = briefing_parse_payload($detail['payload_json'] ?? '');
      $uploads = is_array($p['uploads'] ?? null) ? $p['uploads'] : [];
      $bid = (int) $detail['id'];
    ?>
    <section class="panel-card rounded-2xl p-6 mb-8">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-6 pb-4 border-b border-slate-200">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-widest text-[#2F80ED] mb-1">Briefing #<?= $bid ?></p>
          <h2 class="text-2xl font-bold"><?= h($detail['nome']) ?></h2>
          <p class="soft-text text-sm mt-1"><?= h($detail['created_at']) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
          <?php if ($uploads !== []): ?>
          <a href="/admin/briefing_files_zip.php?id=<?= $bid ?>" class="btn-zip">↓ Baixar todos os arquivos (ZIP)</a>
          <?php endif; ?>
          <a href="/admin/briefing_forms.php" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Lista</a>
        </div>
      </div>

      <div class="section-card">
        <h3>Dados de contato</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="field-row"><p class="field-label">E-mail</p><p class="field-value"><?= h($detail['email']) ?: '—' ?></p></div>
          <div class="field-row"><p class="field-label">Telefone / WhatsApp</p><p class="field-value"><?= h(phone_br($detail['telefone'])) ?: '—' ?></p></div>
          <div class="field-row"><p class="field-label">Subdomínio desejado</p><p class="field-value"><?php if ($detail['subdominio_desejado']): ?><span class="subdomain-badge"><?= h($detail['subdominio_desejado']) ?>.linkbio.api.br</span><?php else: ?>—<?php endif; ?></p></div>
          <div class="field-row"><p class="field-label">Por onde veio</p><p class="field-value"><?= h($detail['origem']) ?: '—' ?></p></div>
        </div>
      </div>

      <?php if ($uploads !== []): ?>
      <div class="section-card">
        <h3>Arquivos enviados (logo, fotos, etc.)</h3>
        <p class="soft-text text-sm mb-4">Clique para baixar ou visualize imagens abaixo.</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
          <?php foreach ($uploads as $u):
            if (!is_array($u)) continue;
            $stored = (string) ($u['stored'] ?? '');
            $label = (string) ($u['name'] ?? $stored);
            $mime = (string) ($u['mime'] ?? '');
            $size = (int) ($u['size'] ?? 0);
            $sizeKb = $size > 0 ? round($size / 1024, 1) . ' KB' : '';
            $isImg = str_starts_with($mime, 'image/');
            $fileUrl = '/admin/briefing_file.php?id=' . $bid . '&f=' . urlencode($stored);
          ?>
          <div class="file-card">
            <?php if ($isImg): ?>
            <a href="<?= h($fileUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= h($fileUrl . '&inline=1') ?>" alt="<?= h($label) ?>" loading="lazy"/>
            </a>
            <?php endif; ?>
            <div class="file-card-body">
              <p class="text-sm font-semibold text-slate-800 truncate" title="<?= h($label) ?>"><?= h($label) ?></p>
              <?php if ($sizeKb): ?><p class="text-xs soft-text mt-0.5"><?= h($sizeKb) ?></p><?php endif; ?>
              <a href="<?= h($fileUrl) ?>" class="btn-dl mt-2">Baixar arquivo</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php foreach (briefing_field_groups() as $groupTitle => $fields):
        $hasAny = false;
        foreach ($fields as $key => $label) {
            if (briefing_format_val($key, $p[$key] ?? null) !== '') {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) continue;
      ?>
      <div class="section-card">
        <h3><?= h($groupTitle) ?></h3>
        <?php foreach ($fields as $key => $label):
          $val = briefing_format_val($key, $p[$key] ?? null);
          if ($val === '') continue;
        ?>
        <div class="field-row">
          <p class="field-label"><?= h($label) ?></p>
          <p class="field-value"><?= h($val) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

      <form method="POST" class="mt-6 pt-6 border-t border-slate-200" onsubmit="return confirm('Excluir este briefing e todos os arquivos?');">
        <input type="hidden" name="action" value="delete_submission"/>
        <input type="hidden" name="id" value="<?= $bid ?>"/>
        <button type="submit" class="delete-btn inline-flex rounded-lg border px-4 py-2 text-sm font-bold">Excluir briefing</button>
      </form>
    </section>

    <?php else: ?>

    <section class="panel-card rounded-2xl p-5 sm:p-6 mb-8">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="metric-card"><span class="metric-label">Total</span><div class="metric-value"><?= $totalCount ?></div></div>
        <div class="metric-card"><span class="metric-label">24h</span><div class="metric-value"><?= $count24h ?></div></div>
        <div class="metric-card"><span class="metric-label">7 dias</span><div class="metric-value"><?= $count7d ?></div></div>
        <div class="metric-card"><span class="metric-label">30 dias</span><div class="metric-value"><?= $count30d ?></div></div>
      </div>
      <?php if ($range['first_at']): ?>
      <p class="soft-text text-xs mb-4">Primeiro: <?= h($range['first_at']) ?> · Último: <?= h($range['last_at']) ?></p>
      <?php endif; ?>

      <?php if ($totalCount > 0 && $byOrigem !== []): ?>
      <div class="rounded-xl border border-slate-200 p-4 max-w-md">
        <p class="text-[11px] font-bold uppercase text-slate-500 mb-3">Por onde veio</p>
        <ul class="space-y-2">
          <?php foreach ($byOrigem as $o): ?>
          <li>
            <div class="flex justify-between text-sm mb-1"><span><?= h($o['canal']) ?></span><span class="font-bold"><?= (int) $o['c'] ?></span></div>
            <div class="bar-bg"><div class="bar-fill" style="width:<?= bar_pct((int) $o['c'], $maxOrigem) ?>%"></div></div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </section>

    <div class="panel-card rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
        <p class="text-sm font-semibold text-slate-700">Clique em <strong>Ver briefing</strong> para ler todos os dados e baixar logos/arquivos.</p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left min-w-[960px]">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-4 py-3 font-semibold text-slate-600">Data</th>
              <th class="px-4 py-3 font-semibold text-slate-600">Nome</th>
              <th class="px-4 py-3 font-semibold text-slate-600">Profissão</th>
              <th class="px-4 py-3 font-semibold text-slate-600">Subdomínio</th>
              <th class="px-4 py-3 font-semibold text-slate-600">Contato</th>
              <th class="px-4 py-3 font-semibold text-slate-600 text-center">Arquivos</th>
              <th class="px-4 py-3 font-semibold text-slate-600 text-center">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center soft-text">Nenhum briefing ainda.</td></tr>
            <?php else: foreach ($rows as $r):
              $rp = briefing_parse_payload($r['payload_json'] ?? '');
              $fileCount = is_array($rp['uploads'] ?? null) ? count($rp['uploads']) : 0;
              $prof = (string) ($rp['profissao'] ?? '');
            ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 soft-text whitespace-nowrap tabular-nums text-xs"><?= h($r['created_at']) ?></td>
              <td class="px-4 py-3 font-semibold"><?= h($r['nome']) ?></td>
              <td class="px-4 py-3 soft-text text-xs max-w-[140px] truncate" title="<?= h($prof) ?>"><?= h($prof) ?: '—' ?></td>
              <td class="px-4 py-3">
                <?php if ($r['subdominio_desejado']): ?>
                <span class="subdomain-badge"><?= h($r['subdominio_desejado']) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="px-4 py-3 soft-text text-xs">
                <?= h($r['email']) ?><br/><?= h(phone_br($r['telefone'])) ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if ($fileCount > 0): ?>
                <span class="badge-files"><?= $fileCount ?> arquivo<?= $fileCount > 1 ? 's' : '' ?></span>
                <?php else: ?>
                <span class="badge-files badge-none">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-center whitespace-nowrap">
                <a href="/admin/briefing_forms.php?id=<?= (int) $r['id'] ?>" class="inline-flex items-center rounded-lg bg-[#2F80ED] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#2569c4] mr-2">Ver briefing</a>
                <form method="POST" class="inline" onsubmit="return confirm('Excluir?');">
                  <input type="hidden" name="action" value="delete_submission"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <button type="submit" class="delete-btn rounded border px-2 py-1 text-xs font-bold">Excluir</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-slate-500 text-xs mt-4">Últimos 500 registros. Exporte o Excel para planilha completa ou abra cada briefing para ver textos e baixar anexos.</p>
    <?php endif; ?>
  </div>
</body>
</html>