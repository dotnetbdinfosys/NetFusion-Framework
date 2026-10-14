<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Exporters/ExcelXmlExporter.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Exporters
| Purpose   : Native XML Spreadsheet 2003 generator without external dependencies.
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

namespace NetFusion\Core\Documents\Exporters;

use NetFusion\Core\Documents\Contracts\ExporterInterface;
use NetFusion\Core\Http\Response;

class ExcelXmlExporter implements ExporterInterface
{
    /**
     * @var iterable<int, array<string, mixed>>
     */
    protected iterable $data = [];

    /**
     * @var array<string, string>|array<int, string>
     */
    protected array $headers = [];

    protected string $sheetTitle = 'Sheet1';
    protected string $creator = 'NetFusion Framework';
    protected string $company = 'DotNetBD';

    public function __construct(iterable $data = [], array $headers = [], string $sheetTitle = 'Sheet1')
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->sheetTitle = $sheetTitle;
    }

    public static function make(iterable $data = [], array $headers = [], string $sheetTitle = 'Sheet1'): static
    {
        return new static($data, $headers, $sheetTitle);
    }

    public function setData(iterable $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function sheetTitle(string $title): static
    {
        $this->sheetTitle = $title;
        return $this;
    }

    public function render(): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<?mso-application progid="Excel.Sheet"?>';
        $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $xml[] = ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:html="http://www.w3.org/TR/REC-html40">';

        // Document Properties
        $xml[] = ' <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">';
        $xml[] = sprintf('  <Author>%s</Author>', htmlspecialchars($this->creator, ENT_XML1, 'UTF-8'));
        $xml[] = sprintf('  <Company>%s</Company>', htmlspecialchars($this->company, ENT_XML1, 'UTF-8'));
        $xml[] = sprintf('  <Created>%s</Created>', date('Y-m-d\TH:i:s\Z'));
        $xml[] = ' </DocumentProperties>';

        // Styles
        $xml[] = ' <Styles>';
        $xml[] = '  <Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/></Style>';
        $xml[] = '  <Style ss:ID="HeaderStyle">';
        $xml[] = '   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>';
        $xml[] = '   <Interior ss:Color="#1F4E79" ss:Pattern="Solid"/>';
        $xml[] = '   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
        $xml[] = '   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/></Borders>';
        $xml[] = '  </Style>';
        $xml[] = '  <Style ss:ID="NumberStyle"><NumberFormat ss:Format="#,##0.00"/></Style>';
        $xml[] = ' </Styles>';

        // Worksheet
        $xml[] = sprintf(' <Worksheet ss:Name="%s">', htmlspecialchars($this->sheetTitle, ENT_XML1, 'UTF-8'));
        $xml[] = '  <Table>';

        // Headers
        if (!empty($this->headers)) {
            $xml[] = '   <Row ss:Height="22">';
            foreach (array_values($this->headers) as $hdr) {
                $xml[] = sprintf(
                    '    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">%s</Data></Cell>',
                    htmlspecialchars((string)$hdr, ENT_XML1, 'UTF-8')
                );
            }
            $xml[] = '   </Row>';
        }

        // Data Rows
        $isAssociativeHeader = !empty($this->headers) && array_keys($this->headers) !== range(0, count($this->headers) - 1);

        foreach ($this->data as $row) {
            if ($row instanceof \NetFusion\Core\Database\Model || (is_object($row) && method_exists($row, 'toArray'))) {
                $row = $row->toArray();
            } elseif (is_object($row)) {
                $row = get_object_vars($row);
            }

            $xml[] = '   <Row>';

            $cells = $isAssociativeHeader
                ? array_map(fn($k) => $row[$k] ?? '', array_keys($this->headers))
                : array_values((array)$row);

            foreach ($cells as $val) {
                if (is_numeric($val) && !str_starts_with((string)$val, '0') || $val === 0 || $val === '0') {
                    $xml[] = sprintf('    <Cell ss:StyleID="NumberStyle"><Data ss:Type="Number">%s</Data></Cell>', $val);
                } elseif (is_bool($val)) {
                    $xml[] = sprintf('    <Cell><Data ss:Type="Boolean">%d</Data></Cell>', $val ? 1 : 0);
                } else {
                    $xml[] = sprintf('    <Cell><Data ss:Type="String">%s</Data></Cell>', htmlspecialchars((string)($val ?? ''), ENT_XML1, 'UTF-8'));
                }
            }

            $xml[] = '   </Row>';
        }

        $xml[] = '  </Table>';
        $xml[] = ' </Worksheet>';
        $xml[] = '</Workbook>';

        return implode("\n", $xml);
    }

    public function store(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $this->render()) !== false;
    }

    public function download(string $filename = 'export.xls'): Response
    {
        if (!str_ends_with(strtolower($filename), '.xls') && !str_ends_with(strtolower($filename), '.xml')) {
            $filename .= '.xls';
        }

        $content = $this->render();

        return new Response(
            content: $content,
            statusCode: 200,
            headers: [
                'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length'      => (string)strlen($content),
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ]
        );
    }
}