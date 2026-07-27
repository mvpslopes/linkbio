<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $origin !== '' && preg_match('#^https?://([a-z0-9\-]+\.)?linkbio\.(app|api)\.br$#', $origin);
if ($allowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif ($origin === '' || $origin === 'null') {
    header('Access-Control-Allow-Origin: null');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

require_once dirname(__DIR__, 2) . '/admin/includes/db.php';

$slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['slug'] ?? 'personalraqueleduarda'));
if ($slug === '') {
    $slug = 'personalraqueleduarda';
}

try {
    $stmt = db()->prepare(
        'SELECT id, image_path, objetivo, perfil_label, perfil, resultado_em, sort_order
         FROM transformations
         WHERE page_slug = ? AND published = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 100'
    );
    $stmt->execute([$slug]);
    $rows = $stmt->fetchAll();

    $items = array_map(static function (array $r): array {
        $path = str_replace('\\', '/', (string) $r['image_path']);
        if (!preg_match('#^antes-depois/[a-zA-Z0-9._-]+$#', $path)) {
            $path = '';
        }
        return [
            'id'           => (int) $r['id'],
            'image_url'    => $path !== '' ? '/' . $path : '',
            'objetivo'     => $r['objetivo'],
            'perfil_label' => $r['perfil_label'],
            'perfil'       => $r['perfil'],
            'resultado_em' => $r['resultado_em'],
            'sort_order'   => (int) $r['sort_order'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao carregar transformações.', 'items' => []]);
}
