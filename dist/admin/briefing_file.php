<?php
/**
 * Download de arquivo enviado no briefing (somente root).
 * GET: id, f (nome armazenado)
 */
require_once __DIR__ . '/includes/auth.php';
require_root();

$id = (int) ($_GET['id'] ?? 0);
$stored = basename((string) ($_GET['f'] ?? ''));
if ($id <= 0 || $stored === '' || preg_match('/[^a-zA-Z0-9._\-]/', $stored)) {
    http_response_code(400);
    exit('Bad request');
}

$pdo = db();
$st = $pdo->prepare('SELECT payload_json FROM briefing_submissions WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$p = json_decode($row['payload_json'], true);
$uploads = is_array($p['uploads'] ?? null) ? $p['uploads'] : [];
$meta = null;
foreach ($uploads as $u) {
    if (is_array($u) && ($u['stored'] ?? '') === $stored) {
        $meta = $u;
        break;
    }
}
if ($meta === null) {
    http_response_code(404);
    exit('File not registered');
}

$path = __DIR__ . '/uploads/briefing/' . $id . '/' . $stored;
if (!is_file($path)) {
    http_response_code(404);
    exit('File missing');
}

$mime = (string) ($meta['mime'] ?? 'application/octet-stream');
$name = (string) ($meta['name'] ?? $stored);
$inline = !empty($_GET['inline']) && str_starts_with($mime, 'image/');

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
