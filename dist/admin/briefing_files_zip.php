<?php
/**
 * Baixa todos os arquivos de um briefing em ZIP (somente root).
 */
require_once __DIR__ . '/includes/auth.php';
require_root();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Bad request');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Extensão ZIP não disponível no servidor.');
}

$pdo = db();
$st = $pdo->prepare('SELECT nome, payload_json FROM briefing_submissions WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$p = json_decode($row['payload_json'], true);
$uploads = is_array($p['uploads'] ?? null) ? $p['uploads'] : [];
if ($uploads === []) {
    http_response_code(404);
    exit('Nenhum arquivo neste briefing.');
}

$dir = __DIR__ . '/uploads/briefing/' . $id;
$zipPath = sys_get_temp_dir() . '/briefing_' . $id . '_' . bin2hex(random_bytes(4)) . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Não foi possível criar o ZIP.');
}

$added = 0;
foreach ($uploads as $u) {
    if (!is_array($u)) {
        continue;
    }
    $stored = (string) ($u['stored'] ?? '');
    $name = (string) ($u['name'] ?? $stored);
    if ($stored === '') {
        continue;
    }
    $path = $dir . '/' . $stored;
    if (!is_file($path)) {
        continue;
    }
    $zip->addFile($path, $name);
    $added++;
}
$zip->close();

if ($added === 0) {
    @unlink($zipPath);
    http_response_code(404);
    exit('Arquivos não encontrados no servidor.');
}

$safeName = preg_replace('/[^a-z0-9\-]+/i', '-', trim((string) $row['nome']));
$safeName = $safeName !== '' ? $safeName : 'briefing';
$downloadName = 'briefing-' . $safeName . '-' . $id . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string) filesize($zipPath));
header('X-Content-Type-Options: nosniff');
readfile($zipPath);
@unlink($zipPath);
exit;
