<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planeje_xlsx_writer.php';

$user = require_auth();
$isRoot = $user['role'] === 'root';
$pdo = db();

$slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['page'] ?? ''));
if ($slug !== 'cristianoladeira') {
    http_response_code(404);
    exit('Not found');
}
if (!$isRoot && ($user['page_slug'] ?? '') !== 'cristianoladeira') {
    http_response_code(403);
    exit('Forbidden');
}

$stmt = $pdo->prepare(
    'SELECT created_at, payload_json FROM planeje_submissions WHERE page_slug = ? ORDER BY created_at ASC'
);
$stmt->execute([$slug]);
$all = $stmt->fetchAll();

$headers = [
    'Data/hora',
    'Nome',
    'E-mail',
    'Telefone',
    'Por onde veio',
    'Haras/Empresa',
    'Baias',
    'Colaboradores',
    'Banheiro (sanitário + chuveiro)',
    'Cozinha',
    'Receptivo',
    'Segurança',
    'Limpeza periódica',
    'Demanda específica',
    'Identidade visual',
];

$dataRows = [];
foreach ($all as $row) {
    $p = json_decode($row['payload_json'], true);
    if (!is_array($p)) {
        $p = [];
    }
    $dataRows[] = [
        (string) ($row['created_at'] ?? ''),
        (string) ($p['nome'] ?? ''),
        (string) ($p['email'] ?? ''),
        (string) ($p['telefone'] ?? ''),
        (string) ($p['origem'] ?? ''),
        (string) ($p['harasEmpresa'] ?? ''),
        (string) ($p['baias'] ?? ''),
        (string) ($p['colaboradores'] ?? ''),
        (string) ($p['banheiro'] ?? ''),
        (string) ($p['cozinha'] ?? ''),
        (string) ($p['receptivo'] ?? ''),
        (string) ($p['seguranca'] ?? ''),
        (string) ($p['limpeza'] ?? ''),
        (string) ($p['demanda'] ?? ''),
        (string) ($p['identidade'] ?? ''),
    ];
}

try {
    $bytes = planeje_build_xlsx_bytes($headers, $dataRows);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"/><title>Exportação</title></head><body style="font-family:sans-serif;padding:2rem;">';
    echo '<p>Não foi possível gerar o Excel. Ative a extensão <strong>php-zip</strong> no servidor ou contate o suporte.</p>';
    echo '<p><a href="/admin/planeje_forms.php?page=' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">Voltar</a></p></body></html>';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="planeje_' . $slug . '_' . date('Y-m-d') . '.xlsx"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
