<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/people.php';
require_once __DIR__ . '/includes/nav.php';

$user = require_genealogy_auth();
$pdo = db();
$tableOk = genealogy_table_ok($pdo);

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$success = $_GET['ok'] ?? '';

$rows = [];
$total = 0;
$semPais = 0;

if ($tableOk) {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM genealogy_people')->fetchColumn();

    $sql = 'SELECT id, full_name, birth_date, death_date, birth_year_only, gender, notes, photo_path, updated_at FROM genealogy_people WHERE 1=1';
    $params = [];

    if ($q !== '') {
        $sql .= ' AND full_name LIKE ?';
        $params[] = '%' . $q . '%';
    }

    if ($filter === 'sem_pais') {
        $sql .= ' AND id NOT IN (
            SELECT person_id FROM genealogy_relations WHERE relation_type IN (\'father\',\'mother\')
        )';
    }

    $sql .= ' ORDER BY full_name ASC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $semPais = (int) $pdo->query(
        'SELECT COUNT(*) FROM genealogy_people p
         WHERE NOT EXISTS (
           SELECT 1 FROM genealogy_relations r
           WHERE r.person_id = p.id AND r.relation_type IN (\'father\',\'mother\')
         )'
    )->fetchColumn();
}

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int) ($_POST['id'] ?? 0);
    if ($delId) {
        $st = $pdo->prepare('SELECT photo_path FROM genealogy_people WHERE id = ?');
        $st->execute([$delId]);
        $photoPath = $st->fetchColumn();
        if ($photoPath) {
            genealogy_delete_photo_file((string) $photoPath);
        }
        $pdo->prepare('DELETE FROM genealogy_people WHERE id = ?')->execute([$delId]);
        header('Location: /painel/?ok=deleted');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#1B3A2D"/>
  <title>Pessoas — Árvore Genealógica</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <?php genealogy_painel_head(); ?>
</head>
<body class="painel-app">
<?php genealogy_painel_layout_start('pessoas', [
  'title' => 'Pessoas',
  'subtitle' => genealogy_h($user['name'] ?: $user['username']),
  'user' => $user,
  'actions' => '<a href="/painel/pessoa.php" class="btn btn-primary btn-sm">+ Nova pessoa</a>',
]); ?>

    <?php if (!$tableOk): ?>
      <div class="warn">
        Execute <code>admin/sql/11_genealogia.sql</code> no phpMyAdmin antes de usar o painel.
      </div>
    <?php else: ?>

      <?php if ($success === 'saved'): ?>
        <div class="alert">Pessoa salva com sucesso.</div>
      <?php elseif ($success === 'deleted'): ?>
        <div class="alert">Pessoa removida.</div>
      <?php endif; ?>

      <div class="stats-bar">
        <div class="stat-pill"><strong><?= $total ?></strong> cadastradas</div>
        <div class="stat-pill"><strong><?= $semPais ?></strong> sem pai/mãe</div>
      </div>

      <form class="search-bar" method="GET" role="search">
        <input type="search" name="q" value="<?= genealogy_h($q) ?>" placeholder="Buscar por nome..." autocomplete="off"/>
        <?php if ($filter): ?><input type="hidden" name="filter" value="<?= genealogy_h($filter) ?>"/><?php endif; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
      </form>

      <div class="filter-chips">
        <a href="/painel/" class="chip<?= $filter === '' ? ' active' : '' ?>">Todas</a>
        <a href="/painel/?filter=sem_pais" class="chip<?= $filter === 'sem_pais' ? ' active' : '' ?>">Sem pai/mãe</a>
      </div>

      <?php if (!$rows): ?>
        <div class="empty">
          <?php if ($q || $filter): ?>
            Nenhuma pessoa encontrada com esses filtros.
          <?php else: ?>
            Nenhuma pessoa cadastrada ainda.<br/>Toque em <strong>Nova pessoa</strong> para começar.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="person-list">
          <?php foreach ($rows as $r):
            $age = genealogy_calc_age($r['birth_date'], $r['death_date'], $r['birth_year_only'] ? (int)$r['birth_year_only'] : null);
            $dates = genealogy_format_dates($r['birth_date'], $r['death_date'], $r['birth_year_only'] ? (int)$r['birth_year_only'] : null);
            $summary = genealogy_relation_summary($pdo, (int)$r['id']);
            $photoUrl = genealogy_photo_url($r['photo_path'] ?? null);
          ?>
          <article class="person-card">
            <div class="person-card-head">
              <div class="person-card-main">
                <?php if ($photoUrl): ?>
                  <img src="<?= genealogy_h($photoUrl) ?>" alt="" class="person-thumb"/>
                <?php else: ?>
                  <span class="person-thumb person-thumb-placeholder"><?= genealogy_h(mb_strtoupper(mb_substr($r['full_name'], 0, 1))) ?></span>
                <?php endif; ?>
                <div>
                <h2 class="person-name"><?= genealogy_h($r['full_name']) ?></h2>
                <?php if ($dates): ?><p class="person-meta"><?= genealogy_h($dates) ?></p><?php endif; ?>
                </div>
              </div>
              <?php if ($age['text']): ?><span class="person-age"><?= genealogy_h($age['text']) ?></span><?php endif; ?>
            </div>
            <p class="person-links"><?= genealogy_h($summary) ?></p>
            <div class="person-actions">
              <a href="/painel/pessoa.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
              <form method="POST" onsubmit="return confirm('Remover esta pessoa e todos os vínculos?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/>
                <button type="submit" class="btn btn-sm btn-del">Excluir</button>
              </form>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

<?php genealogy_painel_layout_end(); ?>
</body>
</html>
