<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$slug = (string) cfg('page_slug', 'personalraqueleduarda');
$siteName = (string) cfg('site_name', 'Raquel Eduarda');
$mainUrl = rtrim((string) cfg('main_site_url', '/'), '/') . '/';
$adminUser = (string) ($_SESSION['testimonial_admin']['user'] ?? cfg('admin_user', 'admin'));
$flash = flash_get();
$csrf = csrf_token();

$filter = $_GET['f'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'all'], true)) {
    $filter = 'pending';
}

$rows = [];
$counts = ['pending' => 0, 'approved' => 0, 'all' => 0];
$avgRating = 0.0;

try {
    $c = db()->prepare(
        'SELECT
            SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS approved,
            COUNT(*) AS total,
            AVG(CASE WHEN approved = 1 THEN rating END) AS avg_rating
         FROM testimonials WHERE page_slug = ?'
    );
    $c->execute([$slug]);
    $agg = $c->fetch() ?: [];
    $counts['pending'] = (int) ($agg['pending'] ?? 0);
    $counts['approved'] = (int) ($agg['approved'] ?? 0);
    $counts['all'] = (int) ($agg['total'] ?? 0);
    $avgRating = round((float) ($agg['avg_rating'] ?? 0), 1);

    $sql = 'SELECT id, name, email, photo_url, rating, comment, approved, created_at
            FROM testimonials WHERE page_slug = ?';
    $params = [$slug];
    if ($filter === 'pending') {
        $sql .= ' AND approved = 0';
    } elseif ($filter === 'approved') {
        $sql .= ' AND approved = 1';
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $flash = ['type' => 'error', 'message' => 'Erro ao carregar depoimentos. Confirme se a tabela existe.'];
}

function stars_label(int $n): string {
    return str_repeat('★', max(0, min(5, $n))) . str_repeat('☆', max(0, 5 - min(5, $n)));
}

$titles = [
    'pending'  => 'Pendentes de aprovação',
    'approved' => 'Publicados no site',
    'all'      => 'Todos os depoimentos',
];
$listTitle = $titles[$filter];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Painel · Depoimentos | <?= e($siteName) ?></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../../logo/icone-logo-branco.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/painel.css" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="side-brand">
        <img src="../../logo/icone-logo-branco.png" alt="" />
        <div>
          <strong><?= e($siteName) ?></strong>
          <span>Sistema de depoimentos</span>
        </div>
      </div>

      <nav class="side-nav" aria-label="Filtros">
        <a class="<?= $filter === 'pending' ? 'active' : '' ?>" href="?f=pending">
          Pendentes <span class="count"><?= (int) $counts['pending'] ?></span>
        </a>
        <a class="<?= $filter === 'approved' ? 'active' : '' ?>" href="?f=approved">
          No site <span class="count"><?= (int) $counts['approved'] ?></span>
        </a>
        <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="?f=all">
          Todos <span class="count"><?= (int) $counts['all'] ?></span>
        </a>
      </nav>

      <div class="side-help">
        <strong>Como usar</strong>
        Aprove para publicar no site. Oculte para tirar do ar sem apagar. Exclua só quando quiser remover de vez.
      </div>

      <div class="side-foot">
        <a href="<?= e($mainUrl) ?>#depoimentos" target="_blank" rel="noopener">↗ Abrir site público</a>
        <a href="<?= e($mainUrl) ?>depoimentos/" target="_blank" rel="noopener">↗ Página de envio</a>
        <a href="logout.php">Sair do painel</a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <p class="crumb">Painel · Moderação</p>
          <h1>Depoimentos</h1>
        </div>
        <div class="top-actions">
          <span class="chip"><span class="dot"></span> Online · <?= e($adminUser) ?></span>
          <a class="btn-top" href="<?= e($mainUrl) ?>#depoimentos" target="_blank" rel="noopener">Ver no site</a>
          <a class="btn-top danger" href="logout.php">Sair</a>
        </div>
      </header>

      <nav class="mobile-nav" aria-label="Filtros mobile">
        <a class="<?= $filter === 'pending' ? 'active' : '' ?>" href="?f=pending">Pendentes (<?= (int) $counts['pending'] ?>)</a>
        <a class="<?= $filter === 'approved' ? 'active' : '' ?>" href="?f=approved">No site (<?= (int) $counts['approved'] ?>)</a>
        <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="?f=all">Todos (<?= (int) $counts['all'] ?>)</a>
      </nav>

      <div class="content">
        <div class="stats">
          <div class="stat warn">
            <div class="label">Aguardando</div>
            <div class="value"><?= (int) $counts['pending'] ?></div>
            <div class="hint">Precisam da sua revisão</div>
          </div>
          <div class="stat ok">
            <div class="label">Publicados</div>
            <div class="value"><?= (int) $counts['approved'] ?></div>
            <div class="hint">Visíveis no carrossel</div>
          </div>
          <div class="stat">
            <div class="label">Média no site</div>
            <div class="value"><?= $avgRating > 0 ? e(number_format($avgRating, 1, ',', '')) : '—' ?></div>
            <div class="hint">Avaliação média (estrelas)</div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="toolbar">
          <div>
            <h2><?= e($listTitle) ?></h2>
            <div class="meta"><?= count($rows) ?> registro(s) nesta lista · máx. 200</div>
          </div>
        </div>

        <section class="panel">
          <div class="panel-head">
            <div>
              <div class="title">Fila de moderação</div>
              <div class="sub">Nome, e-mail e foto vêm da conta Google do aluno</div>
            </div>
            <div class="sub">ID · data · ações rápidas</div>
          </div>

          <?php if (!$rows): ?>
            <div class="empty">
              <strong>Nada por aqui</strong>
              <?php if ($filter === 'pending'): ?>
                Quando alguém enviar um depoimento, ele aparece nesta fila para aprovação.
              <?php else: ?>
                Não há depoimentos neste filtro no momento.
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $approved = (int) $row['approved'] === 1;
                $initial = mb_strtoupper(mb_substr((string) $row['name'], 0, 1));
                $when = date('d/m/Y H:i', strtotime((string) $row['created_at']));
              ?>
              <article class="row">
                <div>
                  <?php if (!empty($row['photo_url'])): ?>
                    <img src="<?= e($row['photo_url']) ?>" alt="" referrerpolicy="no-referrer" />
                  <?php else: ?>
                    <div class="avatar-fallback"><?= e($initial) ?></div>
                  <?php endif; ?>
                </div>
                <div class="who">
                  <strong>
                    <?= e($row['name']) ?>
                    <span class="badge <?= $approved ? 'ok' : 'wait' ?>"><?= $approved ? 'No site' : 'Pendente' ?></span>
                  </strong>
                  <div class="meta">
                    #<?= (int) $row['id'] ?> · <?= e($when) ?>
                    <?php if (!empty($row['email'])): ?><br><?= e($row['email']) ?><?php endif; ?>
                  </div>
                </div>
                <div>
                  <div class="stars"><?= e(stars_label((int) $row['rating'])) ?> · <?= (int) $row['rating'] ?>/5</div>
                  <p class="comment"><?= e($row['comment']) ?></p>
                </div>
                <div class="actions">
                  <?php if (!$approved): ?>
                    <form method="post" action="action.php">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                      <input type="hidden" name="action" value="approve" />
                      <input type="hidden" name="filter" value="<?= e($filter) ?>" />
                      <button type="submit" class="btn btn-approve">Aprovar</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="action.php">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                      <input type="hidden" name="action" value="hide" />
                      <input type="hidden" name="filter" value="<?= e($filter) ?>" />
                      <button type="submit" class="btn btn-hide">Ocultar</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="action.php" onsubmit="return confirm('Excluir este depoimento definitivamente?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="filter" value="<?= e($filter) ?>" />
                    <button type="submit" class="btn btn-delete">Excluir</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <div class="guide">
          <div class="guide-card">
            <strong>1. Aprovar</strong>
            Publica o comentário no carrossel da home com nome e foto do Google.
          </div>
          <div class="guide-card">
            <strong>2. Ocultar</strong>
            Remove do site, mas mantém no sistema caso queira republicar depois.
          </div>
          <div class="guide-card">
            <strong>3. Excluir</strong>
            Apaga permanentemente do banco. Use só em spam ou conteúdo inadequado.
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
