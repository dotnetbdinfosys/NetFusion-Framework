<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Pdf/PdfDocument.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Pdf
| Purpose   : Zero-dependency pure PHP PDF 1.4 document builder.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Documents\Pdf;

use NetFusion\Core\Documents\Contracts\PdfRendererInterface;
use NetFusion\Core\Http\Response;

class PdfDocument implements PdfRendererInterface
{
    protected float $pageWidth = 595.28;  // A4 width in pt
    protected float $pageHeight = 841.89; // A4 height in pt
    protected string $paperSize = 'A4';
    protected string $orientation = 'portrait';

    protected array $pages = [];
    protected int $currentPage = 0;
    protected string $currentFont = 'Helvetica';
    protected float $currentFontSize = 12.0;

    protected array $offsets = [];
    protected int $objectCount = 0;

    public function __construct(string $paperSize = 'A4', string $orientation = 'portrait')
    {
        $this->setPaper($paperSize, $orientation);
        $this->addPage();
    }

    public static function make(string $paperSize = 'A4', string $orientation = 'portrait'): static
    {
        return new static($paperSize, $orientation);
    }

    public function setPaper(string $paperSize = 'A4', string $orientation = 'portrait'): static
    {
        $this->paperSize = strtoupper($paperSize);
        $this->orientation = strtolower($orientation);

        // Standard dimensions in points (1 pt = 1/72 inch)
        $sizes = [
            'A4'     => [595.28, 841.89],
            'LETTER' => [612.00, 792.00],
            'LEGAL'  => [612.00, 1008.00],
        ];

        [$w, $h] = $sizes[$this->paperSize] ?? $sizes['A4'];

        if ($this->orientation === 'landscape') {
            $this->pageWidth = $h;
            $this->pageHeight = $w;
        } else {
            $this->pageWidth = $w;
            $this->pageHeight = $h;
        }

        return $this;
    }

