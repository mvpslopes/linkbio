<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/clientes.php';
require_once __DIR__ . '/includes/nav.php';

$user = require_thayna_auth();
$pdo  = db();
$tableOk = thayna_table_clientes_ok($pdo);

$success = '';
if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int) ($_POST['id'] ?? 0);
    if ($delId) {
        $pdo->prepare('UPDATE thayna_relatorios SET cliente_id = NULL WHERE cliente_id = ?')->execute([$delId]);
        $pdo->prepare('DELETE FROM thayna_clientes WHERE id = ?')->execute([$delId]);
        $success = 'Cliente removido.';
    }
}

$rows = [];
if ($tableOk) {
    $rows = $pdo->query(
        'SELECT id, nome_completo, whatsapp, cidade_estado, token, questionario_json, assinado_em, updated_at
         FROM thayna_clientes ORDER BY updated_at DESC, id DESC LIMIT 300'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <title>Clientes — Thayna</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <?php thayna_painel_head(); ?>
</head>
<body class="painel-app">
<?php thayna_painel_layout_start('clientes', [
  'title' => 'Clientes',
  'subtitle' => thayna_h($user['name'] ?: $user['username']),
  'user' => $user,
  'actions' => '<a href="/painel/cliente.php" class="btn btn-primary btn-sm">+ Nova cliente</a>',
]); ?>
    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/10_thayna_clientes_termos.sql</code> no phpMyAdmin.</div>
    <?php else: ?>
      <?php if ($success): ?><div class="alert"><?= thayna_h($success) ?></div><?php endif; ?>
      <?php if (!$rows): ?>
        <div class="empty">Nenhuma cliente cadastrada.<br/>Toque em <strong>Nova cliente</strong>.</div>
      <?php else: ?>
      <div class="report-list">
        <?php foreach ($rows as $r):
          $st = thayna_cliente_status($r);
        ?>
        <article class="report-card">
          <div class="report-card-head">
            <span class="status-badge status-<?= thayna_h($st) ?>"><?= thayna_h(thayna_cliente_status_label($st)) ?></span>
            <span class="report-date"><?= thayna_h(date('d/m/Y', strtotime($r['updated_at']))) ?></span>
          </div>
          <h2 class="report-client"><?= thayna_h($r['nome_completo']) ?></h2>
          <p class="report-meta"><?= thayna_h($r['whatsapp'] ?: '—') ?> · <?= thayna_h($r['cidade_estado'] ?: '—') ?></p>
          <div class="report-actions">
            <a href="/painel/cliente.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-edit">Abrir</a>
            <?php if ($st === 'assinado'): ?>
            <a href="/painel/termo_pdf.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-pdf">PDF termo</a>
            <?php else: ?>
            <button type="button" class="btn btn-sm btn-secondary btn-copy-link" data-link="<?= thayna_h(thayna_termo_url($r['token'])) ?>">Copiar link</button>
            <?php endif; ?>
            <div class="btn-del-wrap">
              <form method="POST" onsubmit="return confirm('Remover esta cliente?')">
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
<?php thayna_painel_layout_end(); ?>
  <script>
    document.querySelectorAll('.btn-copy-link').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var link = btn.getAttribute('data-link');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(function() {
            btn.textContent = 'Link copiado!';
            setTimeout(function() { btn.textContent = 'Copiar link'; }, 2000);
          });
        } else {
          prompt('Copie o link do termo:', link);
        }
      });
    });
  </script>
</body>
</html>
