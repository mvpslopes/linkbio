<?php
require_once dirname(__DIR__) . '/painel/includes/db.php';
require_once dirname(__DIR__) . '/painel/includes/clientes.php';

$pdo = db();
$token = trim($_GET['token'] ?? '');
if ($token === '' && preg_match('#/termo/([A-Za-z0-9_-]{12}|[a-fA-F0-9]{48})#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $token = $m[1];
}

if ($token === '' || !thayna_token_is_valid($token)) {
    http_response_code(404);
    $page = 'invalid';
} elseif (!thayna_table_clientes_ok($pdo)) {
    http_response_code(503);
    $page = 'unavailable';
} else {
    $st = $pdo->prepare('SELECT * FROM thayna_clientes WHERE token = ? LIMIT 1');
    $st->execute([$token]);
    $cliente = $st->fetch();
    if (!$cliente) {
        http_response_code(404);
        $page = 'invalid';
    } elseif (!empty($cliente['assinado_em'])) {
        $page = 'done';
    } else {
        $page = 'form';
        $error = '';
        $step = 1;
        $questionario = json_decode($cliente['questionario_json'] ?? '{}', true) ?: [];

        if (!empty($questionario) && !isset($_GET['edit'])) {
            $step = 2;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'questionario') {
                $q = thayna_questionario_from_post($_POST);
                $missing = false;
                foreach (['motivo', 'sentimento', 'relacionamento_atual', 'tempo_relacionamento', 'inseguranca', 'traicao_antes', 'expectativa', 'preparada'] as $req) {
                    if (trim($q[$req] ?? '') === '') {
                        $missing = true;
                        break;
                    }
                }
                if ($missing) {
                    $error = 'Responda todas as perguntas obrigatórias.';
                    $step = 1;
                    $questionario = $q;
                } else {
                    $pdo->prepare('UPDATE thayna_clientes SET questionario_json = ? WHERE id = ? AND assinado_em IS NULL')
                        ->execute([json_encode($q, JSON_UNESCAPED_UNICODE), (int)$cliente['id']]);
                    $questionario = $q;
                    $step = 2;
                }
            } elseif ($action === 'assinatura') {
                if (empty($questionario) && empty($cliente['questionario_json'])) {
                    $error = 'Preencha o questionário antes de assinar.';
                    $step = 1;
                } else {
                    $nomeAssinatura = trim($_POST['assinatura_nome'] ?? '');
                    if ($nomeAssinatura === '') {
                        $error = 'Digite seu nome completo para confirmar a assinatura.';
                        $step = 2;
                    } else {
                        $pdo->prepare(
                            'UPDATE thayna_clientes SET assinatura_nome = ?, assinado_em = NOW(), assinatura_ip_hash = ?
                             WHERE id = ? AND assinado_em IS NULL'
                        )->execute([$nomeAssinatura, thayna_ip_hash(), (int)$cliente['id']]);
                        $cliente['assinatura_nome'] = $nomeAssinatura;
                        $cliente['assinado_em'] = date('Y-m-d H:i:s');
                        $page = 'signed';
                    }
                }
            }
        }
    }
}

$labels = thayna_questionario_labels();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#6d214f"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Termo de aceite — Thayna Freire</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --wine: #6d214f;
      --rose: #8F2C5A;
      --pink: #b33771;
      --cream: #F9ECE5;
      --bg: #f8fafc;
      --card: #fff;
      --border: #e2e8f0;
      --text: #0f172a;
      --muted: #64748b;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.55;
      min-height: 100vh;
    }
    .hero {
      background: linear-gradient(135deg, var(--wine), var(--rose));
      color: #fff;
      padding: 28px 20px 24px;
      text-align: center;
    }
    .hero img { width: 72px; height: auto; margin-bottom: 12px; }
    .hero h1 { font-size: 1.15rem; font-weight: 700; }
    .hero p { font-size: .88rem; opacity: .9; margin-top: 6px; }
    main { max-width: 680px; margin: 0 auto; padding: 20px 16px 40px; }
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 4px 20px rgba(15,23,42,.04);
    }
    .steps {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
    }
    .step-pill {
      flex: 1;
      text-align: center;
      font-size: .75rem;
      font-weight: 600;
      padding: 8px 6px;
      border-radius: 999px;
      background: #f1f5f9;
      color: var(--muted);
    }
    .step-pill.active { background: var(--cream); color: var(--wine); }
    .step-pill.done { background: #dcfce7; color: #166534; }
    label.q {
      display: block;
      font-size: .88rem;
      font-weight: 600;
      margin: 16px 0 8px;
      color: var(--wine);
    }
    label.q:first-child { margin-top: 0; }
    input[type="text"], input[type="tel"], textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 10px;
      font: inherit;
      font-size: 1rem;
      background: #fff;
    }
    textarea { min-height: 88px; resize: vertical; }
    input:focus, textarea:focus {
      outline: none;
      border-color: var(--pink);
      box-shadow: 0 0 0 3px rgba(179,55,113,.15);
    }
    .termo-text {
      font-size: .86rem;
      color: #334155;
      white-space: pre-wrap;
      max-height: 340px;
      overflow-y: auto;
      padding: 14px;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: 10px;
      margin-bottom: 16px;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 14px 20px;
      border: none;
      border-radius: 999px;
      font: inherit;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--pink), var(--rose));
      color: #fff;
      margin-top: 8px;
    }
    .btn-secondary {
      background: #fff;
      color: var(--wine);
      border: 2px solid var(--wine);
      margin-top: 10px;
    }
    .err {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-size: .9rem;
    }
    .ok {
      text-align: center;
      padding: 32px 16px;
    }
    .ok h2 { color: var(--wine); margin-bottom: 10px; }
    .ok p { color: var(--muted); font-size: .95rem; }
    .note { font-size: .8rem; color: var(--muted); margin-top: 12px; }
    .check-row {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      margin: 16px 0;
      font-size: .88rem;
    }
    .check-row input { width: 20px; height: 20px; margin-top: 2px; flex-shrink: 0; }
  </style>
