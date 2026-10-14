<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 36 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase36.php
| Module    : Documents
| Purpose   : Comprehensive tests for Document Generation, PDF & Excel/CSV Exporters.
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Documents\DocumentManager;
use NetFusion\Core\Documents\Exporters\CsvExporter;
use NetFusion\Core\Documents\Exporters\ExcelXmlExporter;
use NetFusion\Core\Documents\Pdf\HtmlToPdfAdapter;
use NetFusion\Core\Documents\Pdf\PdfDocument;
use NetFusion\Core\Documents\Templates\InvoiceDocument;
use NetFusion\Core\Http\Response;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 36 — Document Generation, PDF & Exporter Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: CSV Exporter Engine
// =============================================================================
echo "\033[36m[Section 1: CSV Exporter Engine]\033[0m\n";

$sampleUsers = [
    ['id' => 1, 'name' => 'Md. Atiquzzaman Ashish', 'email' => 'ashish@dotnetbd.com', 'role' => 'Admin'],
    ['id' => 2, 'name' => 'Sarah Connor', 'email' => 'sarah@example.com', 'role' => 'Manager'],
    ['id' => 3, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'User'],
];

$csv = new CsvExporter($sampleUsers, ['id' => 'User ID', 'name' => 'Full Name', 'email' => 'Email Address', 'role' => 'System Role']);
$csvContent = $csv->render();

t(str_starts_with($csvContent, "\xEF\xBB\xBF"), 'CsvExporter includes UTF-8 BOM by default for Excel compatibility');
t(str_contains($csvContent, '"User ID","Full Name","Email Address","System Role"'), 'CsvExporter writes header row with custom labels');
t(str_contains($csvContent, '"Md. Atiquzzaman Ashish"'), 'CsvExporter correctly serializes string data with quotes');

$customCsv = (new CsvExporter($sampleUsers))->delimiter(';')->withBom(false);
$customOutput = $customCsv->render();
t(!str_starts_with($customOutput, "\xEF\xBB\xBF"), 'CsvExporter::withBom(false) disables BOM byte sequence');
t(str_contains($customOutput, ';'), 'CsvExporter::delimiter(";") uses custom semicolon delimiter');

$tempCsvPath = __DIR__ . '/test_output.csv';
t($csv->store($tempCsvPath) && file_exists($tempCsvPath), 'CsvExporter::store() persists CSV file to disk');
@unlink($tempCsvPath);

$downloadResponse = $csv->download('users_report.csv');
t($downloadResponse instanceof Response, 'CsvExporter::download() returns Response instance');
t($downloadResponse->hasHeader('Content-Type') && str_contains($downloadResponse->getHeader('Content-Type'), 'text/csv'), 'CSV download response has correct MIME type');
t(str_contains($downloadResponse->getHeader('Content-Disposition'), 'users_report.csv'), 'CSV download response has correct filename header');

// =============================================================================
// Section 2: Microsoft Excel XML Spreadsheet 2003 Exporter
// =============================================================================
echo "\n\033[36m[Section 2: Microsoft Excel XML Exporter]\033[0m\n";

$excel = new ExcelXmlExporter($sampleUsers, ['id' => 'ID', 'name' => 'Name', 'email' => 'Email'], 'User Directory');
$excelXml = $excel->render();

