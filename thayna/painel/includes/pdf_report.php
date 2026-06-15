<?php
require_once __DIR__ . '/../lib/fpdf.php';

function pdf_txt(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\u{2014}", "\u{2013}", "\u{2022}"], ['-', '-', '*'], $s);
    if (function_exists('iconv')) {
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        if ($out !== false) {
            return $out;
        }
    }
    return preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $s) ?: $s;
}

function pdf_mark(bool $selected): string
{
    return $selected ? '(X)' : '( )';
}

function thayna_pdf_logo_path(): ?string
{
    $candidates = [
        dirname(__DIR__, 2) . '/logo/logo.png',
        dirname(__DIR__) . '/assets/logo.png',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

class ThaynaRelatorioPdf extends FPDF
{
    public function Header(): void
    {
        $logo = thayna_pdf_logo_path();
        if ($logo) {
            $this->Image($logo, 86, 8, 38);
            $this->SetY(30);
        } else {
            $this->SetY(12);
        }

        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(109, 33, 79);
        $this->Cell(0, 8, pdf_txt('RELATÓRIO DE ANÁLISE COMPORTAMENTAL'), 0, 1, 'C');
        $this->SetDrawColor(179, 55, 113);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY() + 1, 195, $this->GetY() + 1);
        $this->Ln(4);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, pdf_txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    private function sectionTitle(string $title): void
    {
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(249, 236, 229);
        $this->Cell(0, 8, pdf_txt($title), 0, 1, 'L', true);
        $this->Ln(2);
    }

    private function bodyText(string $text): void
    {
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5.5, pdf_txt($text));
        $this->Ln(2);
    }

    private function optionLine(string $label, bool $selected): void
    {
        $this->SetFont('Arial', '', 10);
        $mark = pdf_mark($selected);
        $this->Cell(12, 6, pdf_txt($mark), 0, 0);
        $this->Cell(0, 6, pdf_txt($label), 0, 1);
    }

    private function obsBlock(?string $obs): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 5, pdf_txt('Observações:'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5.5, pdf_txt($obs ?: '—'));
        $this->Ln(2);
    }

    public function render(array $r): void
    {
        $this->AliasNbPages();
        $this->AddPage();
        $this->SetAutoPageBreak(true, 18);

        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, pdf_txt('Data da análise: ' . ($r['data_analise_fmt'] ?? '—')), 0, 1);
        $this->Cell(0, 6, pdf_txt('Código do caso: ' . ($r['codigo_caso'] ?? '—')), 0, 1);
        $this->Cell(0, 6, pdf_txt('Cliente: ' . ($r['cliente_nome'] ?? '—')), 0, 1);
        $this->Ln(4);

        $this->sectionTitle('1. OBJETIVO DA ANÁLISE');
        $this->bodyText(
            'Este relatório apresenta uma análise das interações e comportamentos observados durante o período de monitoramento, com o objetivo de identificar padrões de comunicação, receptividade a abordagens externas, coerência comportamental e possíveis indicadores de comprometimento com o relacionamento atual.'
        );

        $this->sectionTitle('2. METODOLOGIA UTILIZADA');
        $this->bodyText(
            "Foi realizada uma interação controlada por meio de perfil previamente definido, simulando uma aproximação social espontânea.\n\nDurante o processo foram observados:\n\n• Tempo de resposta\n• Interesse demonstrado na conversa\n• Iniciativa para manter contato\n• Compartilhamento de informações pessoais\n• Nível de abertura emocional\n• Flertes ou insinuações\n• Respeito aos limites do relacionamento\n• Coerência entre discurso e comportamento"
        );
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('Período da análise:'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, pdf_txt('Início: ' . ($r['periodo_inicio_fmt'] ?? '—')), 0, 1);
        $this->Cell(0, 6, pdf_txt('Término: ' . ($r['periodo_termino_fmt'] ?? '—')), 0, 1);
        $this->Ln(2);

        $this->sectionTitle('3. RESUMO DAS INTERAÇÕES');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, pdf_txt('Descrição resumida dos acontecimentos:'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5.5, pdf_txt($r['resumo_interacoes'] ?? '—'));
        $this->Ln(2);

        $this->sectionTitle('4. INDICADORES OBSERVADOS');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('A) RECEPTIVIDADE AO CONTATO'), 0, 1);
        $opts = ['Muito baixa', 'Baixa', 'Moderada', 'Alta', 'Muito alta'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['receptividade'] ?? '') === $o);
        }
        $this->obsBlock($r['receptividade_obs'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('B) INICIATIVA NA CONVERSA'), 0, 1);
        $opts = ['Não demonstrou', 'Ocasional', 'Frequente'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['iniciativa'] ?? '') === $o);
        }
        $this->obsBlock($r['iniciativa_obs'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('C) ABERTURA PARA INTERAÇÃO PESSOAL'), 0, 1);
        $opts = ['Não demonstrou', 'Limitada', 'Moderada', 'Elevada'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['abertura'] ?? '') === $o);
        }
        $this->obsBlock($r['abertura_obs'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('D) MENÇÕES AO RELACIONAMENTO ATUAL'), 0, 1);
        $opts = ['Espontâneas', 'Apenas quando questionado(a)', 'Não mencionou'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['mencoes_relacionamento'] ?? '') === $o);
        }
        $this->obsBlock($r['mencoes_obs'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('E) RESPEITO AOS LIMITES DO RELACIONAMENTO'), 0, 1);
        $opts = ['Demonstrou claramente', 'Parcialmente', 'Não demonstrou'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['respeito_limites'] ?? '') === $o);
        }
        $this->obsBlock($r['respeito_obs'] ?? '');

        $this->sectionTitle('5. PONTOS RELEVANTES IDENTIFICADOS');
        $pontos = $r['pontos_relevantes'] ?? [];
        if (!is_array($pontos)) {
            $pontos = array_filter(array_map('trim', explode("\n", (string) $pontos)));
        }
        if (!$pontos) {
            $pontos = ['—'];
        }
        $this->SetFont('Arial', '', 10);
        foreach ($pontos as $p) {
            if (trim((string) $p) === '') {
                continue;
            }
            $this->Cell(6, 6, pdf_txt('•'), 0, 0);
            $this->MultiCell(0, 6, pdf_txt((string) $p));
        }
        $this->Ln(2);

        $this->sectionTitle('6. CONCLUSÃO TÉCNICA');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, pdf_txt('Com base nas interações observadas durante o período analisado:'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5.5, pdf_txt($r['conclusao_tecnica'] ?? '—'));
        $this->Ln(2);

        $this->sectionTitle('7. RESULTADO GERAL');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('Nível de comprometimento observado:'), 0, 1);
        $opts = ['Muito elevado', 'Elevado', 'Moderado', 'Baixo', 'Muito baixo'];
        foreach ($opts as $o) {
            $this->optionLine($o, ($r['nivel_comprometimento'] ?? '') === $o);
        }
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 5, pdf_txt('Observação final:'), 0, 1);
        $this->SetFont('Arial', 'I', 9);
        $obs = $r['observacao_final'] ?? 'Este relatório apresenta exclusivamente os comportamentos observados durante o período analisado e não constitui prova definitiva sobre intenções, sentimentos ou ações não registradas durante a interação.';
        $this->MultiCell(0, 5, pdf_txt($obs));
    }
}

