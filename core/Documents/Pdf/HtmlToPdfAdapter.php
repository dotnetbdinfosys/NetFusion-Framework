<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Pdf/HtmlToPdfAdapter.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Pdf
| Purpose   : Pluggable HTML-to-PDF adapter for rendering view templates into PDF.
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

class HtmlToPdfAdapter implements PdfRendererInterface
{
    protected PdfDocument $pdf;
    protected string $html = '';

    public function __construct(string $paperSize = 'A4', string $orientation = 'portrait')
    {
        $this->pdf = new PdfDocument($paperSize, $orientation);
    }

    public static function make(string $paperSize = 'A4', string $orientation = 'portrait'): static
    {
        return new static($paperSize, $orientation);
    }

    public function setPaper(string $paperSize = 'A4', string $orientation = 'portrait'): static
    {
        $this->pdf->setPaper($paperSize, $orientation);
        return $this;
    }

    public function loadHtml(string $html): static
    {
        $this->html = $html;
        $this->pdf->loadHtml($html);
        return $this;
    }

    public function loadView(string $view, array $data = []): static
    {
        $html = '';
        if (function_exists('view')) {
            $viewObj = view($view, $data);
            $html = (string)$viewObj;
        }

        return $this->loadHtml($html);
    }

    public function output(): string
    {
        return $this->pdf->output();
    }

    public function save(string $path): bool
    {
        return $this->pdf->save($path);
    }

    public function stream(string $filename = 'document.pdf', bool $download = false): Response
    {
        return $this->pdf->stream($filename, $download);
    }
}