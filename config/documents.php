<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Documents & Exporters Configuration
|--------------------------------------------------------------------------
|
| File      : config/documents.php
| Module    : Documents
| Purpose   : Document generation, PDF renderer settings, and Exporter options.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Default PDF Settings
    |--------------------------------------------------------------------------
    |
    | ডিফল্ট পেপার সাইজ এবং ওরিয়েন্টেশন
    |
    */
    'pdf' => [
        'paper'       => env('PDF_PAPER', 'A4'),
        'orientation' => env('PDF_ORIENTATION', 'portrait'),
        'font'        => env('PDF_FONT', 'Helvetica'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default CSV Exporter Settings
    |--------------------------------------------------------------------------
    |
    | CSV ফাইল ফরম্যাটের ডিফল্ট ডেলিমিটার ও BOM অপশন
    |
    */
    'csv' => [
        'delimiter'   => env('CSV_DELIMITER', ','),
        'enclosure'   => '"',
        'include_bom' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Settings
    |--------------------------------------------------------------------------
    */
    'excel' => [
        'creator'     => 'NetFusion Framework',
        'company'     => 'DotNetBD',
    ],

];