<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: transformacoes.php');
    exit;
}

if (!csrf_ok($_POST['csrf'] ?? null)) {
    flash_set('error', 'Sessão expirada. Tente novamente.');
    header('Location: transformacoes.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$slug = (string) cfg('page_slug', 'personalraqueleduarda');
$filter = preg_replace('/[^a-z]/', '', (string) ($_POST['filter'] ?? 'published')) ?: 'published';

if ($id < 1 || !in_array($action, ['publish', 'hide', 'delete'], true)) {
    flash_set('error', 'Ação inválida.');
    header('Location: transformacoes.php?f=' . urlencode($filter));
    exit;
}

try {
    if ($action === 'delete') {
        $sel = db()->prepare('SELECT image_path FROM transformations WHERE id = ? AND page_slug = ? LIMIT 1');
        $sel->execute([$id, $slug]);
        $path = $sel->fetchColumn();
        $stmt = db()->prepare('DELETE FROM transformations WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        if ($path) {
            raquel_ba_delete_image_file((string) $path);
        }
        flash_set('ok', 'Transformação excluída.');
    } elseif ($action === 'publish') {
        $stmt = db()->prepare('UPDATE transformations SET published = 1 WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        flash_set('ok', 'Transformação publicada no site.');
    } else {
        $stmt = db()->prepare('UPDATE transformations SET published = 0 WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        flash_set('ok', 'Transformação ocultada do site.');
    }
} catch (Throwable $e) {
    flash_set('error', 'Não foi possível concluir a ação.');
}

header('Location: transformacoes.php?f=' . urlencode($filter));
exit;
