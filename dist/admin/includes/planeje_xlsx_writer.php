<?php
/**
 * Gera um .xlsx mínimo (Office Open XML) com cabeçalho em negrito, bordas finas
 * e células em texto (inlineStr) para evitar notação científica no telefone e em números longos.
 */
declare(strict_types=1);

function planeje_xml_escape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Coluna Excel 0-based → A, B, … Z, AA */
function planeje_xl_col(int $i): string
{
    $n = $i + 1;
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

/** Célula como texto forçado (inlineStr) */
function planeje_xlsx_cell_inline(string $ref, string $value, int $styleId): string
{
    return '<c r="' . planeje_xml_escape($ref) . '" s="' . $styleId . '" t="inlineStr">'
        . '<is><t>' . planeje_xml_escape($value) . '</t></is></c>';
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $rows Cada linha com o mesmo número de colunas que $headers
 */
function planeje_build_xlsx_bytes(array $headers, array $rows): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive não disponível no PHP (extensão zip).');
    }

    $numCols = count($headers);
    if ($numCols < 1) {
        throw new InvalidArgumentException('Cabeçalho vazio.');
    }

    $lastRow = 1 + count($rows);
    $lastCol = planeje_xl_col($numCols - 1);
    $dimension = 'A1:' . $lastCol . $lastRow;

    // styles: 0 = dados (borda), 1 = cabeçalho (negrito + fundo + borda)
    $styles = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><color theme="1"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD9E8F5"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color indexed="64"/></left>
      <right style="thin"><color indexed="64"/></right>
      <top style="thin"><color indexed="64"/></top>
      <bottom style="thin"><color indexed="64"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">
      <alignment vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
  </cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;

    $colsXml = '<cols>';
    $widths = [22, 28, 32, 16, 24, 36, 10, 14, 22, 12, 14, 14, 18, 28, 14];
    for ($c = 0; $c < $numCols; $c++) {
        $w = $widths[$c] ?? 16;
        $colsXml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $sheetRows = '';
    // Cabeçalho (estilo 1)
    $sheetRows .= '<row r="1" spans="1:' . $numCols . '" ht="22" customHeight="1">';
    for ($c = 0; $c < $numCols; $c++) {
        $ref = planeje_xl_col($c) . '1';
        $sheetRows .= planeje_xlsx_cell_inline($ref, $headers[$c], 1);
    }
    $sheetRows .= '</row>';

    $r = 2;
    foreach ($rows as $row) {
        $sheetRows .= '<row r="' . $r . '" spans="1:' . $numCols . '">';
        for ($c = 0; $c < $numCols; $c++) {
            $ref = planeje_xl_col($c) . $r;
            $val = isset($row[$c]) ? (string) $row[$c] : '';
            $sheetRows .= planeje_xlsx_cell_inline($ref, $val, 0);
        }
        $sheetRows .= '</row>';
        $r++;
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . planeje_xml_escape($dimension) . '"/>'
        . $colsXml
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . '</worksheet>';

    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'plx');
    if ($tmp === false) {
        throw new RuntimeException('tempnam falhou.');
    }
    @unlink($tmp);
    $path = $tmp . '.xlsx';
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o arquivo xlsx.');
    }

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML);

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML);

    $zip->addFromString('docProps/app.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>LinkBio</Application></Properties>
XML);

    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Planeje seu espaço</dc:title>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
        . '</cp:coreProperties>');

    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Respostas" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);

    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);

    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $bin = file_get_contents($path);
    @unlink($path);

    if ($bin === false) {
        throw new RuntimeException('Leitura do xlsx falhou.');
    }

    return $bin;
}
