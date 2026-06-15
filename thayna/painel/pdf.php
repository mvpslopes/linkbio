<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_report.php';

$user = require_thayna_auth();
$pdo  = db();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit('Relatório não encontrado.');
}

$st = $pdo->prepare('SELECT * FROM thayna_relatorios WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    exit('Relatório não encontrado.');
}

try {
    if (ob_get_level()) {
        ob_end_clean();
    }

    $data = thayna_relatorio_prepare($row);
    $pdf = new ThaynaRelatorioPdf();
    $pdf->render($data);

    $filename = 'relatorio-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $row['codigo_caso']) . '.pdf';
    $pdf->Output('D', $filename);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('thayna pdf.php: ' . $e->getMessage());
    exit('Não foi possível gerar o PDF. Verifique se a pasta painel/lib/font foi enviada ao servidor.');
}
exit;