    public function addPage(): static
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
        return $this;
    }

    public function setFont(string $font = 'Helvetica', float $size = 12.0): static
    {
        $this->currentFont = in_array($font, ['Helvetica', 'Times-Roman', 'Courier'], true) ? $font : 'Helvetica';
        $this->currentFontSize = $size;
        return $this;
    }

    /**
     * পৃষ্ঠায় টেক্সট আঁকুন (Top-Left ভিত্তিক কোঅর্ডিনেট, pt তে)।
     */
    public function text(float $x, float $y, string $text, ?string $font = null, ?float $size = null): static
    {
        $fName = $font ?? $this->currentFont;
        $fSize = $size ?? $this->currentFontSize;

        // PDF coordinate system originates at Bottom-Left, so convert Top-Left y
        $pdfY = $this->pageHeight - $y;

        $safeText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        $stream = sprintf(
            "BT /F1 %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $fSize,
            $x,
            $pdfY,
            $safeText
        );

        $this->pages[$this->currentPage] .= $stream;
        return $this;
    }

    /**
     * আয়তক্ষেত্র (Rectangle) আঁকুন।
     *
     * @param string $style 'D' for Draw outline, 'F' for Fill, 'DF' for Both
     */
    public function rect(float $x, float $y, float $w, float $h, string $style = 'D', ?string $fillColor = null): static
    {
        $pdfY = $this->pageHeight - $y - $h;

        $op = match (strtoupper($style)) {
            'F'  => 'f',
            'DF', 'FD' => 'B',
            default => 's',
        };

        $colorStream = '';
        if ($fillColor !== null) {
            // Hex color to RGB
            $hex = ltrim($fillColor, '#');
            if (strlen($hex) === 6) {
                $r = hexdec(substr($hex, 0, 2)) / 255;
                $g = hexdec(substr($hex, 2, 2)) / 255;
                $b = hexdec(substr($hex, 4, 2)) / 255;
                $colorStream = sprintf("%.2f %.2f %.2f rg\n", $r, $g, $b);
            }
        }

        $stream = sprintf(
            "%sq\n%.2f %.2f %.2f %.2f re %s\nQ\n",
            $colorStream,
            $x,
            $pdfY,
            $w,
            $h,
            $op
        );

        $this->pages[$this->currentPage] .= $stream;
        return $this;
    }

    /**
     * লাইন আঁকুন।
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 1.0): static
    {
        $pdfY1 = $this->pageHeight - $y1;
        $pdfY2 = $this->pageHeight - $y2;

        $stream = sprintf(
            "q\n%.2f w %.2f %.2f m %.2f %.2f l S\nQ\n",
            $width,
            $x1,
            $pdfY1,
            $x2,
            $pdfY2
        );

        $this->pages[$this->currentPage] .= $stream;
        return $this;
    }

    /**
     * টেবিল গ্রিড আঁকুন।
     */
    public function table(float $x, float $y, array $headers, array $rows, array $colWidths = [], float $rowHeight = 20.0): float
    {
        $currentY = $y;
        $totalCols = count($headers);

        if (empty($colWidths)) {
            $defaultWidth = ($this->pageWidth - ($x * 2)) / max(1, $totalCols);
            $colWidths = array_fill(0, $totalCols, $defaultWidth);
        }

        // Header
        $this->rect($x, $currentY, array_sum($colWidths), $rowHeight, 'F', '#EAEAEA');
        $this->rect($x, $currentY, array_sum($colWidths), $rowHeight, 'D');

        $colX = $x;
        foreach ($headers as $idx => $hdr) {
            $w = $colWidths[$idx] ?? 100.0;
            $this->text($colX + 5, $currentY + 14, (string)$hdr, 'Helvetica', 10.0);
            $colX += $w;
        }

        $currentY += $rowHeight;

        // Data Rows
        foreach ($rows as $row) {
            $this->rect($x, $currentY, array_sum($colWidths), $rowHeight, 'D');
            $colX = $x;
            $rowValues = array_values((array)$row);

            foreach ($rowValues as $idx => $val) {
                $w = $colWidths[$idx] ?? 100.0;
                $this->text($colX + 5, $currentY + 14, (string)$val, 'Helvetica', 9.0);
                $colX += $w;
            }

            $currentY += $rowHeight;
        }

        return $currentY;
    }

    public function loadHtml(string $html): static
    {
        // Simple HTML text extractor for pure PHP PDF engine
        $clean = strip_tags($html);
        $lines = explode("\n", $clean);
        $y = 40.0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $this->text(40.0, $y, $trimmed, 'Helvetica', 11.0);
                $y += 18.0;
            }
        }

        return $this;
    }

    /**
     * সম্পূর্ণ PDF 1.4 ফাইল স্ট্রাকচার বাইনারি তৈরি করুন।
     */
    public function output(): string
    {
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $this->offsets = [];
        $this->objectCount = 0;

        // Obj 1: Catalog
        $this->newObj($out, 1);
        $out .= "<</Type /Catalog /Pages 2 0 R>>\nendobj\n";

        // Obj 2: Pages
        $this->newObj($out, 2);
        $kids = [];
        for ($i = 0; $i < count($this->pages); $i++) {
            $kids[] = (3 + ($i * 2)) . ' 0 R';
        }
        $out .= sprintf(
            "<</Type /Pages /Kids [%s] /Count %d /MediaBox [0 0 %.2f %.2f]>>\nendobj\n",
            implode(' ', $kids),
            count($this->pages),
            $this->pageWidth,
            $this->pageHeight
        );

        // Pages and Content streams
        for ($i = 0; $i < count($this->pages); $i++) {
            $pageObj = 3 + ($i * 2);
            $contentObj = $pageObj + 1;

            // Page Object
            $this->newObj($out, $pageObj);
            $out .= sprintf(
                "<</Type /Page /Parent 2 0 R /Contents %d 0 R /Resources <</Font <</F1 <</Type /Font /Subtype /Type1 /BaseFont /%s>>>>>> >>\nendobj\n",
                $contentObj,
                $this->currentFont
            );

            // Content Stream Object
            $streamData = $this->pages[$i];
            $this->newObj($out, $contentObj);
            $out .= sprintf(
                "<</Length %d>>\nstream\n%sendstream\nendobj\n",
                strlen($streamData),
                $streamData
            );
        }

        // XRef Table
        $xrefOffset = strlen($out);
        $totalObjs = 2 + (count($this->pages) * 2) + 1;

        $out .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $totalObjs);
        for ($i = 1; $i < $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $this->offsets[$i]);
        }

        // Trailer
        $out .= sprintf(
            "trailer\n<</Size %d /Root 1 0 R>>\nstartxref\n%d\n%%%%EOF",
            $totalObjs,
            $xrefOffset
        );

        return $out;
    }

    protected function newObj(string &$out, int $id): void
    {
        $this->offsets[$id] = strlen($out);
        $out .= "{$id} 0 obj\n";
    }

    public function save(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $this->output()) !== false;
    }

    public function stream(string $filename = 'document.pdf', bool $download = false): Response
    {
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        $content = $this->output();
        $disposition = $download ? 'attachment' : 'inline';

        return new Response(
            content: $content,
            statusCode: 200,
            headers: [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
                'Content-Length'      => (string)strlen($content),
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            ]
        );
    }
}