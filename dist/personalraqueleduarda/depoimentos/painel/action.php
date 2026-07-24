<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . painel_url() . '/');
    exit;
}

if (!csrf_ok($_POST['csrf'] ?? null)) {
    flash_set('error', 'Sessão expirada. Tente novamente.');
    header('Location: ' . painel_url() . '/');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$slug = (string) cfg('page_slug', 'personalraqueleduarda');
$filter = preg_replace('/[^a-z]/', '', (string) ($_POST['filter'] ?? 'pending')) ?: 'pending';

if ($id < 1 || !in_array($action, ['approve', 'hide', 'delete'], true)) {
    flash_set('error', 'Ação inválida.');
    header('Location: ' . painel_url() . '/?f=' . urlencode($filter));
    exit;
}

try {
    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM testimonials WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        flash_set('ok', 'Depoimento excluído.');
    } elseif ($action === 'approve') {
        $stmt = db()->prepare('UPDATE testimonials SET approved = 1 WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        flash_set('ok', 'Depoimento aprovado e visível no site.');
    } else {
        $stmt = db()->prepare('UPDATE testimonials SET approved = 0 WHERE id = ? AND page_slug = ? LIMIT 1');
        $stmt->execute([$id, $slug]);
        flash_set('ok', 'Depoimento ocultado do site.');
    }
} catch (Throwable $e) {
    flash_set('error', 'Não foi possível concluir a ação.');
}

header('Location: ' . painel_url() . '/?f=' . urlencode($filter));
exit;
