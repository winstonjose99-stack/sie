<?php
/**
 * Generador nativo de documentos PDF (%PDF-1.4) en PHP puro, 100% en blanco y negro
 * y diseño minimalista de planilla académica listo para imprimir.
 */
class SimplePDF {
    private array $pages = [];
    private int $currentPage = -1;
    private float $pageWidth = 595.28;  // A4 Portrait (pt)
    private float $pageHeight = 841.89; // A4 Portrait (pt)
    private float $margin = 36.0;
    private float $y = 0.0;

    private string $docTitle = 'Reporte del Sistema de Asistencias';
    private string $docSubtitle = '';
    private array $metaItems = [];
    private array $tableHeaders = [];
    private array $tableWidths = [];

    public function __construct(string $orientation = 'P') {
        if (strtoupper($orientation) === 'L') {
            $this->pageWidth  = 841.89;
            $this->pageHeight = 595.28;
        }
    }

    public function setHeaderInfo(string $title, string $subtitle = '', array $metaItems = []): void {
        $this->docTitle    = $title;
        $this->docSubtitle = $subtitle;
        $this->metaItems   = $metaItems;
    }

    private function encodeText(string $text): string {
        $clean = preg_replace('/[^\x20-\x7E\xA0-\xFF—–]/u', '', $text);
        if ($clean === null) {
            $clean = $text;
        }
        $clean = str_replace(['—', '–'], '-', $clean);
        $win = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $clean);
        if ($win === false) {
            $win = utf8_decode($clean);
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $win);
    }

    private function writeCmd(string $cmd): void {
        $this->pages[$this->currentPage] .= $cmd . "\n";
    }

    public function addPage(): void {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->y = $this->pageHeight - $this->margin;
        $this->drawPageHeader();
    }

    private function drawBorderRect(float $x, float $yTop, float $w, float $h, float $lineWidth = 0.6): void {
        $pdfY = $yTop - $h;
        $this->writeCmd(sprintf('0 0 0 RG %.2f w %.2f %.2f %.2f %.2f re S', $lineWidth, $x, $pdfY, $w, $h));
    }

    private function drawLine(float $x1, float $y1, float $x2, float $y2, float $width = 0.6): void {
        $this->writeCmd(sprintf('0 0 0 RG %.2f w %.2f %.2f m %.2f %.2f l S', $width, $x1, $y1, $x2, $y2));
    }

    private function drawText(float $x, float $y, string $text, float $fontSize = 10, bool $bold = false): void {
        $font = $bold ? '/F2' : '/F1';
        $escaped = $this->encodeText($text);
        $this->writeCmd(sprintf('BT %s %.2f Tf 0 0 0 rg %.2f %.2f Td (%s) Tj ET', $font, $fontSize, $x, $y, $escaped));
    }

    private function truncateToWidth(string $text, float $maxWidthPt, float $fontSize): string {
        $avgCharWidth = $fontSize * 0.52;
        $maxChars = max(4, (int) floor(($maxWidthPt - 10) / $avgCharWidth));
        if (mb_strlen($text, 'UTF-8') > $maxChars) {
            return mb_substr($text, 0, $maxChars - 2, 'UTF-8') . '..';
        }
        return $text;
    }

    private function drawPageHeader(): void {
        $usableW = $this->pageWidth - ($this->margin * 2);

        // Encabezado simple en blanco y negro
        $this->drawText($this->margin, $this->y - 12, 'SISTEMA DE CONTROL DE ASISTENCIA ESCOLAR', 9, false);
        $this->drawText($this->pageWidth - $this->margin - 120, $this->y - 12, 'Fecha: ' . date('d/m/Y H:i'), 9, false);

        $this->drawText($this->margin, $this->y - 28, strtoupper($this->docTitle), 13, true);
        if ($this->docSubtitle !== '') {
            $this->drawText($this->pageWidth - $this->margin - 120, $this->y - 28, $this->docSubtitle, 9.5, true);
        }

        $this->y -= 36;
        $this->drawLine($this->margin, $this->y, $this->margin + $usableW, $this->y, 0.8);
        $this->y -= 14;

        // Metadatos en texto plano sin cajas de colores
        if (!empty($this->metaItems)) {
            $partes = [];
            foreach ($this->metaItems as $label => $val) {
                $partes[] = $label . ': ' . $val;
            }
            $lineaMeta = implode('   |   ', $partes);
            $this->drawText($this->margin, $this->y, $this->truncateToWidth($lineaMeta, $usableW, 9.5), 9.5, false);
            $this->y -= 16;
        }
    }

    public function drawSummaryBoxes(array $boxes): void {
        if (empty($boxes)) {
            return;
        }
        $usableW = $this->pageWidth - ($this->margin * 2);
        $partes = [];
        foreach ($boxes as $box) {
            $partes[] = $box['label'] . ': ' . $box['value'];
        }
        $lineaResumen = implode('   |   ', $partes);
        $this->drawText($this->margin, $this->y, $this->truncateToWidth($lineaResumen, $usableW, 9.5), 9.5, true);
        $this->y -= 14;
    }

    public function setTableColumns(array $headers, array $percentWidths): void {
        $usableW = $this->pageWidth - ($this->margin * 2);
        $this->tableHeaders = $headers;
        $this->tableWidths  = [];
        foreach ($percentWidths as $pct) {
            $this->tableWidths[] = ($usableW * $pct) / 100.0;
        }
        $this->drawTableHeaderRow();
    }

    private function drawTableHeaderRow(): void {
        $rowH = 20.0;
        $x = $this->margin;

        foreach ($this->tableHeaders as $i => $hText) {
            $w = $this->tableWidths[$i] ?? 60.0;
            $this->drawBorderRect($x, $this->y, $w, $rowH, 0.7);
            $this->drawText($x + 5, $this->y - 13.5, strtoupper($hText), 8.5, true);
            $x += $w;
        }

        $this->y -= $rowH;
    }

    public function addTableRow(array $cells, int $rowIndex = 0, ?array $badgeColumnConfig = null): void {
        $rowH = 19.0;

        if ($this->y - $rowH < ($this->margin + 65)) {
            $this->addPage();
            $this->drawTableHeaderRow();
        }

        $x = $this->margin;
        foreach ($cells as $i => $cellText) {
            $w = $this->tableWidths[$i] ?? 60.0;
            $this->drawBorderRect($x, $this->y, $w, $rowH, 0.5);

            $textStr = (string) $cellText;
            $trunc   = $this->truncateToWidth($textStr, $w, 8.8);
            $this->drawText($x + 5, $this->y - 13, $trunc, 8.8, false);

            $x += $w;
        }

        $this->y -= $rowH;
    }

    public function drawSignatureFooter(string $leftLabel = 'Firma del Docente / Responsable', string $rightLabel = 'Sello de Control de Estudios'): void {
        if ($this->y < ($this->margin + 80)) {
            $this->addPage();
        }

        $usableW = $this->pageWidth - ($this->margin * 2);
        $sigY    = $this->margin + 44;

        $this->drawLine($this->margin + 20, $sigY, $this->margin + 200, $sigY, 0.6);
        $this->drawText($this->margin + 25, $sigY - 12, $leftLabel, 8.5, false);

        $rightStart = $this->margin + $usableW - 200;
        $this->drawLine($rightStart, $sigY, $rightStart + 180, $sigY, 0.6);
        $this->drawText($rightStart + 25, $sigY - 12, $rightLabel, 8.5, false);
    }

    private function addPageNumbers(): void {
        $total = count($this->pages);
        for ($i = 0; $i < $total; $i++) {
            $this->currentPage = $i;
            $usableW = $this->pageWidth - ($this->margin * 2);
            $this->drawLine($this->margin, $this->margin + 16, $this->margin + $usableW, $this->margin + 16, 0.4);
            $this->drawText($this->margin, $this->margin + 5, 'Planilla de control de asistencia escolar', 8.0, false);
            $this->drawText($this->pageWidth - $this->margin - 65, $this->margin + 5, sprintf('Pagina %d de %d', $i + 1, $total), 8.0, false);
        }
    }

    public function output(string $filename = 'reporte.pdf', bool $download = true): void {
        if (empty($this->pages)) {
            $this->addPage();
        }
        $this->addPageNumbers();

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $kids = [];
        $objId = 5;

        foreach ($this->pages as $content) {
            $pageObjId    = $objId++;
            $contentObjId = $objId++;
            $kids[] = "{$pageObjId} 0 R";

            $objects[$pageObjId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentObjId
            );

            $len = strlen($content);
            $objects[$contentObjId] = "<< /Length {$len} >>\nstream\n{$content}endstream";
        }

        $kidsStr = implode(' ', $kids);
        $count   = count($kids);
        $objects[2] = "<< /Type /Pages /Kids [ {$kidsStr} ] /Count {$count} >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjs  = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        if (headers_sent()) {
            echo $pdf;
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        $disposition = $download ? 'attachment' : 'inline';

        header('Content-Type: application/pdf');
        header("Content-Disposition: {$disposition}; filename=\"{$safeName}\"");
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit();
    }
}
?>
