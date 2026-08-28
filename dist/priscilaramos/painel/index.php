<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/loyalty.php';

$user = require_priscila_auth();
loyalty_csrf_boot();
$pdo = db();
$tableOk = loyalty_table_ok($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$ok = (string) ($_GET['ok'] ?? '');
$error = '';

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!loyalty_csrf_ok()) {
        $error = 'Sessão expirada. Tente de novo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = loyalty_normalize_phone((string) ($_POST['phone'] ?? ''));
            if ($name === '' || !loyalty_phone_valid($phone)) {
                $error = 'Informe o nome e um WhatsApp válido com DDD.';
            } else {
                try {
                    $pdo->prepare(
                        'INSERT INTO loyalty_clients (page_slug, name, phone) VALUES (?, ?, ?)'
                    )->execute([PRISCILA_SLUG, $name, $phone]);
                    header('Location: /painel/cliente.php?id=' . (int) $pdo->lastInsertId() . '&ok=created');
                    exit;
                } catch (PDOException $e) {
                    if ((int) $e->errorInfo[1] === 1062) {
                        $existing = loyalty_find_by_phone($pdo, $phone);
                        if ($existing) {
                            header('Location: /painel/cliente.php?id=' . (int) $existing['id'] . '&ok=exists');
                            exit;
                        }
                    }
                    $error = 'Não foi possível cadastrar. Tente de novo.';
                }
            }
        }
    }
}

$rows = [];
$total = 0;
$ready = 0;
if ($tableOk) {
    $stTotal = $pdo->prepare('SELECT COUNT(*) FROM loyalty_clients WHERE page_slug = ?');
    $stTotal->execute([PRISCILA_SLUG]);
    $total = (int) $stTotal->fetchColumn();
    $stReady = $pdo->prepare('SELECT COUNT(*) FROM loyalty_clients WHERE page_slug = ? AND reward_available = 1');
    $stReady->execute([PRISCILA_SLUG]);
    $ready = (int) $stReady->fetchColumn();

    $sql = 'SELECT id, name, phone, stamps_count, reward_available, rewards_earned, updated_at
            FROM loyalty_clients WHERE page_slug = ?';
    $params = [PRISCILA_SLUG];
    if ($q !== '') {
        $sql .= ' AND (name LIKE ? OR phone LIKE ?)';
        $like = '%' . $q . '%';
        $digits = loyalty_normalize_phone($q);
        $params[] = $like;
        $params[] = $digits !== '' ? '%' . $digits . '%' : $like;
    }
    $sql .= ' ORDER BY updated_at DESC, name ASC LIMIT 300';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#522530"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Clientes — Cartão fidelidade | Priscila Ramos</title>
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
          <span><?= loyalty_h((string) ($user['name'] ?: $user['username'])) ?></span>
        </span>
      </a>
      <div class="top-actions">
        <a class="btn btn-ghost btn-sm" href="/">Site</a>
        <a class="btn btn-ghost btn-sm" href="/painel/logout.php">Sair</a>
      </div>
    </div>
  </header>

  <main class="wrap page">
    <h1>Clientes</h1>
    <p class="lede">Marque o selo depois de cada atendimento express. Clubes de assinatura não entram no cartão.</p>

    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/15_loyalty.sql</code> no phpMyAdmin antes de usar o painel.</div>
    <?php else: ?>
      <?php if ($ok === 'deleted'): ?><div class="alert">Cliente removida.</div><?php endif; ?>
      <?php if ($error): ?><div class="err"><?= loyalty_h($error) ?></div><?php endif; ?>

      <div class="stats">
        <div class="stat"><b><?= (int) $total ?></b><span>cadastradas</span></div>
        <div class="stat"><b><?= (int) $ready ?></b><span>com brinde</span></div>
        <div class="stat"><b>10</b><span>selos / ciclo</span></div>
      </div>

      <section class="card">
        <h2 class="display" style="font-size:1.25rem;margin-bottom:12px">Nova cliente</h2>
        <form method="POST" class="split">
          <?= loyalty_csrf_field() ?>
          <input type="hidden" name="action" value="create"/>
          <div>
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required maxlength="120" autocomplete="name" placeholder="Nome da cliente"/>
          </div>
          <div>
            <label for="phone">WhatsApp</label>
            <input type="tel" id="phone" name="phone" required inputmode="tel" autocomplete="tel" placeholder="47 99999-0000"/>
          </div>
          <div style="grid-column:1/-1">
            <button type="submit" class="btn btn-primary btn-full">Cadastrar e abrir cartão</button>
          </div>
        </form>
      </section>

      <form class="search-bar" method="GET" role="search">
        <input type="search" name="q" value="<?= loyalty_h($q) ?>" placeholder="Buscar por nome ou WhatsApp" autocomplete="off"/>
        <button type="submit" class="btn btn-ghost">Buscar</button>
      </form>

      <?php if (!$rows): ?>
        <div class="empty">
          <?php if ($q): ?>Nenhuma cliente encontrada.<?php else: ?>Nenhuma cliente cadastrada ainda.<?php endif; ?>
        </div>
      <?php else: ?>
        <div class="client-list">
          <?php foreach ($rows as $r):
            $n = (int) $r['stamps_count'];
            $readyCard = !empty($r['reward_available']);
          ?>
            <a class="client-card" href="/painel/cliente.php?id=<?= (int) $r['id'] ?>">
              <span class="dots-mini" aria-hidden="true">
                <?php for ($i = 1; $i <= 10; $i++): ?><i class="<?= $i <= $n ? 'on' : '' ?>"></i><?php endfor; ?>
              </span>
              <span class="client-info">
                <strong><?= loyalty_h((string) $r['name']) ?></strong>
                <span><?= loyalty_h(loyalty_format_phone((string) $r['phone'])) ?> · <?= $n ?>/10</span>
              </span>
              <?php if ($readyCard): ?><span class="badge ready">Brinde</span>
              <?php elseif ($n > 0): ?><span class="badge"><?= $n ?>/10</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
