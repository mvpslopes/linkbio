<?php
// CORS: aceita domínio principal e subdomínios; com credenciais exige origem específica + Allow-Credentials
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $origin !== '' && preg_match('#^https?://([a-z0-9\-]+\.)?linkbio\.(app|api)\.br$#', $origin);
if ($allowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} elseif ($origin === '' || $origin === 'null') {
    // Arquivos locais ou origem null - não envia credenciais
    header('Access-Control-Allow-Origin: null');
} else {
    // Outras origens - permite sem credenciais
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit; }

require_once __DIR__ . '/../includes/db.php';

$body = json_decode(file_get_contents('php://input'), true);
$type = $body['type'] ?? '';
$slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($body['slug'] ?? ''));

if (!$slug) { echo json_encode(['ok' => false]); exit; }

// ── Detecta dispositivo ──────────────────────────────────────
function detect_device(string $ua): string {
    if (preg_match('/Mobile|Android|iPhone/i', $ua) && !preg_match('/iPad/i', $ua)) return 'mobile';
    if (preg_match('/Tablet|iPad/i', $ua)) return 'tablet';
    return 'desktop';
}

// ── GeoIP via ip-api.com (free, 45 req/min) ─────────────────
function get_geo(string $ip): array {
    if (empty($ip) || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.')) {
        return ['country' => null, 'city' => null];
    }
    $clean_ip = explode(',', $ip)[0];
    $clean_ip = trim($clean_ip);
    $url = "http://ip-api.com/json/{$clean_ip}?fields=status,country,city";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return ['country' => null, 'city' => null];
    $data = json_decode($res, true);
    if (($data['status'] ?? '') !== 'success') return ['country' => null, 'city' => null];
    return ['country' => $data['country'] ?? null, 'city' => $data['city'] ?? null];
}

$ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
$device = detect_device($ua);
$ip     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = hash('sha256', trim(explode(',', $ip)[0]));

// Browser e OS vindos do cliente (tracker.js já detecta)
$browser = substr($body['browser'] ?? '', 0, 50) ?: null;
$os      = substr($body['os']      ?? '', 0, 50) ?: null;

try {
    if ($type === 'pageview') {
        $ref = substr($body['referrer'] ?? '', 0, 500);

        // GeoIP
        $geo  = get_geo($ip);

        $stmt = db()->prepare(
            'INSERT INTO page_views (page_slug, ip_hash, referrer, device, browser, os, country, city)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $slug, $ipHash, $ref, $device,
            $browser, $os,
            $geo['country'], $geo['city'],
        ]);

    } elseif ($type === 'click') {
        $text   = substr($body['text']         ?? '', 0, 200);
        $elType = substr($body['element_type'] ?? '', 0, 50);
        $target = substr($body['target_url']   ?? '', 0, 500);
        $stmt   = db()->prepare(
            'INSERT INTO click_events (page_slug, element_text, element_type, target_url) VALUES (?,?,?,?)'
        );
        $stmt->execute([$slug, $text, $elType, $target]);
    }

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
