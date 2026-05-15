<?php
/**
 * Recebe POST do formulário criar.linkbio.api.br
 * JSON (application/json) ou multipart (FormData + arquivos opcionais).
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $origin !== '' && preg_match('#^https?://([a-z0-9\-]+\.)?linkbio\.(app|api)\.br$#', $origin);
if ($allowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/briefing_upload.php';

$expected = getenv('BRIEFING_SUBMIT_TOKEN');
if ($expected === false || $expected === '') {
    $expected = 'Lb8#kCriar2026!mX9@briefing$vT2';
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$filesInput = null;

if (stripos($contentType, 'multipart/form-data') !== false) {
    $body = $_POST;
    if (isset($body['secoes']) && is_string($body['secoes'])) {
        $decoded = json_decode($body['secoes'], true);
        $body['secoes'] = is_array($decoded) ? $decoded : [];
    }
    $body['lgpd_consent'] = !empty($body['lgpd_consent'])
        && !in_array((string) $body['lgpd_consent'], ['0', 'false', ''], true);
    $filesInput = $_FILES['arquivos'] ?? null;
} else {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'json']);
        exit;
    }
}

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'body']);
    exit;
}

if (($body['token'] ?? '') !== $expected) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

if (trim((string) ($body['website'] ?? '')) !== '') {
    echo json_encode(['ok' => true, 'id' => 0]);
    exit;
}

$copy = $body;
unset($copy['token'], $copy['website']);

$nome = substr(trim((string) ($copy['nome'] ?? '')), 0, 255);
$email = substr(trim((string) ($copy['email'] ?? '')), 0, 255);
$telefone = substr(trim((string) ($copy['telefone'] ?? '')), 0, 100);
$origem = substr(trim((string) ($copy['origem'] ?? '')), 0, 500);
$subdominio = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string) ($copy['subdominio'] ?? ''))));
$subdominio = substr($subdominio, 0, 80) ?: null;

if ($nome === '' || ($email === '' && $telefone === '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'required']);
    exit;
}

if (empty($copy['lgpd_consent'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'consent']);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO briefing_submissions (nome, email, telefone, subdominio_desejado, origem, payload_json)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $nome,
        $email ?: null,
        $telefone ?: null,
        $subdominio,
        $origem ?: null,
        json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
    $id = (int) $pdo->lastInsertId();

    $uploads = briefing_process_uploads($id, $filesInput);
    if ($uploads !== []) {
        $copy['uploads'] = $uploads;
        $upd = $pdo->prepare('UPDATE briefing_submissions SET payload_json = ? WHERE id = ? LIMIT 1');
        $upd->execute([
            json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $id,
        ]);
    }

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'files' => count($uploads),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
}
