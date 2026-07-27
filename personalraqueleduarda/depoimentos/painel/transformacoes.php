<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/nav.php';
require_once __DIR__ . '/includes/upload.php';
require_admin();

$slug = (string) cfg('page_slug', 'personalraqueleduarda');
$siteName = (string) cfg('site_name', 'Raquel Eduarda');
$mainUrl = rtrim((string) cfg('main_site_url', '/'), '/') . '/';
$adminUser = (string) ($_SESSION['testimonial_admin']['user'] ?? cfg('admin_user', 'admin'));
$flash = flash_get();
$csrf = csrf_token();

$filter = $_GET['f'] ?? 'published';
if (!in_array($filter, ['published', 'hidden', 'all'], true)) {
    $filter = 'published';
}

$rows = [];
$counts = ['published' => 0, 'hidden' => 0, 'all' => 0];

try {
    $c = db()->prepare(
        'SELECT
            SUM(CASE WHEN published = 1 THEN 1 ELSE 0 END) AS published,
            SUM(CASE WHEN published = 0 THEN 1 ELSE 0 END) AS hidden,
            COUNT(*) AS total
         FROM transformations WHERE page_slug = ?'
    );
    $c->execute([$slug]);
    $agg = $c->fetch() ?: [];
    $counts['published'] = (int) ($agg['published'] ?? 0);
    $counts['hidden'] = (int) ($agg['hidden'] ?? 0);
    $counts['all'] = (int) ($agg['total'] ?? 0);

    $sql = 'SELECT id, image_path, objetivo, perfil_label, perfil, resultado_em, sort_order, published, updated_at
            FROM transformations WHERE page_slug = ?';
    if ($filter === 'published') {
        $sql .= ' AND published = 1';
    } elseif ($filter === 'hidden') {
        $sql .= ' AND published = 0';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute([$slug]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $flash = ['type' => 'error', 'message' => 'Erro ao carregar. Confirme se a tabela transformations existe (SQL 14_transformations.sql).'];
}

$titles = [
    'published' => 'Publicadas no site',
    'hidden'    => 'Ocultas',
    'all'       => 'Todas as transformações',
];
$listTitle = $titles[$filter];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Painel · Transformações | <?= e($siteName) ?></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../../logo/icone-logo-branco.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= painel_css_href() ?>" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="side-brand">
        <img src="../../logo/icone-logo-branco.png" alt="" />
        <div>
          <strong><?= e($siteName) ?></strong>
          <span>Sistema interno</span>
        </div>
      </div>

      <?php painel_module_nav('transformacoes'); ?>

      <nav class="side-nav" aria-label="Filtros">
        <a class="<?= $filter === 'published' ? 'active' : '' ?>" href="?f=published">
          No site <span class="count"><?= (int) $counts['published'] ?></span>
        </a>
        <a class="<?= $filter === 'hidden' ? 'active' : '' ?>" href="?f=hidden">
          Ocultas <span class="count"><?= (int) $counts['hidden'] ?></span>
        </a>
        <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="?f=all">
          Todas <span class="count"><?= (int) $counts['all'] ?></span>
        </a>
      </nav>

      <div class="side-help">
        <strong>Como usar</strong>
        Cadastre a foto de antes/depois e os dados do card. Publique para aparecer no carrossel da home.
      </div>

      <div class="side-foot">
        <a href="<?= e($mainUrl) ?>#transformacoes" target="_blank" rel="noopener">↗ Ver no site</a>
        <a href="logout.php">Sair do painel</a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <p class="crumb">Painel · Antes e depois</p>
          <h1>Transformações</h1>
        </div>
        <div class="top-actions">
          <span class="chip"><span class="dot"></span> Online · <?= e($adminUser) ?></span>
          <a class="btn-top primary" href="transformacao.php">+ Nova</a>
          <a class="btn-top" href="<?= e($mainUrl) ?>#transformacoes" target="_blank" rel="noopener">Ver no site</a>
          <a class="btn-top danger" href="logout.php">Sair</a>
        </div>
      </header>

      <nav class="mobile-nav" aria-label="Filtros mobile">
        <a href="index.php">Depoimentos</a>
        <a class="active" href="transformacoes.php">Transformações</a>
        <a class="<?= $filter === 'published' ? 'active' : '' ?>" href="?f=published">No site (<?= (int) $counts['published'] ?>)</a>
        <a class="<?= $filter === 'hidden' ? 'active' : '' ?>" href="?f=hidden">Ocultas (<?= (int) $counts['hidden'] ?>)</a>
      </nav>

      <div class="content">
        <div class="stats">
          <div class="stat ok">
            <div class="label">Publicadas</div>
            <div class="value"><?= (int) $counts['published'] ?></div>
            <div class="hint">Visíveis no carrossel</div>
          </div>
          <div class="stat warn">
            <div class="label">Ocultas</div>
            <div class="value"><?= (int) $counts['hidden'] ?></div>
            <div class="hint">Salvas, fora do ar</div>
          </div>
          <div class="stat">
            <div class="label">Total</div>
            <div class="value"><?= (int) $counts['all'] ?></div>
            <div class="hint">No sistema</div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="toolbar">
          <div>
            <h2><?= e($listTitle) ?></h2>
            <div class="meta"><?= count($rows) ?> registro(s) · ordenados por posição</div>
          </div>
          <a class="btn btn-approve" href="transformacao.php">+ Nova transformação</a>
        </div>

        <section class="panel">
          <div class="panel-head">
            <div>
              <div class="title">Galeria</div>
              <div class="sub">Foto + objetivo, perfil e tempo de resultado</div>
            </div>
            <div class="sub">Ordem · ações</div>
          </div>

          <?php if (!$rows): ?>
            <div class="empty">
              <strong>Nenhuma transformação aqui</strong>
              Clique em “Nova transformação” para cadastrar a primeira.
            </div>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $published = (int) $row['published'] === 1;
                $img = raquel_ba_public_url((string) $row['image_path']) ?: '';
                $when = date('d/m/Y H:i', strtotime((string) $row['updated_at']));
              ?>
              <article class="row ba-row">
                <div>
                  <?php if ($img): ?>
                    <img class="ba-thumb" src="<?= e($img) ?>" alt="" />
                  <?php else: ?>
                    <div class="avatar-fallback">?</div>
                  <?php endif; ?>
                </div>
                <div class="who">
                  <strong>
                    <?= e($row['objetivo']) ?>
                    <span class="badge <?= $published ? 'ok' : 'wait' ?>"><?= $published ? 'No site' : 'Oculta' ?></span>
                  </strong>
                  <div class="meta">
                    #<?= (int) $row['id'] ?> · ordem <?= (int) $row['sort_order'] ?> · <?= e($when) ?><br>
                    <?= e($row['perfil_label']) ?>: <?= e($row['perfil']) ?> · <?= e($row['resultado_em']) ?>
                  </div>
                </div>
                <div class="actions">
                  <a class="btn btn-hide" href="transformacao.php?id=<?= (int) $row['id'] ?>">Editar</a>
                  <?php if (!$published): ?>
                    <form method="post" action="transformacao-action.php">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                      <input type="hidden" name="action" value="publish" />
                      <input type="hidden" name="filter" value="<?= e($filter) ?>" />
                      <button type="submit" class="btn btn-approve">Publicar</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="transformacao-action.php">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>" />
                      <input type="hidden" name="action" value="hide" />
                      <input type="hidden" name="filter" value="<?= e($filter) ?>" />
                      <button type="submit" class="btn btn-hide">Ocultar</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="transformacao-action.php" onsubmit="return confirm('Excluir esta transformação definitivamente?');">
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
      </div>
    </div>
  </div>
</body>
</html>