</head>
<body>
  <header class="hero">
    <img src="/logo/logo.png" alt="Thayna Freire" width="72" height="72" onerror="this.style.display='none'"/>
    <h1>Termo de aceite</h1>
    <p>Prestação de serviço — atendimento sigiloso</p>
  </header>

  <main>
    <?php if ($page === 'invalid'): ?>
      <div class="card ok">
        <h2>Link inválido</h2>
        <p>Este link não existe ou expirou. Entre em contato com a Thayna para receber um novo link.</p>
      </div>
    <?php elseif ($page === 'unavailable'): ?>
      <div class="card ok">
        <h2>Indisponível</h2>
        <p>O sistema está em manutenção. Tente novamente em alguns minutos.</p>
      </div>
    <?php elseif ($page === 'signed'): ?>
      <div class="card ok">
        <h2>Termo assinado com sucesso!</h2>
        <p>Obrigada, <?= thayna_h($cliente['assinatura_nome'] ?? '') ?>! Seu aceite foi registrado em <?= thayna_h(date('d/m/Y \à\s H:i')) ?>.</p>
        <p class="note">A Thayna receberá uma notificação do seu cadastro. Em caso de dúvidas, fale pelo WhatsApp do atendimento.</p>
      </div>
    <?php elseif ($page === 'done'): ?>
      <div class="card ok">
        <h2>Termo já assinado</h2>
        <p>Este link já foi utilizado e não permite nova assinatura.</p>
        <p class="note">Em caso de dúvidas, fale com a Thayna pelo WhatsApp informado no seu atendimento.</p>
      </div>
    <?php else: ?>
      <div class="steps">
        <span class="step-pill <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">1. Sobre você</span>
        <span class="step-pill <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. Termo e assinatura</span>
      </div>

      <?php if (!empty($error)): ?><div class="err"><?= thayna_h($error) ?></div><?php endif; ?>

      <?php if ($step === 1): ?>
      <form method="POST" class="card">
        <input type="hidden" name="action" value="questionario"/>
        <p style="font-size:.9rem;color:var(--muted);margin-bottom:8px">Olá, <strong><?= thayna_h($cliente['nome_completo']) ?></strong>! Responda com sinceridade para personalizarmos seu atendimento.</p>
        <?php $n = 1; foreach ($labels as $key => $label):
          $optional = in_array($key, ['traicao_detalhe', 'info_importante'], true);
        ?>
        <label class="q" for="<?= thayna_h($key) ?>"><?= $n ?>. <?= thayna_h($label) ?><?= $optional ? ' (opcional)' : '' ?></label>
        <?php if (strlen($label) > 60 || in_array($key, ['motivo', 'inseguranca', 'expectativa', 'info_importante', 'traicao_detalhe'], true)): ?>
        <textarea id="<?= thayna_h($key) ?>" name="<?= thayna_h($key) ?>" <?= $optional ? '' : 'required' ?>><?= thayna_h($questionario[$key] ?? '') ?></textarea>
        <?php else: ?>
        <input type="text" id="<?= thayna_h($key) ?>" name="<?= thayna_h($key) ?>" value="<?= thayna_h($questionario[$key] ?? '') ?>" <?= $optional ? '' : 'required' ?>/>
        <?php endif; ?>
        <?php $n++; endforeach; ?>
        <button type="submit" class="btn btn-primary">Continuar para o termo</button>
      </form>
      <?php else: ?>
      <form method="POST" class="card">
        <input type="hidden" name="action" value="assinatura"/>
        <h2 style="font-size:1rem;color:var(--wine);margin-bottom:12px">Leia o termo com atenção</h2>
        <div class="termo-text"><?= thayna_h(thayna_termo_texto_legal()) ?></div>

        <label class="check-row">
          <input type="checkbox" name="aceite" value="1" required id="aceite"/>
          <span for="aceite">Declaro que li, compreendi e aceito integralmente este termo de aceite.</span>
        </label>

        <label class="q" for="assinatura_nome">Digite seu nome completo para assinar digitalmente *</label>
        <input type="text" id="assinatura_nome" name="assinatura_nome" required
               value="<?= thayna_h($cliente['nome_completo'] ?? '') ?>"
               placeholder="Nome completo conforme documento"/>

        <button type="submit" class="btn btn-primary">Assinar termo</button>
        <a href="/termo/<?= rawurlencode($token) ?>?edit=1" class="btn btn-secondary">Voltar ao questionário</a>
        <p class="note">A assinatura digital registra data e hora. Não substitui certificado ICP-Brasil, mas comprova seu aceite neste documento.</p>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
