<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/DocumentManager.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents
| Purpose   : Central document and report orchestrator (CSV, Excel XML, PDF, Invoices).
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

namespace NetFusion\Core\Documents;

use NetFusion\Core\Application;
use NetFusion\Core\Documents\Exporters\CsvExporter;
use NetFusion\Core\Documents\Exporters\ExcelXmlExporter;
use NetFusion\Core\Documents\Pdf\HtmlToPdfAdapter;
use NetFusion\Core\Documents\Pdf\PdfDocument;
use NetFusion\Core\Documents\Templates\InvoiceDocument;

class DocumentManager
{
    public function __construct(
        protected readonly ?Application $app = null
    ) {}

    /**
     * CSV Exporter তৈরি করুন।
     *
     * @param iterable<int, array<string, mixed>> $data
     * @param array<string, string>|array<int, string> $headers
     */
    public function csv(iterable $data = [], array $headers = []): CsvExporter
    {
        return new CsvExporter($data, $headers);
    }

    /**
     * Excel XML Exporter তৈরি করুন।
     *
     * @param iterable<int, array<string, mixed>> $data
     * @param array<string, string>|array<int, string> $headers
     */
    public function excel(iterable $data = [], array $headers = [], string $sheetTitle = 'Sheet1'): ExcelXmlExporter
    {
        return new ExcelXmlExporter($data, $headers, $sheetTitle);
    }

    /**
     * Pure PHP Vector PDF Document তৈরি করুন।
     */
    public function pdf(string $paperSize = 'A4', string $orientation = 'portrait'): PdfDocument
    {
        return new PdfDocument($paperSize, $orientation);
    }

    /**
     * HTML to PDF Adapter তৈরি করুন।
     */
    public function htmlToPdf(string $html = '', string $paperSize = 'A4', string $orientation = 'portrait'): HtmlToPdfAdapter
    {
        $adapter = new HtmlToPdfAdapter($paperSize, $orientation);
        if (!empty($html)) {
            $adapter->loadHtml($html);
        }
        return $adapter;
    }

    /**
     * এন্টারপ্রাইজ ইনভয়েস জেনারেটর ইনস্ট্যান্স তৈরি করুন।
     */
    public function invoice(string $invoiceNumber = ''): InvoiceDocument
    {
        return new InvoiceDocument($invoiceNumber);
    }
}