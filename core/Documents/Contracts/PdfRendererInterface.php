<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Contracts/PdfRendererInterface.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Contracts
| Purpose   : Standard contract for PDF rendering engines.
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

namespace NetFusion\Core\Documents\Contracts;

use NetFusion\Core\Http\Response;

interface PdfRendererInterface
{
    /**
     * কাগজের সাইজ ও ওরিয়েন্টেশন নির্ধারণ করুন।
     *
     * @param string $paperSize e.g. 'A4', 'LETTER', 'LEGAL'
     * @param string $orientation e.g. 'portrait', 'landscape'
     */
    public function setPaper(string $paperSize = 'A4', string $orientation = 'portrait'): static;

    /**
     * এইচটিএমএল বা ভিউ কন্টেন্ট লোড করুন।
     */
    public function loadHtml(string $html): static;

    /**
     * তৈরি হওয়া পিডিএফ ফাইলের বাইনারি কন্টেন্ট রিটার্ন করুন।
     */
    public function output(): string;

    /**
     * নির্দিষ্ট পাথে পিডিএফ ফাইল সংরক্ষণ করুন।
     */
    public function save(string $path): bool;

    /**
     * ব্রাউজারে পিডিএফ ইনলাইন স্ট্রিম বা ডাউনলোড রেসপন্স দিন।
     */
    public function stream(string $filename = 'document.pdf', bool $download = false): Response;
}