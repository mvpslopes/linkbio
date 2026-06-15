<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_report.php';

$user = require_thayna_auth();
$pdo  = db();

$tableOk = in_array('thayna_relatorios', array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true);

$id = (int) ($_GET['id'] ?? 0);
$row = null;
$data = [];

if ($tableOk && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM thayna_relatorios WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch() ?: null;
    if ($row) {
        $data = thayna_relatorio_prepare($row);
    }
}

$error = '';
$success = '';

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = trim($_POST['cliente_nome'] ?? '');
    if ($cliente === '') {
        $error = 'Informe o nome do cliente.';
    } else {
        $payload = thayna_payload_from_post($_POST);
        $dataAnalise = $payload['data_analise'];
        $periodoInicio = $payload['periodo_inicio'];
        $periodoTermino = $payload['periodo_termino'];
        $editId = (int) ($_POST['id'] ?? 0);

        try {
            if ($editId > 0) {
                $pdo->prepare(
                    'UPDATE thayna_relatorios SET cliente_nome=?, data_analise=?, periodo_inicio=?, periodo_termino=?, payload_json=? WHERE id=?'
                )->execute([
                    $cliente,
                    $dataAnalise,
                    $periodoInicio,
                    $periodoTermino,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $editId,
                ]);
                header('Location: /painel/relatorio.php?id=' . $editId . '&saved=1');
                exit;
            }

            $codigo = thayna_gerar_codigo_caso($pdo);
            $pdo->prepare(
                'INSERT INTO thayna_relatorios (codigo_caso, cliente_nome, data_analise, periodo_inicio, periodo_termino, payload_json, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $codigo,
                $cliente,
                $dataAnalise,
                $periodoInicio,
                $periodoTermino,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                (int) $user['id'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            header('Location: /painel/relatorio.php?id=' . $newId . '&saved=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Erro ao salvar relatório.';
        }
    }
    $data = array_merge($data, thayna_payload_from_post($_POST), ['cliente_nome' => $cliente]);
}

if (isset($_GET['saved'])) {
    $success = 'Relatório salvo com sucesso.';
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v(array $data, string $key): string
{
    return h($data[$key] ?? '');
}

function radioGroup(string $name, array $options, ?string $selected): void
{
    echo '<div class="radio-grid">';
    foreach ($options as $opt) {
        $sel = ($selected === $opt) ? ' checked' : '';
        $id = $name . '_' . md5($opt);
        echo '<label class="radio-item"><input type="radio" name="' . h($name) . '" value="' . h($opt) . '" id="' . h($id) . '"' . $sel . '><span>' . h($opt) . '</span></label>';
    }
    echo '</div>';
}

$pontos = $data['pontos_relevantes'] ?? ['', '', '', ''];
if (!is_array($pontos)) {
    $pontos = array_pad(array_filter(explode("\n", (string) $pontos)), 4, '');
}
$pontos = array_pad($pontos, 4, '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <title><?= $row ? 'Editar' : 'Novo' ?> relatório — Thayna</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/painel/includes/painel.css"/>
</head>
<body>
  <header class="top">
    <div class="top-inner">
      <div class="top-row">
        <div>
          <strong class="title"><?= $row ? 'Editar relatório' : 'Novo relatório' ?></strong>
          <?php if ($row): ?><p class="sub">Código: <?= h($row['codigo_caso']) ?></p><?php endif; ?>
        </div>
        <a href="/painel/" class="back-link">← Lista</a>
      </div>
    </div>
  </header>

  <main>
    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/09_thayna_relatorios.sql</code> no phpMyAdmin.</div>
    <?php else: ?>

    <?php if ($success): ?><div class="alert"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

    <?php if ($row): ?>
    <div class="meta">
      <span>Código: <strong><?= h($row['codigo_caso']) ?></strong></span>
      <a href="/painel/pdf.php?id=<?= (int)$row['id'] ?>" class="btn btn-pdf btn-sm" target="_blank">Baixar PDF</a>
    </div>
    <?php endif; ?>

    <form method="POST" id="relatorio-form">
      <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>"/>

      <div class="section">
        <h2>Identificação</h2>
        <div class="grid2">
          <div>
            <label class="field-label" for="cliente_nome">Cliente *</label>
            <input type="text" id="cliente_nome" name="cliente_nome" required value="<?= v($data, 'cliente_nome') ?>" placeholder="Nome do cliente"/>
          </div>
          <div>
            <label class="field-label" for="data_analise">Data da análise</label>
            <input type="date" id="data_analise" name="data_analise" value="<?= v($data, 'data_analise') ?>"/>
          </div>
        </div>
        <div class="grid2">
          <div>
            <label class="field-label" for="periodo_inicio">Período — início</label>
            <input type="date" id="periodo_inicio" name="periodo_inicio" value="<?= v($data, 'periodo_inicio') ?>"/>
          </div>
          <div>
            <label class="field-label" for="periodo_termino">Período — término</label>
            <input type="date" id="periodo_termino" name="periodo_termino" value="<?= v($data, 'periodo_termino') ?>"/>
          </div>
        </div>
      </div>

      <div class="section">
        <h2>3. Resumo das interações</h2>
        <label class="field-label" for="resumo_interacoes">Descrição resumida dos acontecimentos</label>
        <textarea id="resumo_interacoes" name="resumo_interacoes" placeholder="Ex.: O participante respondeu ao primeiro contato após aproximadamente 2 horas..."><?= v($data, 'resumo_interacoes') ?></textarea>
      </div>

      <div class="section">
        <h2>4. Indicadores observados</h2>

        <h3>A) Receptividade ao contato</h3>
        <?php radioGroup('receptividade', ['Muito baixa', 'Baixa', 'Moderada', 'Alta', 'Muito alta'], $data['receptividade'] ?? null); ?>
        <label class="field-label" for="receptividade_obs">Observações</label>
        <textarea id="receptividade_obs" name="receptividade_obs" rows="2"><?= v($data, 'receptividade_obs') ?></textarea>

        <h3>B) Iniciativa na conversa</h3>
        <?php radioGroup('iniciativa', ['Não demonstrou', 'Ocasional', 'Frequente'], $data['iniciativa'] ?? null); ?>
        <label class="field-label" for="iniciativa_obs">Observações</label>
        <textarea id="iniciativa_obs" name="iniciativa_obs" rows="2"><?= v($data, 'iniciativa_obs') ?></textarea>

        <h3>C) Abertura para interação pessoal</h3>
        <?php radioGroup('abertura', ['Não demonstrou', 'Limitada', 'Moderada', 'Elevada'], $data['abertura'] ?? null); ?>
        <label class="field-label" for="abertura_obs">Observações</label>
        <textarea id="abertura_obs" name="abertura_obs" rows="2"><?= v($data, 'abertura_obs') ?></textarea>

        <h3>D) Menções ao relacionamento atual</h3>
        <?php radioGroup('mencoes_relacionamento', ['Espontâneas', 'Apenas quando questionado(a)', 'Não mencionou'], $data['mencoes_relacionamento'] ?? null); ?>
        <label class="field-label" for="mencoes_obs">Observações</label>
        <textarea id="mencoes_obs" name="mencoes_obs" rows="2"><?= v($data, 'mencoes_obs') ?></textarea>

        <h3>E) Respeito aos limites do relacionamento</h3>
        <?php radioGroup('respeito_limites', ['Demonstrou claramente', 'Parcialmente', 'Não demonstrou'], $data['respeito_limites'] ?? null); ?>
        <label class="field-label" for="respeito_obs">Observações</label>
        <textarea id="respeito_obs" name="respeito_obs" rows="2"><?= v($data, 'respeito_obs') ?></textarea>
      </div>

      <div class="section">
        <h2>5. Pontos relevantes identificados</h2>
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <label class="field-label" for="ponto_<?= $i ?>">Ponto <?= $i ?></label>
        <input type="text" id="ponto_<?= $i ?>" name="ponto_<?= $i ?>" value="<?= h($pontos[$i - 1] ?? '') ?>" placeholder="Descreva um ponto relevante"/>
        <?php endfor; ?>
      </div>

      <div class="section">
        <h2>6. Conclusão técnica</h2>
        <textarea id="conclusao_tecnica" name="conclusao_tecnica" placeholder="Conclusão personalizada com base nas interações observadas..."><?= v($data, 'conclusao_tecnica') ?></textarea>
      </div>

      <div class="section">
        <h2>7. Resultado geral</h2>
        <label class="field-label">Nível de comprometimento observado</label>
        <?php radioGroup('nivel_comprometimento', ['Muito elevado', 'Elevado', 'Moderado', 'Baixo', 'Muito baixo'], $data['nivel_comprometimento'] ?? null); ?>
        <label class="field-label" for="observacao_final">Observação final</label>
        <textarea id="observacao_final" name="observacao_final" rows="3"><?= v($data, 'observacao_final') ?: 'Este relatório apresenta exclusivamente os comportamentos observados durante o período analisado e não constitui prova definitiva sobre intenções, sentimentos ou ações não registradas durante a interação.' ?></textarea>
      </div>

      <div class="form-actions-desktop">
        <button type="submit" class="btn btn-primary">Salvar relatório</button>
        <a href="/painel/" class="btn btn-secondary">Cancelar</a>
        <?php if ($row): ?>
        <a href="/painel/pdf.php?id=<?= (int)$row['id'] ?>" class="btn btn-pdf" target="_blank">Gerar PDF</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="sticky-actions" id="sticky-bar">
      <button type="submit" form="relatorio-form" class="btn btn-primary">Salvar relatório</button>
      <?php if ($row): ?>
      <div class="sticky-actions-row">
        <a href="/painel/" class="btn btn-secondary">Cancelar</a>
        <a href="/painel/pdf.php?id=<?= (int)$row['id'] ?>" class="btn btn-pdf" target="_blank">PDF</a>
      </div>
      <?php else: ?>
      <a href="/painel/" class="btn btn-secondary">Cancelar</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>
</body>
</html>
