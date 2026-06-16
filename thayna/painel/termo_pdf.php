<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_termo.php';

$user = require_thayna_auth();
$pdo  = db();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit('Cliente nao encontrado.');
}

$st = $pdo->prepare('SELECT * FROM thayna_clientes WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || empty($row['assinado_em'])) {
    http_response_code(404);
    exit('Termo ainda nao assinado.');
}

try {
    if (ob_get_level()) {
        ob_end_clean();
    }
    [$cliente, $questionario] = thayna_termo_prepare_cliente($row);
    $pdf = new ThaynaTermoPdf();
    $pdf->render($cliente, $questionario);
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $row['nome_completo']);
    $pdf->Output('D', 'termo-' . $slug . '.pdf');
} catch (Throwable $e) {
    http_response_code(500);
    error_log('thayna termo_pdf.php: ' . $e->getMessage());
    exit('Nao foi possivel gerar o PDF.');
}
exit;
