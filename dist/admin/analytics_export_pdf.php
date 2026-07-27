<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics_data.php';
require_once __DIR__ . '/includes/lib/fpdf.php';

$user   = require_auth();
$isRoot = $user['role'] === 'root';
$pdo    = db();

$selected = preg_replace('/[^a-z0-9_\-]/', '', (string) ($_GET['page'] ?? ''));
if ($selected === '') {
    $selected = (string) ($user['page_slug'] ?? '');
}
if (!$isRoot) {
    $selected = (string) $user['page_slug'];
}
if ($selected === '') {
    http_response_code(400);
    exit('Cliente não informado.');
}

$period = (string) ($_GET['period'] ?? '7d');
$data = analytics_load($pdo, $selected, $period);
$period = $data['period'];

// Resolve display name
$slugName = $selected;
if ($isRoot) {
    $st = $pdo->prepare("SELECT name FROM users WHERE role='client' AND page_slug=? LIMIT 1");
    $st->execute([$selected]);
    $nm = $st->fetchColumn();
    if ($nm) {
        $slugName = (string) $nm;
    }
} else {
    $slugName = (string) ($user['username'] ?: $selected);
}

$pageUrl = 'https://' . $selected . '.linkbio.api.br';
$generatedAt = date('d/m/Y H:i');
$k = $data['kpis'];

function pdf_txt(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\u{2014}", "\u{2013}", "\u{2022}", "\u{221E}"], ['-', '-', '*', 'inf'], $s);
    if (function_exists('iconv')) {
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        if ($out !== false) {
            return $out;
        }
    }
    return preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $s) ?: $s;
}

