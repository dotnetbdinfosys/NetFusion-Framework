<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Documents/Contracts/ExporterInterface.php
| Module    : Documents
| Namespace : NetFusion\Core\Documents\Contracts
| Purpose   : Contract for data exporting to various formats (CSV, Excel, etc.).
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

interface ExporterInterface
{
    /**
     * এক্সপোর্ট ডাটা সেট করুন (অ্যারে, জেনারেটর বা রো কালেকশন)।
     *
     * @param iterable<int, array<string, mixed>> $data
     */
    public function setData(iterable $data): static;

    /**
     * কলাম হেডার্স সেট করুন।
     *
     * @param array<string, string>|array<int, string> $headers
     */
    public function setHeaders(array $headers): static;

    /**
     * এক্সপোর্ট করা ডকুমেন্টের কাঁচা বাইনারি/টেক্সট কন্টেন্ট পান।
     */
    public function render(): string;

    /**
     * নির্দিষ্ট ফাইল পাথে এক্সপোর্ট ফাইল সেভ করুন।
     */
    public function store(string $path): bool;

    /**
     * সরাসরি ব্রাউজারে ফাইল ডাউনলোড রেসপন্স তৈরি করুন।
     */
    public function download(string $filename): Response;
}