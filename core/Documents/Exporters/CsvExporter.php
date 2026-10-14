<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Exporters/CsvExporter.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Exporters
| Purpose   : High-performance, memory-efficient CSV exporter with UTF-8 BOM support.
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

class CsvExporter implements ExporterInterface
{
    /**
     * @var iterable<int, array<string, mixed>>
     */
    protected iterable $data = [];

    /**
     * @var array<string, string>|array<int, string>
     */
    protected array $headers = [];

    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $escapeChar = '\\';
    protected bool $includeBom = true;

    public function __construct(iterable $data = [], array $headers = [])
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    public static function make(iterable $data = [], array $headers = []): static
    {
        return new static($data, $headers);
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

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function withBom(bool $includeBom = true): static
    {
        $this->includeBom = $includeBom;
        return $this;
    }

    /**
     * CSV কন্টেন্ট স্ট্রিং হিসেবে জেনারেট করুন।
     */
    public function render(): string
    {
        $fp = fopen('php://temp', 'r+');

        if ($this->includeBom) {
            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($fp, "\xEF\xBB\xBF");
        }

        // Write headers if provided
        if (!empty($this->headers)) {
            $headerValues = array_values($this->headers);
            fputcsv($fp, $headerValues, $this->delimiter, $this->enclosure, $this->escapeChar);
        }

        // Write data rows
        $isAssociativeHeader = !empty($this->headers) && array_keys($this->headers) !== range(0, count($this->headers) - 1);

        foreach ($this->data as $row) {
            if ($row instanceof \NetFusion\Core\Database\Model || (is_object($row) && method_exists($row, 'toArray'))) {
                $row = $row->toArray();
            } elseif (is_object($row)) {
                $row = get_object_vars($row);
            }

            if ($isAssociativeHeader) {
                $mappedRow = [];
                foreach (array_keys($this->headers) as $key) {
                    $mappedRow[] = $row[$key] ?? '';
                }
                fputcsv($fp, $mappedRow, $this->delimiter, $this->enclosure, $this->escapeChar);
            } else {
                fputcsv($fp, array_values((array)$row), $this->delimiter, $this->enclosure, $this->escapeChar);
            }
        }

        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        return $csvContent ?: '';
    }

    public function store(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $this->render()) !== false;
    }

    public function download(string $filename = 'export.csv'): Response
    {
        if (!str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        $content = $this->render();

        return new Response(
            content: $content,
            statusCode: 200,
            headers: [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length'      => (string)strlen($content),
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ]
        );
    }
}