<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/loyalty.php';

$user = require_priscila_auth();
loyalty_csrf_boot();
$pdo = db();
$tableOk = loyalty_table_ok($pdo);

$id = (int) ($_GET['id'] ?? 0);
$ok = (string) ($_GET['ok'] ?? '');
$error = '';
$client = ($tableOk && $id > 0) ? loyalty_find_client($pdo, $id) : null;

if ($tableOk && $client && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!loyalty_csrf_ok()) {
        $error = 'Sessão expirada. Tente de novo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'stamp') {
                $service = trim((string) ($_POST['service'] ?? ''));
                loyalty_add_stamp($pdo, $client, $service, (int) $user['id']);
                header('Location: /painel/cliente.php?id=' . $id . '&ok=stamp');
                exit;
            }
            if ($action === 'redeem') {
                loyalty_redeem($pdo, $client, (int) $user['id']);
                header('Location: /painel/cliente.php?id=' . $id . '&ok=redeem');
                exit;
            }
            if ($action === 'undo') {
                loyalty_undo_last($pdo, $client, (int) $user['id']);
                header('Location: /painel/cliente.php?id=' . $id . '&ok=undo');
                exit;
            }
            if ($action === 'update') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $phone = loyalty_normalize_phone((string) ($_POST['phone'] ?? ''));
                if ($name === '' || !loyalty_phone_valid($phone)) {
                    throw new RuntimeException('Informe o nome e um WhatsApp válido com DDD.');
                }
                $pdo->prepare('UPDATE loyalty_clients SET name = ?, phone = ? WHERE id = ? AND page_slug = ?')
                    ->execute([$name, $phone, $id, PRISCILA_SLUG]);
                header('Location: /painel/cliente.php?id=' . $id . '&ok=saved');
                exit;
            }
            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM loyalty_clients WHERE id = ? AND page_slug = ?')->execute([$id, PRISCILA_SLUG]);
                header('Location: /painel/?ok=deleted');
                exit;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $client = loyalty_find_client($pdo, $id);
        } catch (PDOException $e) {
            $error = ((int) $e->errorInfo[1] === 1062)
                ? 'Já existe uma cliente com este WhatsApp.'
                : 'Não foi possível salvar.';
            $client = loyalty_find_client($pdo, $id);
        }
    }
}

$history = [];
if ($client) {
    $st = $pdo->prepare('SELECT kind, service, created_at FROM loyalty_stamps WHERE client_id = ? ORDER BY id DESC LIMIT 20');
    $st->execute([(int) $client['id']]);
    $history = $st->fetchAll();
}

$flash = [
    'created' => 'Cliente cadastrada.',
    'exists' => 'Esta cliente já existia — cartão aberto.',
    'stamp' => 'Selo marcado. Envie a confirmação no WhatsApp.',
    'redeem' => 'Brinde resgatado. O ciclo recomeçou.',
    'undo' => 'Último selo desfeito.',
    'saved' => 'Dados atualizados.',
][$ok] ?? '';

