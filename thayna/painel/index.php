<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_thayna_auth();
$pdo  = db();

$tableOk = in_array('thayna_relatorios', array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true);

$success = '';
if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int) ($_POST['id'] ?? 0);
    if ($delId) {
        $pdo->prepare('DELETE FROM thayna_relatorios WHERE id = ?')->execute([$delId]);
        $success = 'Relatório removido.';
    }
}

$rows = [];
if ($tableOk) {
    $rows = $pdo->query(
        'SELECT id, codigo_caso, cliente_nome, data_analise, created_at, updated_at
         FROM thayna_relatorios ORDER BY updated_at DESC, id DESC LIMIT 200'
    )->fetchAll();
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtDate(?string $d): string
{
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : h($d);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <title>Relatórios — Thayna Freire</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/painel/includes/painel.css"/>
</head>
<body>
  <header class="top">
    <div class="top-inner">
      <div>
        <h1>Relatórios</h1>
        <p><?= h($user['name'] ?: $user['username']) ?> · Análise comportamental</p>
      </div>
      <div class="top-actions">
        <a href="/painel/relatorio.php" class="btn btn-light">+ Novo relatório</a>
        <a href="/painel/logout.php" class="btn btn-ghost">Sair</a>
      </div>
    </div>
  </header>

  <main>
    <?php if (!$tableOk): ?>
      <div class="warn">Execute o script <code>admin/sql/09_thayna_relatorios.sql</code> no phpMyAdmin antes de usar o painel.</div>
    <?php else: ?>
      <?php if ($success): ?><div class="alert"><?= h($success) ?></div><?php endif; ?>

      <?php if (!$rows): ?>
        <div class="empty">
          Nenhum relatório cadastrado.<br/>
          Toque em <strong>Novo relatório</strong> para começar.
        </div>
      <?php else: ?>
      <div class="report-list">
        <?php foreach ($rows as $r): ?>
        <article class="report-card">
          <div class="report-card-head">
            <span class="report-code"><?= h($r['codigo_caso']) ?></span>
            <span class="report-date"><?= fmtDate($r['data_analise']) ?></span>
          </div>
          <h2 class="report-client"><?= h($r['cliente_nome']) ?></h2>
          <p class="report-meta">Atualizado em <?= fmtDate(substr($r['updated_at'], 0, 10)) ?></p>
          <div class="report-actions">
            <a href="/painel/relatorio.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-edit">Editar</a>
            <a href="/painel/pdf.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-pdf">Baixar PDF</a>
            <div class="btn-del-wrap">
              <form method="POST" onsubmit="return confirm('Remover este relatório?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/>
                <button type="submit" class="btn btn-sm btn-del">Excluir</button>
              </form>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
