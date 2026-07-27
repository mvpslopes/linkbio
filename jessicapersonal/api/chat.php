<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['error' => 'Assistente não configurado. Crie api/config.php.']);
    exit;
}

/** @var array $config */
$config = require $configPath;
$apiKey = trim((string) ($config['groq_api_key'] ?? ''));
if ($apiKey === '' || str_starts_with($apiKey, 'gsk_SUA_')) {
    http_response_code(503);
    echo json_encode(['error' => 'Chave Groq não configurada.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$limit = (int) ($config['rate_limit_per_hour'] ?? 40);
$bucket = $_SESSION['jessica_chat_bucket'] ?? ['t' => time(), 'n' => 0];
if (time() - (int) $bucket['t'] > 3600) {
    $bucket = ['t' => time(), 'n' => 0];
}
if ((int) $bucket['n'] >= $limit) {
    http_response_code(429);
    echo json_encode(['error' => 'Muitas mensagens. Tente novamente em alguns minutos.']);
    exit;
}
$bucket['n'] = (int) $bucket['n'] + 1;
$_SESSION['jessica_chat_bucket'] = $bucket;

$maxChars = (int) ($config['max_message_chars'] ?? 800);
$maxHistory = (int) ($config['max_history'] ?? 16);
$message = trim((string) ($payload['message'] ?? ''));
if ($message === '' || mb_strlen($message) > $maxChars) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem inválida.']);
    exit;
}

$historyIn = $payload['history'] ?? [];
if (!is_array($historyIn)) {
    $historyIn = [];
}

$history = [];
foreach (array_slice($historyIn, -$maxHistory) as $item) {
    if (!is_array($item)) {
        continue;
    }
    $role = (string) ($item['role'] ?? '');
    $content = trim((string) ($item['content'] ?? ''));
    if (($role !== 'user' && $role !== 'assistant') || $content === '') {
        continue;
    }
    if (mb_strlen($content) > $maxChars * 2) {
        $content = mb_substr($content, 0, $maxChars * 2);
    }
    $history[] = ['role' => $role, 'content' => $content];
}

$product = is_array($config['product'] ?? null) ? $config['product'] : [];
$productName = (string) ($product['name'] ?? 'MFIT Personal');
$tagline = (string) ($product['tagline'] ?? '');
$price = trim((string) ($product['price'] ?? ''));
$notes = trim((string) ($product['notes'] ?? ''));
$includes = $product['includes'] ?? [];
$includesText = '';
if (is_array($includes) && $includes) {
    $includesText = "- Inclui:\n  - " . implode("\n  - ", array_map('strval', $includes));
}

$wa = preg_replace('/\D+/', '', (string) ($config['whatsapp'] ?? '5531983955337')) ?: '5531983955337';
$priceLine = $price !== '' ? "Preço: {$price}" : 'Preço: a Jéssica informa no WhatsApp — não invente valores.';

$system = <<<SYS
Você é a assistente virtual do site da Jéssica Personal (Ouro Branco/MG, CREF 025935 G/MG). ID: linkbio_jessica.

Produto principal (MFIT Personal — acesso exclusivo de aluno):
- Nome: {$productName}
- {$tagline}
- {$priceLine}
{$includesText}
- Observações: {$notes}

O MFIT Personal é o app onde a aluna recebe treinos prescritos pela Jéssica, com vídeos, feedback e acompanhamento.

Objetivo: tirar dúvidas rápidas e conduzir a pessoa a fechar pelo WhatsApp (não há compra no site).

Regras de resposta (obrigatórias):
- Português do Brasil, tom amigável e direto.
- Respostas MUITO curtas: no máximo 1 ou 2 frases curtas (ideal ~40 palavras). Sem listas longas.
- Termine com uma pergunta objetiva quando fizer sentido.
- Não invente preço, prazo, bônus ou garantia.
- Não peça dados sensíveis (cartão, senha).
- Quando a pessoa quiser comprar, saber valor ou começar: diga para usar o botão "Continuar no WhatsApp".
- Nunca ofereça pagamento, QR Code ou checkout no site.
- Se não souber: diga que a Jéssica confirma no WhatsApp.
- Nunca mencione Groq, Llama ou que é IA; diga só que é assistente do site.
SYS;

$messages = array_merge(
    [['role' => 'system', 'content' => $system]],
    $history,
    [['role' => 'user', 'content' => $message]]
);

$body = json_encode([
    'model' => (string) ($config['groq_model'] ?? 'llama-3.3-70b-versatile'),
    'messages' => $messages,
    'temperature' => 0.5,
    'max_tokens' => 160,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
]);
$response = curl_exec($ch);
$errno = curl_errno($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno || $response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Falha ao contactar o assistente. Tente de novo.']);
    exit;
}

$data = json_decode($response, true);
if ($http >= 400 || !is_array($data)) {
    http_response_code(502);
    $hint = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
    echo json_encode([
        'error' => 'O assistente está indisponível no momento.',
        'detail' => $hint !== '' ? $hint : null,
    ]);
    exit;
}

$reply = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
if ($reply === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Resposta vazia do assistente.']);
    exit;
}

$waText = 'Olá, Jéssica! Falei com o assistente do site sobre o MFIT Personal e quero continuar / fechar.';
$waUrl = 'https://wa.me/' . $wa . '?text=' . rawurlencode($waText);

echo json_encode([
    'reply' => $reply,
    'actions' => [
        'whatsapp_url' => $waUrl,
    ],
], JSON_UNESCAPED_UNICODE);