$waMsg = $client ? (($ok === 'redeem') ? loyalty_redeem_message($client) : loyalty_confirm_message($client)) : '';
$waHref = $client ? loyalty_wa_url((string) $client['phone'], $waMsg) : '#';
$n = $client ? (int) $client['stamps_count'] : 0;
$ready = $client && !empty($client['reward_available']);
$expired = $ready && !empty($client['reward_expires_at']) && strtotime((string) $client['reward_expires_at']) < time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#522530"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title><?= $client ? loyalty_h((string) $client['name']) : 'Cliente' ?> — Fidelidade</title>
  <link rel="icon" href="/favicon.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/painel/includes/painel.css?v=1"/>
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="/painel/">
        <img src="/logo/logo-nav.png" alt="Priscila Ramos"/>
        <span class="brand-meta">
          <strong>Cartão fidelidade</strong>
          <span>Voltar para a lista</span>
        </span>
      </a>
      <div class="top-actions">
        <a class="btn btn-ghost btn-sm" href="/painel/">Clientes</a>
        <a class="btn btn-ghost btn-sm" href="/painel/logout.php">Sair</a>
      </div>
    </div>
  </header>

  <main class="wrap page">
    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/15_loyalty.sql</code> no phpMyAdmin.</div>
    <?php elseif (!$client): ?>
      <div class="err">Cliente não encontrada.</div>
      <a class="btn btn-ghost" href="/painel/">Voltar</a>
    <?php else: ?>
      <?php if ($flash): ?><div class="alert"><?= loyalty_h($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="err"><?= loyalty_h($error) ?></div><?php endif; ?>
      <?php if ($expired): ?><div class="warn">O prazo de 30 dias do brinde já passou. Você ainda pode resgatar se quiser honrar.</div><?php endif; ?>

      <div class="card-visual">
        <p class="eyebrow">Cartão digital</p>
        <h2><?= loyalty_h((string) $client['name']) ?></h2>
        <p><?= loyalty_h(loyalty_format_phone((string) $client['phone'])) ?> · <?= $ready ? 'Brinde disponível' : $n . '/10' ?></p>
        <div class="dots" aria-label="<?= $n ?> de 10 selos">
          <?php for ($i = 1; $i <= 10; $i++): ?>
            <span class="<?= $i <= $n ? 'on' : '' ?><?= $ready && $i === 10 ? ' reward' : '' ?>"></span>
          <?php endfor; ?>
        </div>
      </div>

      <?php if ($ready): ?>
        <form method="POST" class="card">
          <?= loyalty_csrf_field() ?>
          <input type="hidden" name="action" value="redeem"/>
          <p class="lede" style="margin:0 0 12px">Cartão completo. Resgate o brinde no atendimento de hoje e o ciclo recomeça.</p>
          <div class="actions two">
            <button type="submit" class="btn btn-primary">Resgatar brinde</button>
            <a class="btn btn-wa" href="<?= loyalty_h($waHref) ?>" target="_blank" rel="noopener noreferrer">Enviar no WhatsApp</a>
          </div>
        </form>
      <?php else: ?>
        <form method="POST" class="card">
          <?= loyalty_csrf_field() ?>
          <input type="hidden" name="action" value="stamp"/>
          <label for="service">Serviço express de hoje</label>
          <select id="service" name="service" required>
            <option value="">Selecione…</option>
            <?php foreach (loyalty_services() as $svc): ?>
              <option value="<?= loyalty_h($svc) ?>"><?= loyalty_h($svc) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="actions two">
            <button type="submit" class="btn btn-rose">+1 selo</button>
            <a class="btn btn-wa" href="<?= loyalty_h($waHref) ?>" target="_blank" rel="noopener noreferrer">Enviar no WhatsApp</a>
          </div>
        </form>
      <?php endif; ?>

      <form method="POST" onsubmit="return confirm('Desfazer o último selo desta cliente?');" style="margin-bottom:16px">
        <?= loyalty_csrf_field() ?>
        <input type="hidden" name="action" value="undo"/>
        <button type="submit" class="btn btn-ghost btn-full">Desfazer último selo</button>
      </form>

      <section class="card">
        <h2 class="display" style="font-size:1.25rem;margin-bottom:12px">Dados</h2>
        <form method="POST" class="split">
          <?= loyalty_csrf_field() ?>
          <input type="hidden" name="action" value="update"/>
          <div>
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required maxlength="120" value="<?= loyalty_h((string) $client['name']) ?>"/>
          </div>
          <div>
            <label for="phone">WhatsApp</label>
            <input type="tel" id="phone" name="phone" required value="<?= loyalty_h(loyalty_format_phone((string) $client['phone'])) ?>"/>
          </div>
          <div style="grid-column:1/-1">
            <button type="submit" class="btn btn-ghost btn-full">Salvar dados</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2 class="display" style="font-size:1.25rem;margin-bottom:8px">Histórico</h2>
        <?php if (!$history): ?>
          <p class="lede" style="margin:0">Ainda sem movimentos.</p>
        <?php else: ?>
          <ul class="history">
            <?php foreach ($history as $h): ?>
              <li>
                <span>
                  <span class="kind"><?= loyalty_h(loyalty_kind_label((string) $h['kind'])) ?></span>
                  <?php if (!empty($h['service'])): ?> · <?= loyalty_h((string) $h['service']) ?><?php endif; ?>
                </span>
                <span class="when"><?= loyalty_h(date('d/m/Y H:i', strtotime((string) $h['created_at']))) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <form method="POST" onsubmit="return confirm('Excluir esta cliente e todo o histórico?');">
        <?= loyalty_csrf_field() ?>
        <input type="hidden" name="action" value="delete"/>
        <button type="submit" class="btn btn-del btn-full">Excluir cliente</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