function pdf_logo_path(): ?string
{
    // Preferir logo colorida (visível em fundo branco do PDF)
    $candidates = [
        dirname(__DIR__) . '/logo/logo-link-bio-1.png',
        dirname(__DIR__) . '/logo/new/logo-link-bio_new-1.png',
        __DIR__ . '/../logo/logo-link-bio-1.png',
        dirname(__DIR__) . '/logo/logo-link-bio-2.png',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

class LinkBioAnalyticsPdf extends FPDF
{
    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, pdf_txt('Gerado por LinkBio · linkbio.api.br'), 0, 0, 'L');
        $this->Cell(0, 5, pdf_txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    public function sectionTitle(string $title): void
    {
        $this->Ln(3);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetFillColor(29, 78, 216);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, '  ' . pdf_txt($title), 0, 1, 'L', true);
        $this->SetTextColor(30, 41, 59);
        $this->Ln(2);
    }

    public function kpiBox(float $x, float $y, float $w, float $h, string $label, string $value, string $trend): void
    {
        $this->SetXY($x, $y);
        $this->SetDrawColor(226, 232, 240);
        $this->SetFillColor(248, 250, 252);
        $this->RoundedRectCompat($x, $y, $w, $h, 2, 'DF');
        $this->SetXY($x + 3, $y + 2.5);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell($w - 6, 4, pdf_txt($label), 0, 2, 'L');
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(15, 23, 42);
        $this->Cell($w - 6, 7, pdf_txt($value), 0, 2, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(71, 85, 105);
        $this->Cell($w - 6, 4, pdf_txt($trend), 0, 0, 'L');
    }

    /** Fallback if RoundedRect not available in core FPDF */
    public function RoundedRectCompat(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $this->Rect($x, $y, $w, $h, $style);
    }

    public function tableHeader(array $cols, array $widths): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(51, 65, 85);
        $this->SetDrawColor(226, 232, 240);
        foreach ($cols as $i => $col) {
            $this->Cell($widths[$i], 7, pdf_txt($col), 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Helvetica', '', 8);
    }

    public function tableRow(array $cells, array $widths, bool $alt = false): void
    {
        if ($alt) {
            $this->SetFillColor(248, 250, 252);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        foreach ($cells as $i => $cell) {
            $align = ($i === count($cells) - 1 && is_numeric(str_replace(['.', ',', '%', '+', '-'], '', (string) $cell))) ? 'R' : 'L';
            if ($i > 0) {
                $align = 'R';
            }
            if ($i === 0) {
                $align = 'L';
            }
            $this->Cell($widths[$i], 6.2, pdf_txt((string) $cell), 1, 0, $align, true);
        }
        $this->Ln();
    }
}

$pdf = new LinkBioAnalyticsPdf('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// Header: título à esquerda + logo colorida à direita
$logo = pdf_logo_path();
$pdf->SetXY(14, 12);
$pdf->SetFont('Helvetica', 'B', 16);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(120, 7, pdf_txt('Relatório de Analytics'), 0, 2, 'L');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(120, 5, pdf_txt('LinkBio · Painel de desempenho'), 0, 1, 'L');

if ($logo) {
    // Canto superior direito (A4 width ~210mm, margem direita 14)
    $logoW = 42;
    $pdf->Image($logo, 196 - $logoW, 9, $logoW);
}

$pdf->SetY(28);
$pdf->SetDrawColor(29, 78, 216);
$pdf->SetLineWidth(0.5);
$pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
$pdf->Ln(5);

// Client meta
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, pdf_txt($slugName), 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(0, 5, pdf_txt($pageUrl), 0, 1, 'L');
$pdf->Cell(0, 5, pdf_txt('Período: ' . $data['period_label'] . '  ·  Gerado em: ' . $generatedAt), 0, 1, 'L');
$pdf->Ln(3);

// KPIs
$pdf->sectionTitle('Indicadores');
$y = $pdf->GetY();
$boxW = 44;
$gap = 3;
$pdf->kpiBox(14, $y, $boxW, 22, 'Visitantes únicos', number_format($k['uniq_visitors'], 0, ',', '.'), (string) $k['trend_visitors']);
$pdf->kpiBox(14 + ($boxW + $gap), $y, $boxW, 22, 'Visualizações', number_format($k['total_views'], 0, ',', '.'), (string) $k['trend_views']);
$pdf->kpiBox(14 + 2 * ($boxW + $gap), $y, $boxW, 22, 'Cliques', number_format($k['total_clicks'], 0, ',', '.'), (string) $k['trend_clicks']);
$pdf->kpiBox(14 + 3 * ($boxW + $gap), $y, $boxW, 22, 'Conversão', $k['conv_rate'] . '%', (string) $k['trend_conv']);
$pdf->SetY($y + 26);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(0, 5, pdf_txt(
    'Online agora: ' . $k['online_now']
    . '  ·  Último acesso: ' . $k['last_visit_formatted']
    . '  ·  Views/visitante: ' . $k['views_per_visitor']
), 0, 1, 'L');

// Evolution table
$pdf->sectionTitle($period === 'today' ? 'Evolução por hora' : 'Evolução diária');
$wEvo = [50, 44, 44, 44];
$pdf->tableHeader(['Data/Hora', 'Visualizações', 'Cliques', 'Conv. %'], $wEvo);
$labels = $data['chart']['labels'];
$views = $data['chart']['views'];
$clicks = $data['chart']['clicks'];
$rowsPrinted = 0;
for ($i = 0; $i < count($labels); $i++) {
    $v = (int) ($views[$i] ?? 0);
    $c = (int) ($clicks[$i] ?? 0);
    if ($v === 0 && $c === 0 && $period !== 'today') {
        // still show for continuity on short periods; keep all for 7d
    }
    $conv = $v > 0 ? round($c / $v * 100, 1) . '%' : '0%';
    $pdf->tableRow([(string) $labels[$i], number_format($v, 0, ',', '.'), number_format($c, 0, ',', '.'), $conv], $wEvo, $rowsPrinted % 2 === 1);
    $rowsPrinted++;
    if ($rowsPrinted >= 32) {
        break; // keep PDF compact
    }
}
if ($rowsPrinted === 0) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->Cell(0, 6, pdf_txt('Sem dados neste período.'), 0, 1, 'L');
}

// Devices + traffic side by side conceptually as sequential tables
$pdf->sectionTitle('Dispositivos');
$wDev = [120, 30, 32];
$pdf->tableHeader(['Dispositivo', 'Views', '%'], $wDev);
$i = 0;
foreach ($data['devices'] as $row) {
    $pct = round(((int) $row['total'] / max(1, $data['totals']['devices'])) * 100, 1) . '%';
    $pdf->tableRow([(string) ($row['device'] ?: 'Unknown'), number_format((int) $row['total'], 0, ',', '.'), $pct], $wDev, $i++ % 2 === 1);
}
if (!$data['devices']) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->Cell(0, 6, pdf_txt('Sem dados.'), 0, 1, 'L');
}

$pdf->sectionTitle('Origem do tráfego');
$wTr = [120, 30, 32];
$pdf->tableHeader(['Origem', 'Views', '%'], $wTr);
$i = 0;
foreach ($data['traffic'] as $row) {
    $pct = round(((int) $row['total'] / max(1, $data['totals']['traffic'])) * 100, 1) . '%';
    $pdf->tableRow([(string) $row['source'], number_format((int) $row['total'], 0, ',', '.'), $pct], $wTr, $i++ % 2 === 1);
}

$pdf->sectionTitle('Navegadores');
$wBr = [120, 30, 32];
$pdf->tableHeader(['Navegador', 'Views', '%'], $wBr);
$i = 0;
foreach ($data['browsers'] as $row) {
    $pct = round(((int) $row['total'] / max(1, $data['totals']['browsers'])) * 100, 1) . '%';
    $pdf->tableRow([(string) $row['browser'], number_format((int) $row['total'], 0, ',', '.'), $pct], $wBr, $i++ % 2 === 1);
}

$pdf->sectionTitle('Sistemas operacionais');
$wOs = [120, 30, 32];
$pdf->tableHeader(['Sistema', 'Views', '%'], $wOs);
$i = 0;
foreach ($data['os_rows'] as $row) {
    $pct = round(((int) $row['total'] / max(1, $data['totals']['os'])) * 100, 1) . '%';
    $pdf->tableRow([(string) $row['os'], number_format((int) $row['total'], 0, ',', '.'), $pct], $wOs, $i++ % 2 === 1);
}

$pdf->AddPage();
$pdf->sectionTitle('Países (top 10)');
$wCo = [90, 46, 46];
$pdf->tableHeader(['País', 'Visitantes', 'Views'], $wCo);
$i = 0;
foreach ($data['countries'] as $row) {
    $pdf->tableRow([
        (string) $row['country'],
        number_format((int) $row['visitors'], 0, ',', '.'),
        number_format((int) $row['views'], 0, ',', '.'),
    ], $wCo, $i++ % 2 === 1);
}
if (!$data['countries']) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->Cell(0, 6, pdf_txt('Sem dados.'), 0, 1, 'L');
}

$pdf->sectionTitle('Cidades (top 10)');
$wCi = [80, 56, 46];
$pdf->tableHeader(['Cidade', 'País', 'Visitantes'], $wCi);
$i = 0;
foreach ($data['cities'] as $row) {
    $pdf->tableRow([
        (string) $row['city'],
        (string) $row['country'],
        number_format((int) $row['visitors'], 0, ',', '.'),
    ], $wCi, $i++ % 2 === 1);
}
if (!$data['cities']) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->Cell(0, 6, pdf_txt('Sem dados.'), 0, 1, 'L');
}

$pdf->sectionTitle('Elementos mais clicados (top 10)');
$wCl = [100, 42, 40];
$pdf->tableHeader(['Elemento', 'Tipo', 'Cliques'], $wCl);
$i = 0;
foreach ($data['top_clicks'] as $row) {
    $text = trim((string) ($row['element_text'] ?? ''));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) > 55) {
            $text = mb_substr($text, 0, 52) . '...';
        }
    } elseif (strlen($text) > 55) {
        $text = substr($text, 0, 52) . '...';
    }
    if ($text === '') {
        $text = '(sem texto)';
    }
    $pdf->tableRow([
        $text,
        (string) ($row['element_type'] ?: '—'),
        number_format((int) $row['total'], 0, ',', '.'),
    ], $wCl, $i++ % 2 === 1);
}
if (!$data['top_clicks']) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->Cell(0, 6, pdf_txt('Sem cliques neste período.'), 0, 1, 'L');
}

$pdf->Ln(8);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->MultiCell(0, 4, pdf_txt(
    'Este relatório foi gerado automaticamente pela plataforma LinkBio com base nos dados de visualizações e cliques rastreados na página do cliente no período selecionado.'
));

$filename = 'linkbio-' . $selected . '-' . $period . '-' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);
exit;
