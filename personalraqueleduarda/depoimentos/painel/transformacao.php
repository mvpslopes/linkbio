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
$csrf = csrf_token();

$id = (int) ($_GET['id'] ?? 0);
$row = null;
$error = '';

if ($id > 0) {
    try {
        $stmt = db()->prepare(
            'SELECT id, image_path, objetivo, perfil_label, perfil, resultado_em, sort_order, published
             FROM transformations WHERE id = ? AND page_slug = ? LIMIT 1'
        );
        $stmt->execute([$id, $slug]);
        $row = $stmt->fetch() ?: null;
        if (!$row) {
            flash_set('error', 'Transformação não encontrada.');
            header('Location: transformacoes.php');
            exit;
        }
    } catch (Throwable $e) {
        flash_set('error', 'Erro ao carregar registro.');
        header('Location: transformacoes.php');
        exit;
    }
}

$form = [
    'objetivo'     => (string) ($row['objetivo'] ?? ''),
    'perfil_label' => (string) ($row['perfil_label'] ?? 'Perfil da Aluna'),
    'perfil'       => (string) ($row['perfil'] ?? ''),
    'resultado_em' => (string) ($row['resultado_em'] ?? ''),
    'sort_order'   => (string) ($row['sort_order'] ?? '0'),
    'published'    => (int) ($row['published'] ?? 1),
    'image_path'   => (string) ($row['image_path'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $form['objetivo'] = trim((string) ($_POST['objetivo'] ?? ''));
        $form['perfil_label'] = trim((string) ($_POST['perfil_label'] ?? 'Perfil'));
        $form['perfil'] = trim((string) ($_POST['perfil'] ?? ''));
        $form['resultado_em'] = trim((string) ($_POST['resultado_em'] ?? ''));
        $form['sort_order'] = (string) ((int) ($_POST['sort_order'] ?? 0));
        $form['published'] = !empty($_POST['published']) ? 1 : 0;

        try {
            if ($form['objetivo'] === '' || mb_strlen($form['objetivo']) > 120) {
                throw new RuntimeException('Informe o objetivo (até 120 caracteres).');
            }
            if ($form['perfil_label'] === '' || mb_strlen($form['perfil_label']) > 80) {
                throw new RuntimeException('Informe o rótulo do perfil.');
            }
            if ($form['perfil'] === '' || mb_strlen($form['perfil']) > 120) {
                throw new RuntimeException('Informe o perfil.');
            }
            if ($form['resultado_em'] === '' || mb_strlen($form['resultado_em']) > 80) {
                throw new RuntimeException('Informe o tempo do resultado (ex.: 4 meses).');
            }

            $imagePath = raquel_ba_normalize_path($form['image_path']);
            $hasNewUpload = !empty($_FILES['image']['name']);

            if ($hasNewUpload) {
                $newPath = raquel_ba_store_image($_FILES['image']);
                if ($imagePath && $imagePath !== $newPath) {
                    raquel_ba_delete_image_file($imagePath);
                }
                $imagePath = $newPath;
            }

            if (!$imagePath) {
                throw new RuntimeException('Envie a foto de antes e depois.');
            }

            if ($id > 0) {
                $upd = db()->prepare(
                    'UPDATE transformations
                     SET image_path = ?, objetivo = ?, perfil_label = ?, perfil = ?, resultado_em = ?,
                         sort_order = ?, published = ?
                     WHERE id = ? AND page_slug = ? LIMIT 1'
                );
                $upd->execute([
                    $imagePath,
                    $form['objetivo'],
                    $form['perfil_label'],
                    $form['perfil'],
                    $form['resultado_em'],
                    (int) $form['sort_order'],
                    $form['published'],
                    $id,
                    $slug,
                ]);
                flash_set('ok', 'Transformação atualizada.');
            } else {
                $ins = db()->prepare(
                    'INSERT INTO transformations
                      (page_slug, image_path, objetivo, perfil_label, perfil, resultado_em, sort_order, published)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $slug,
                    $imagePath,
                    $form['objetivo'],
                    $form['perfil_label'],
                    $form['perfil'],
                    $form['resultado_em'],
                    (int) $form['sort_order'],
                    $form['published'],
                ]);
                flash_set('ok', 'Transformação cadastrada.');
            }

            header('Location: transformacoes.php?f=' . ($form['published'] ? 'published' : 'hidden'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage() ?: 'Não foi possível salvar.';
            $form['image_path'] = $imagePath ?? $form['image_path'];
        }
    }
}

$pageTitle = $id > 0 ? 'Editar transformação' : 'Nova transformação';
$preview = raquel_ba_public_url($form['image_path']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($pageTitle) ?> | <?= e($siteName) ?></title>
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
      <div class="side-help">
        <strong>Dica</strong>
        Use uma foto única com antes e depois lado a lado. JPG ou PNG até 5 MB.
      </div>
      <div class="side-foot">
        <a href="transformacoes.php">← Voltar à lista</a>
        <a href="logout.php">Sair do painel</a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <p class="crumb">Painel · Transformações</p>
          <h1><?= e($pageTitle) ?></h1>
        </div>
        <div class="top-actions">
          <span class="chip"><span class="dot"></span> <?= e($adminUser) ?></span>
          <a class="btn-top" href="transformacoes.php">Cancelar</a>
        </div>
      </header>

      <div class="content">
        <?php if ($error): ?>
          <div class="flash error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="form-card" method="post" enctype="multipart/form-data" action="">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />

          <div class="form-grid">
            <div class="field full">
              <label for="image">Foto (antes e depois)</label>
              <?php if ($preview): ?>
                <div class="preview-wrap">
                  <img src="<?= e($preview) ?>" alt="Pré-visualização" class="preview-img" />
                </div>
              <?php endif; ?>
              <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $id ? '' : 'required' ?> />
              <p class="hint"><?= $id ? 'Deixe em branco para manter a foto atual.' : 'Obrigatório no cadastro.' ?> Máx. 5 MB.</p>
            </div>

            <div class="field">
              <label for="objetivo">Objetivo</label>
              <input type="text" id="objetivo" name="objetivo" maxlength="120" required
                     value="<?= e($form['objetivo']) ?>" placeholder="Ex.: Emagrecimento" />
            </div>

            <div class="field">
              <label for="resultado_em">Resultado em</label>
              <input type="text" id="resultado_em" name="resultado_em" maxlength="80" required
                     value="<?= e($form['resultado_em']) ?>" placeholder="Ex.: 4 meses" />
            </div>

            <div class="field">
              <label for="perfil_label">Rótulo do perfil</label>
              <input type="text" id="perfil_label" name="perfil_label" maxlength="80" required
                     value="<?= e($form['perfil_label']) ?>" placeholder="Ex.: Perfil da Aluna" />
            </div>

            <div class="field">
              <label for="perfil">Perfil</label>
              <input type="text" id="perfil" name="perfil" maxlength="120" required
                     value="<?= e($form['perfil']) ?>" placeholder="Ex.: Lipedema" />
            </div>

            <div class="field">
              <label for="sort_order">Ordem no carrossel</label>
              <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                     value="<?= e($form['sort_order']) ?>" />
              <p class="hint">Menor número aparece primeiro.</p>
            </div>

            <div class="field">
              <label class="check-label">
                <input type="checkbox" name="published" value="1" <?= $form['published'] ? 'checked' : '' ?> />
                Publicar no site
              </label>
            </div>
          </div>

          <div class="form-actions">
            <a class="btn btn-hide" href="transformacoes.php">Cancelar</a>
            <button type="submit" class="btn btn-approve"><?= $id ? 'Salvar alterações' : 'Cadastrar' ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