t(str_starts_with($excelXml, '<?xml version="1.0" encoding="UTF-8"?>'), 'ExcelXmlExporter starts with XML declaration');
t(str_contains($excelXml, 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'), 'ExcelXmlExporter defines Spreadsheet 2003 namespace');
t(str_contains($excelXml, '<Worksheet ss:Name="User Directory">'), 'ExcelXmlExporter creates named worksheet');
t(str_contains($excelXml, '<Style ss:ID="HeaderStyle">'), 'ExcelXmlExporter embeds styling and headers');
t(str_contains($excelXml, '<Data ss:Type="String">Md. Atiquzzaman Ashish</Data>'), 'ExcelXmlExporter formats string cell data');

$tempExcelPath = __DIR__ . '/test_output.xls';
t($excel->store($tempExcelPath) && file_exists($tempExcelPath), 'ExcelXmlExporter::store() persists Excel file to disk');
@unlink($tempExcelPath);

$excelResponse = $excel->download('directory.xls');
t($excelResponse instanceof Response && str_contains($excelResponse->getHeader('Content-Type'), 'application/vnd.ms-excel'), 'Excel download response returns valid Excel MIME type');

// =============================================================================
// Section 3: Pure PHP PDF Document Builder
// =============================================================================
echo "\n\033[36m[Section 3: Pure PHP PDF 1.4 Document Builder]\033[0m\n";

$pdf = new PdfDocument('A4', 'portrait');
$pdf->setFont('Helvetica', 14.0);
$pdf->text(50.0, 50.0, 'NetFusion Enterprise Report');
$pdf->rect(40.0, 40.0, 515.0, 30.0, 'D');
$pdf->line(40.0, 80.0, 555.0, 80.0, 1.5);

$tableHeaders = ['Metric', 'Target', 'Achieved', 'Status'];
$tableData = [
    ['API Response Time', '< 50ms', '12.4ms', 'Optimal'],
    ['Cache Hit Ratio', '> 90%', '98.5%', 'Optimal'],
    ['Database Queries', '< 10/req', '2.1/req', 'Optimal'],
];
$endTableY = $pdf->table(40.0, 100.0, $tableHeaders, $tableData, [160.0, 100.0, 100.0, 115.0]);

t($endTableY > 100.0, 'PdfDocument::table() draws table grid and returns end Y position');

$pdfOutput = $pdf->output();
t(str_starts_with($pdfOutput, "%PDF-1.4\n"), 'PdfDocument::output() generates valid PDF-1.4 header');
t(str_ends_with(trim($pdfOutput), '%%EOF'), 'PdfDocument::output() ends with %%EOF marker');
t(str_contains($pdfOutput, 'xref'), 'PdfDocument contains Cross-Reference (xref) table');
t(str_contains($pdfOutput, '/Type /Catalog') && str_contains($pdfOutput, '/Type /Pages'), 'PdfDocument contains PDF Catalog and Pages dictionary');

$tempPdfPath = __DIR__ . '/test_output.pdf';
t($pdf->save($tempPdfPath) && file_exists($tempPdfPath), 'PdfDocument::save() writes PDF binary to disk');
@unlink($tempPdfPath);

$pdfStreamResponse = $pdf->stream('report.pdf', false);
t($pdfStreamResponse->getHeader('Content-Type') === 'application/pdf', 'PdfDocument::stream() returns application/pdf Content-Type');
t(str_contains($pdfStreamResponse->getHeader('Content-Disposition'), 'inline'), 'PdfDocument::stream(download:false) sets inline disposition');

$pdfDownloadResponse = $pdf->stream('report.pdf', true);
t(str_contains($pdfDownloadResponse->getHeader('Content-Disposition'), 'attachment'), 'PdfDocument::stream(download:true) sets attachment disposition');

// =============================================================================
// Section 4: HTML to PDF Adapter
// =============================================================================
echo "\n\033[36m[Section 4: HTML to PDF Adapter]\033[0m\n";

$html = '<h1>Welcome to NetFusion</h1><p>High performance PHP framework.</p>';
$adapter = new HtmlToPdfAdapter();
$adapter->loadHtml($html);

$adapterPdf = $adapter->output();
t(str_starts_with($adapterPdf, "%PDF-1.4\n"), 'HtmlToPdfAdapter produces valid PDF binary');
t(str_contains($adapterPdf, 'Welcome to NetFusion'), 'HtmlToPdfAdapter extracts and renders text streams');

// =============================================================================
// Section 5: Pre-Built Enterprise Invoice Template
// =============================================================================
echo "\n\033[36m[Section 5: Enterprise Invoice Document Template]\033[0m\n";

$invoice = new InvoiceDocument('INV-2026-001');
$invoice->setCompany([
    'name'    => 'DotNetBD Software Ltd.',
    'address' => 'Dhaka, Bangladesh',
    'email'   => 'billing@dotnetbd.com',
]);
$invoice->setCustomer([
    'name'    => 'Acme Global Corp',
    'address' => 'New York, USA',
    'email'   => 'finance@acme.com',
]);
$invoice->currency('USD');
$invoice->addItem('NetFusion Enterprise License', 1, 499.00);
$invoice->addItem('Priority Support (12 Months)', 1, 150.00);
$invoice->discount(50.00);
$invoice->tax(5.0); // 5% tax

t($invoice->getSubtotal() === 649.00, 'InvoiceDocument::getSubtotal() = 499 + 150 = 649.00');
t(round($invoice->getTaxAmount(), 2) === 29.95, 'InvoiceDocument::getTaxAmount() = 5% of (649 - 50) = 29.95');
t(round($invoice->getGrandTotal(), 2) === 628.95, 'InvoiceDocument::getGrandTotal() = (649 - 50) + 29.95 = 628.95');

$invoicePdf = $invoice->toPdf();
t($invoicePdf instanceof PdfDocument, 'InvoiceDocument::toPdf() returns PdfDocument instance');
$invPdfBinary = $invoicePdf->output();
t(str_starts_with($invPdfBinary, "%PDF-1.4"), 'Generated Invoice PDF has valid binary structure');
t(str_contains($invPdfBinary, 'DotNetBD Software Ltd.'), 'Invoice PDF contains company details');
t(str_contains($invPdfBinary, 'Acme Global Corp'), 'Invoice PDF contains customer details');

// =============================================================================
// Section 6: DocumentManager Orchestration & Global Helpers
// =============================================================================
echo "\n\033[36m[Section 6: DocumentManager & Global Helpers]\033[0m\n";

$manager = new DocumentManager($app);
t($manager->csv($sampleUsers) instanceof CsvExporter, 'DocumentManager::csv() instantiates CsvExporter');
t($manager->excel($sampleUsers) instanceof ExcelXmlExporter, 'DocumentManager::excel() instantiates ExcelXmlExporter');
t($manager->pdf() instanceof PdfDocument, 'DocumentManager::pdf() instantiates PdfDocument');
t($manager->htmlToPdf($html) instanceof HtmlToPdfAdapter, 'DocumentManager::htmlToPdf() instantiates HtmlToPdfAdapter');
t($manager->invoice() instanceof InvoiceDocument, 'DocumentManager::invoice() instantiates InvoiceDocument');

// Global Helpers
t(function_exists('document'), 'Global helper document() exists');
t(function_exists('pdf'), 'Global helper pdf() exists');
t(function_exists('csv'), 'Global helper csv() exists');
t(function_exists('excel'), 'Global helper excel() exists');

t(document() instanceof DocumentManager, 'document() returns DocumentManager instance');
t(pdf() instanceof PdfDocument, 'pdf() returns PdfDocument');
t(pdf('<h1>Test</h1>') instanceof HtmlToPdfAdapter, 'pdf($html) returns HtmlToPdfAdapter');
t(csv($sampleUsers) instanceof CsvExporter, 'csv() helper returns CsvExporter');
t(excel($sampleUsers) instanceof ExcelXmlExporter, 'excel() helper returns ExcelXmlExporter');

// Container singletons
$cDoc = $app->make('document');
t($cDoc instanceof DocumentManager, '"document" singleton resolved from container');

echo "\n=================================================================\n";
echo "  Phase 36 Results: {$passed}/{$total} Passed";
if ($failed > 0) {
    echo " | \033[31m{$failed} Failed\033[0m";
} else {
    echo " \033[32m(100%)\033[0m";
}
echo "\n=================================================================\n\n";

exit($failed > 0 ? 1 : 0);