function thayna_format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : $date;
}

function thayna_relatorio_prepare(array $row): array
{
    $payload = json_decode($row['payload_json'] ?? '{}', true) ?: [];
    $data = array_merge($payload, [
        'codigo_caso' => $row['codigo_caso'] ?? '',
        'cliente_nome' => $row['cliente_nome'] ?? '',
        'data_analise' => $row['data_analise'] ?? ($payload['data_analise'] ?? ''),
        'periodo_inicio' => $row['periodo_inicio'] ?? ($payload['periodo_inicio'] ?? ''),
        'periodo_termino' => $row['periodo_termino'] ?? ($payload['periodo_termino'] ?? ''),
    ]);
    $data['data_analise_fmt'] = thayna_format_date($data['data_analise'] ?? null);
    $data['periodo_inicio_fmt'] = thayna_format_date($data['periodo_inicio'] ?? null);
    $data['periodo_termino_fmt'] = thayna_format_date($data['periodo_termino'] ?? null);
    return $data;
}

function thayna_gerar_codigo_caso(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'THY-' . $year . '-';
    $st = $pdo->prepare('SELECT codigo_caso FROM thayna_relatorios WHERE codigo_caso LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $num = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $num = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
}

function thayna_payload_from_post(array $post): array
{
    $pontos = [];
    for ($i = 1; $i <= 4; $i++) {
        $v = trim($post['ponto_' . $i] ?? '');
        if ($v !== '') {
            $pontos[] = $v;
        }
    }
    return [
        'data_analise' => trim($post['data_analise'] ?? '') ?: null,
        'periodo_inicio' => trim($post['periodo_inicio'] ?? '') ?: null,
        'periodo_termino' => trim($post['periodo_termino'] ?? '') ?: null,
        'resumo_interacoes' => trim($post['resumo_interacoes'] ?? ''),
        'receptividade' => trim($post['receptividade'] ?? ''),
        'receptividade_obs' => trim($post['receptividade_obs'] ?? ''),
        'iniciativa' => trim($post['iniciativa'] ?? ''),
        'iniciativa_obs' => trim($post['iniciativa_obs'] ?? ''),
        'abertura' => trim($post['abertura'] ?? ''),
        'abertura_obs' => trim($post['abertura_obs'] ?? ''),
        'mencoes_relacionamento' => trim($post['mencoes_relacionamento'] ?? ''),
        'mencoes_obs' => trim($post['mencoes_obs'] ?? ''),
        'respeito_limites' => trim($post['respeito_limites'] ?? ''),
        'respeito_obs' => trim($post['respeito_obs'] ?? ''),
        'pontos_relevantes' => $pontos,
        'conclusao_tecnica' => trim($post['conclusao_tecnica'] ?? ''),
        'nivel_comprometimento' => trim($post['nivel_comprometimento'] ?? ''),
        'observacao_final' => trim($post['observacao_final'] ?? '') ?: 'Este relatório apresenta exclusivamente os comportamentos observados durante o período analisado e não constitui prova definitiva sobre intenções, sentimentos ou ações não registradas durante a interação.',
    ];
}
