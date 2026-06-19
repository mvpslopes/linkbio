<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/clientes.php';
require_once __DIR__ . '/includes/nav.php';

$user = require_thayna_auth();
$pdo  = db();
$tableOk = thayna_table_clientes_ok($pdo);

$id = (int) ($_GET['id'] ?? 0);
$row = null;

if ($tableOk && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM thayna_clientes WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch() ?: null;
}

$error = '';
$success = isset($_GET['saved']) ? 'Cliente salva com sucesso.' : '';

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_completo'] ?? '');
    $idade = (int) ($_POST['idade'] ?? 0) ?: null;
    $whatsapp = trim($_POST['whatsapp'] ?? '') ?: null;
    $instagram = trim($_POST['instagram'] ?? '') ?: null;
    $cidade = trim($_POST['cidade_estado'] ?? '') ?: null;
    $obs = trim($_POST['observacoes'] ?? '') ?: null;
    $editId = (int) ($_POST['id'] ?? 0);

    if ($nome === '') {
        $error = 'Informe o nome completo.';
    } else {
        try {
            if ($editId > 0) {
                $pdo->prepare(
                    'UPDATE thayna_clientes SET nome_completo=?, idade=?, whatsapp=?, instagram=?, cidade_estado=?, observacoes=? WHERE id=?'
                )->execute([$nome, $idade, $whatsapp, $instagram, $cidade, $obs, $editId]);
                header('Location: /painel/cliente.php?id=' . $editId . '&saved=1');
                exit;
            }
            $token = thayna_gerar_token_cliente($pdo);
            $pdo->prepare(
                'INSERT INTO thayna_clientes (nome_completo, idade, whatsapp, instagram, cidade_estado, observacoes, token, created_by)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$nome, $idade, $whatsapp, $instagram, $cidade, $obs, $token, (int)$user['id']]);
            header('Location: /painel/cliente.php?id=' . (int)$pdo->lastInsertId() . '&saved=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Erro ao salvar cliente.';
        }
    }
}

$status = $row ? thayna_cliente_status($row) : 'pendente';
$termoLink = $row ? thayna_termo_url($row['token']) : '';

$relatorios = [];
if ($row && $id > 0) {
    $st = $pdo->prepare('SELECT id, codigo_caso, data_analise, updated_at FROM thayna_relatorios WHERE cliente_id = ? ORDER BY updated_at DESC');
    $st->execute([$id]);
    $relatorios = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <title><?= $row ? 'Cliente' : 'Nova cliente' ?> — Thayna</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <?php thayna_painel_head(); ?>
</head>
<body class="painel-app">
<?php
$clienteTitle = $row ? thayna_h($row['nome_completo']) : 'Nova cliente';
$clienteSubtitle = '';
if ($row) {
    $clienteSubtitle = '<span class="status-badge status-' . thayna_h($status) . '">' . thayna_h(thayna_cliente_status_label($status)) . '</span>';
}
thayna_painel_layout_start('clientes', [
  'title' => $clienteTitle,
  'subtitle' => $clienteSubtitle,
  'user' => $user,
  'back_href' => '/painel/clientes.php',
  'back_label' => '← Clientes',
]);
?>
    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/10_thayna_clientes_termos.sql</code> no phpMyAdmin.</div>
    <?php else: ?>
      <?php if ($success): ?><div class="alert"><?= thayna_h($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="err"><?= thayna_h($error) ?></div><?php endif; ?>

      <?php if ($row): ?>
      <div class="meta link-box">
        <div>
          <p class="field-label" style="margin-bottom:4px">Link do termo de aceite</p>
          <p class="link-url" id="termo-link"><?= thayna_h($termoLink) ?></p>
          <?php if ($status === 'assinado'): ?>
          <p class="report-meta" style="margin-top:8px">Assinado em <?= thayna_h(date('d/m/Y H:i', strtotime($row['assinado_em']))) ?> por <?= thayna_h($row['assinatura_nome']) ?></p>
          <?php else: ?>
          <p class="report-meta" style="margin-top:8px">Envie este link para a cliente ler e assinar o termo. Apenas uma assinatura é permitida.</p>
          <?php endif; ?>
        </div>
        <div class="link-actions">
          <?php if ($status !== 'assinado'): ?>
          <button type="button" class="btn btn-sm btn-secondary" id="btn-copy">Copiar link</button>
          <?php endif; ?>
          <?php if ($status === 'assinado'): ?>
          <a href="/painel/termo_pdf.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-pdf">Baixar PDF</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" id="cliente-form">
        <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>"/>
        <div class="section">
          <h2>Dados da cliente</h2>
          <label class="field-label" for="nome_completo">Nome completo *</label>
          <input type="text" id="nome_completo" name="nome_completo" required value="<?= thayna_h($row['nome_completo'] ?? '') ?>"/>
          <label class="field-label" for="idade">Idade</label>
          <input type="text" id="idade" name="idade" inputmode="numeric" value="<?= thayna_h((string)($row['idade'] ?? '')) ?>"/>
          <label class="field-label" for="whatsapp">WhatsApp</label>
          <input type="tel" id="whatsapp" name="whatsapp" value="<?= thayna_h($row['whatsapp'] ?? '') ?>"/>
          <label class="field-label" for="instagram">Instagram (opcional)</label>
          <input type="text" id="instagram" name="instagram" value="<?= thayna_h($row['instagram'] ?? '') ?>"/>
          <label class="field-label" for="cidade_estado">Cidade/Estado</label>
          <input type="text" id="cidade_estado" name="cidade_estado" value="<?= thayna_h($row['cidade_estado'] ?? '') ?>"/>
          <label class="field-label" for="observacoes">Observações</label>
          <textarea id="observacoes" name="observacoes" rows="3" placeholder="Anotações internas sobre a cliente"><?= thayna_h($row['observacoes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions-desktop">
          <button type="submit" class="btn btn-primary">Salvar cliente</button>
          <?php if ($row): ?>
          <a href="/painel/relatorio.php?cliente_id=<?= (int)$row['id'] ?>" class="btn btn-secondary">Novo relatório</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($row && $relatorios): ?>
      <div class="section">
        <h2>Relatórios vinculados</h2>
        <?php foreach ($relatorios as $rel): ?>
        <p class="report-meta" style="margin-bottom:8px">
          <a href="/painel/relatorio.php?id=<?= (int)$rel['id'] ?>" style="color:#6d214f;font-weight:600"><?= thayna_h($rel['codigo_caso']) ?></a>
          · <?= thayna_h($rel['data_analise'] ? date('d/m/Y', strtotime($rel['data_analise'])) : '—') ?>
        </p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="sticky-actions">
        <button type="submit" form="cliente-form" class="btn btn-primary">Salvar cliente</button>
        <?php if ($row): ?>
        <a href="/painel/relatorio.php?cliente_id=<?= (int)$row['id'] ?>" class="btn btn-secondary">Novo relatório</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
<?php thayna_painel_layout_end(); ?>
  <?php if ($row && $status !== 'assinado'): ?>
  <script>
    document.getElementById('btn-copy')?.addEventListener('click', function() {
      var link = document.getElementById('termo-link').textContent.trim();
      if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(function() {
          document.getElementById('btn-copy').textContent = 'Copiado!';
        });
      } else {
        prompt('Copie o link:', link);
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>
