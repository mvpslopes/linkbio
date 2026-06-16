<?php
require_once __DIR__ . '/pdf_report.php';
require_once __DIR__ . '/clientes.php';

class ThaynaTermoPdf extends FPDF
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
        $this->Cell(0, 8, pdf_txt('TERMO DE ACEITE ASSINADO'), 0, 1, 'C');
        $this->SetDrawColor(179, 55, 113);
        $this->Line(15, $this->GetY() + 1, 195, $this->GetY() + 1);
        $this->Ln(4);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, pdf_txt('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    private function section(string $title): void
    {
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(249, 236, 229);
        $this->Cell(0, 7, pdf_txt($title), 0, 1, 'L', true);
        $this->Ln(1);
    }

    public function render(array $cliente, array $questionario): void
    {
        $this->AliasNbPages();
        $this->AddPage();
        $this->SetAutoPageBreak(true, 18);
        $this->SetFont('Arial', '', 10);

        $this->Cell(0, 6, pdf_txt('Nome: ' . ($cliente['nome_completo'] ?? '')), 0, 1);
        $this->Cell(0, 6, pdf_txt('Idade: ' . ($cliente['idade'] ?? '—')), 0, 1);
        $this->Cell(0, 6, pdf_txt('WhatsApp: ' . ($cliente['whatsapp'] ?? '—')), 0, 1);
        if (!empty($cliente['instagram'])) {
            $this->Cell(0, 6, pdf_txt('Instagram: ' . $cliente['instagram']), 0, 1);
        }
        $this->Cell(0, 6, pdf_txt('Cidade/Estado: ' . ($cliente['cidade_estado'] ?? '—')), 0, 1);
        $this->Ln(3);

        $this->section('SOBRE VOCE - RESPOSTAS');
        $labels = thayna_questionario_labels();
        $n = 1;
        foreach ($labels as $key => $label) {
            $val = trim((string) ($questionario[$key] ?? ''));
            if ($val === '') {
                $val = '—';
            }
            $this->SetFont('Arial', 'B', 9);
            $this->MultiCell(0, 5, pdf_txt($n . '. ' . $label));
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 5.5, pdf_txt($val));
            $this->Ln(2);
            $n++;
        }

        $this->AddPage();
        $this->section('TERMO DE ACEITE');
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(0, 5, pdf_txt(thayna_termo_texto_legal()));

        $this->Ln(6);
        $this->section('ASSINATURA DIGITAL');
        $this->SetFont('Arial', '', 10);
        $assinado = $cliente['assinado_em'] ?? '';
        $ts = $assinado ? strtotime($assinado) : false;
        $dataFmt = $ts ? date('d/m/Y \a\s H:i', $ts) : '—';
        $this->MultiCell(0, 6, pdf_txt(
            'Declaro que li, compreendi e aceito integralmente o termo acima.'
        ));
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, pdf_txt('Assinante: ' . ($cliente['assinatura_nome'] ?? $cliente['nome_completo'] ?? '')), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, pdf_txt('Assinado digitalmente em: ' . $dataFmt), 0, 1);
    }
}

function thayna_termo_prepare_cliente(array $row): array
{
    $q = json_decode($row['questionario_json'] ?? '{}', true) ?: [];
    return [$row, $q];
}
