<?php
/**
 * Recebe POST JSON do formulário planeje-espaco.html (subdomínio cristianoladeira).
 * Campos: token, page_slug, nome, email, telefone, origem, harasEmpresa, ...
 * Token deve coincidir com PLANEJE_SUBMIT_TOKEN (env ou o mesmo valor do formulário).
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

$expected = getenv('PLANEJE_SUBMIT_TOKEN');
if ($expected === false || $expected === '') {
    $expected = 'N6&k9#zP2!mX5*qR8@vT1$yW4^bL7(jQ';
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json']);
    exit;
}

if (($body['token'] ?? '') !== $expected) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($body['page_slug'] ?? ''));
if ($slug !== 'cristianoladeira') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'slug']);
    exit;
}

$copy = $body;
unset($copy['token']);

$nome = substr(trim((string)($copy['nome'] ?? '')), 0, 255);
$email = substr(trim((string)($copy['email'] ?? '')), 0, 255);
$telefone = substr(trim((string)($copy['telefone'] ?? '')), 0, 100);
$origem = substr(trim((string)($copy['origem'] ?? '')), 0, 500);

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO planeje_submissions (page_slug, nome, email, telefone, origem, payload_json)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $slug,
        $nome ?: null,
        $email ?: null,
        $telefone ?: null,
        $origem ?: null,
        json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
}
