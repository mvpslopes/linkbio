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
        'SELECT id, name, photo_url, rating, comment, created_at
         FROM testimonials
         WHERE page_slug = ? AND approved = 1
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $stmt->execute([$slug]);
    $rows = $stmt->fetchAll();

    $items = array_map(static function (array $r): array {
        return [
            'id'         => (int) $r['id'],
            'name'       => $r['name'],
            'photo_url'  => $r['photo_url'],
            'rating'     => (int) $r['rating'],
            'comment'    => $r['comment'],
            'created_at' => $r['created_at'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao carregar depoimentos.', 'items' => []]);
}
