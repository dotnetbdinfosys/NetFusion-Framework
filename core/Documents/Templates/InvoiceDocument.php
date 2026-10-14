<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Templates/InvoiceDocument.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Templates
| Purpose   : Pre-built production-ready Enterprise Invoice PDF generator.
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

namespace NetFusion\Core\Documents\Templates;

use NetFusion\Core\Documents\Pdf\PdfDocument;

class InvoiceDocument
{
    protected string $invoiceNumber = '';
    protected string $invoiceDate = '';
    protected string $dueDate = '';
    protected array $company = [
        'name'    => 'DotNetBD Enterprise',
        'address' => 'Dhaka, Bangladesh',
        'phone'   => '+880 1900-000000',
        'email'   => 'billing@dotnetbd.com',
    ];
    protected array $customer = [
        'name'    => '',
        'address' => '',
        'email'   => '',
    ];
    protected array $items = [];
    protected string $currency = 'USD';
    protected float $taxRate = 0.0;
    protected float $discount = 0.0;
    protected string $notes = 'Thank you for your business.';

    public function __construct(string $invoiceNumber = '')
    {
        $this->invoiceNumber = $invoiceNumber ?: 'INV-' . strtoupper(substr(uniqid(), -6));
        $this->invoiceDate = date('Y-m-d');
        $this->dueDate = date('Y-m-d', strtotime('+14 days'));
    }

    public static function make(string $invoiceNumber = ''): static
    {
        return new static($invoiceNumber);
    }

    public function setCompany(array $company): static
    {
        $this->company = array_merge($this->company, $company);
        return $this;
    }

    public function setCustomer(array $customer): static
    {
        $this->customer = array_merge($this->customer, $customer);
        return $this;
    }

    public function currency(string $currency): static
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function dates(string $invoiceDate, ?string $dueDate = null): static
    {
        $this->invoiceDate = $invoiceDate;
        if ($dueDate !== null) {
            $this->dueDate = $dueDate;
        }
        return $this;
    }

    public function addItem(string $description, int|float $quantity, float $unitPrice): static
    {
        $this->items[] = [
            'description' => $description,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total'       => $quantity * $unitPrice,
        ];
        return $this;
    }

    public function tax(float $ratePercent): static
    {
        $this->taxRate = $ratePercent;
        return $this;
    }

    public function discount(float $discountAmount): static
    {
        $this->discount = $discountAmount;
        return $this;
    }

    public function notes(string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getSubtotal(): float
    {
        return array_sum(array_column($this->items, 'total'));
    }

    public function getTaxAmount(): float
    {
        $taxable = max(0, $this->getSubtotal() - $this->discount);
        return $taxable * ($this->taxRate / 100);
    }

    public function getGrandTotal(): float
    {
        return max(0, $this->getSubtotal() - $this->discount) + $this->getTaxAmount();
    }

    /**
     * ইনভয়েসটি ভেক্টর PDF ডকুমেন্টে রূপান্তর করুন।
     */
    public function toPdf(): PdfDocument
    {
        $pdf = new PdfDocument('A4', 'portrait');

        // Header Background Banner
        $pdf->rect(0, 0, 595.28, 90, 'F', '#1F4E79');
        $pdf->text(40, 45, $this->company['name'], 'Helvetica', 18.0);
        $pdf->text(40, 65, 'INVOICE', 'Helvetica', 14.0);

        // Invoice Meta (Right-aligned info)
        $pdf->text(420, 35, "Invoice #: {$this->invoiceNumber}", 'Helvetica', 10.0);
        $pdf->text(420, 50, "Date: {$this->invoiceDate}", 'Helvetica', 10.0);
        $pdf->text(420, 65, "Due: {$this->dueDate}", 'Helvetica', 10.0);

        // Bill From & Bill To
        $pdf->text(40, 120, "FROM:", 'Helvetica', 9.0);
        $pdf->text(40, 135, $this->company['name'], 'Helvetica', 11.0);
        $pdf->text(40, 150, $this->company['address'], 'Helvetica', 9.0);
        $pdf->text(40, 165, $this->company['email'], 'Helvetica', 9.0);

        $pdf->text(300, 120, "BILL TO:", 'Helvetica', 9.0);
        $pdf->text(300, 135, $this->customer['name'] ?: 'Valued Customer', 'Helvetica', 11.0);
        $pdf->text(300, 150, $this->customer['address'] ?: 'N/A', 'Helvetica', 9.0);
        $pdf->text(300, 165, $this->customer['email'] ?: 'N/A', 'Helvetica', 9.0);

        // Separator Line
        $pdf->line(40, 185, 555, 185, 1.0);

        // Items Table
        $headers = ['Item Description', 'Qty', 'Unit Price (' . $this->currency . ')', 'Total (' . $this->currency . ')'];
        $colWidths = [260, 50, 100, 105];

        $tableRows = [];
        foreach ($this->items as $item) {
            $tableRows[] = [
                $item['description'],
                (string)$item['quantity'],
                number_format((float)$item['unit_price'], 2),
                number_format((float)$item['total'], 2),
            ];
        }

        $endY = $pdf->table(40, 205, $headers, $tableRows, $colWidths, 22.0);

        // Summary Totals Block
        $summaryX = 350;
        $summaryY = $endY + 20;

        $pdf->text($summaryX, $summaryY, "Subtotal:", 'Helvetica', 10.0);
        $pdf->text(500, $summaryY, number_format($this->getSubtotal(), 2) . ' ' . $this->currency, 'Helvetica', 10.0);

        if ($this->discount > 0) {
            $summaryY += 16;
            $pdf->text($summaryX, $summaryY, "Discount:", 'Helvetica', 10.0);
            $pdf->text(500, $summaryY, '-' . number_format($this->discount, 2) . ' ' . $this->currency, 'Helvetica', 10.0);
        }

        if ($this->taxRate > 0) {
            $summaryY += 16;
            $pdf->text($summaryX, $summaryY, sprintf("Tax (%.1f%%):", $this->taxRate), 'Helvetica', 10.0);
            $pdf->text(500, $summaryY, number_format($this->getTaxAmount(), 2) . ' ' . $this->currency, 'Helvetica', 10.0);
        }

        $summaryY += 20;
        $pdf->rect($summaryX - 5, $summaryY - 14, 210, 24, 'F', '#EAEAEA');
        $pdf->text($summaryX, $summaryY + 2, "GRAND TOTAL:", 'Helvetica', 11.0);
        $pdf->text(480, $summaryY + 2, number_format($this->getGrandTotal(), 2) . ' ' . $this->currency, 'Helvetica', 11.0);

        // Notes & Footer
        $pdf->text(40, 750, "Notes: " . $this->notes, 'Helvetica', 9.0);
        $pdf->line(40, 770, 555, 770, 0.5);
        $pdf->text(220, 790, "Generated by NetFusion Framework", 'Helvetica', 8.0);

        return $pdf;
    }
}