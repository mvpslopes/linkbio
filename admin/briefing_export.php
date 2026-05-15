<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planeje_xlsx_writer.php';

require_root();
$pdo = db();

$stmt = $pdo->query(
    'SELECT created_at, nome, email, telefone, subdominio_desejado, origem, payload_json
     FROM briefing_submissions ORDER BY created_at ASC'
);
$all = $stmt->fetchAll();

$headers = [
    'Data/hora',
    'Nome',
    'E-mail',
    'Telefone',
    'Subdomínio desejado',
    'Por onde veio',
    'Profissão / nicho',
    'Cidade',
    'Estado',
    'Instagram',
    'Headline',
    'Subtítulo',
    'Sobre',
    'Diferencial',
    'Serviços',
    'Formação / credenciais',
    'FAQ',
    'Texto do botão',
    'Mensagem WhatsApp',
    'Links extras',
    'Cores / visual',
    'Referências',
    'Link mídia (Drive etc.)',
    'Arquivos enviados',
    'Seções desejadas',
    'Observações',
];

$dataRows = [];
foreach ($all as $row) {
    $p = json_decode($row['payload_json'], true);
    if (!is_array($p)) {
        $p = [];
    }
    $secoes = $p['secoes'] ?? [];
    if (is_array($secoes)) {
        $secoes = implode(', ', $secoes);
    }
    $dataRows[] = [
        (string) ($row['created_at'] ?? ''),
        (string) ($row['nome'] ?? $p['nome'] ?? ''),
        (string) ($row['email'] ?? $p['email'] ?? ''),
        (string) ($row['telefone'] ?? $p['telefone'] ?? ''),
        (string) ($row['subdominio_desejado'] ?? $p['subdominio'] ?? ''),
        (string) ($row['origem'] ?? $p['origem'] ?? ''),
        (string) ($p['profissao'] ?? ''),
        (string) ($p['cidade'] ?? ''),
        (string) ($p['estado'] ?? ''),
        (string) ($p['instagram'] ?? ''),
        (string) ($p['headline'] ?? ''),
        (string) ($p['subtitulo'] ?? ''),
        (string) ($p['sobre'] ?? ''),
        (string) ($p['diferencial'] ?? ''),
        (string) ($p['servicos'] ?? ''),
        (string) ($p['formacao'] ?? ''),
        (string) ($p['faq'] ?? ''),
        (string) ($p['cta_texto'] ?? ''),
        (string) ($p['whatsapp_msg'] ?? ''),
        (string) ($p['links_extras'] ?? ''),
        (string) ($p['cores'] ?? ''),
        (string) ($p['referencias'] ?? ''),
        (string) ($p['midia_link'] ?? ''),
        (function () use ($p) {
            $ups = $p['uploads'] ?? [];
            if (!is_array($ups) || $ups === []) {
                return '';
            }
            $names = [];
            foreach ($ups as $u) {
                if (is_array($u) && !empty($u['name'])) {
                    $names[] = (string) $u['name'];
                }
            }

            return implode(', ', $names);
        })(),
        (string) $secoes,
        (string) ($p['observacoes'] ?? ''),
    ];
}

try {
    $bytes = planeje_build_xlsx_bytes($headers, $dataRows);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"/><title>Exportação</title></head><body style="font-family:sans-serif;padding:2rem;">';
    echo '<p>Não foi possível gerar o Excel. Ative a extensão <strong>php-zip</strong> no servidor.</p>';
    echo '<p><a href="/admin/briefing_forms.php">Voltar</a></p></body></html>';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="briefings_linkbio_' . date('Y-m-d') . '.xlsx"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
