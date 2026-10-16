<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Root Web Installer Bridge
|--------------------------------------------------------------------------
|
| File      : install.php (Project Root)
| Purpose   : Forwards browser-based installation requests to public/install.php
|             when uploaded to Shared Hosting, CloudPanel, or cPanel.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : https://www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/public/install.php')) {
    require __DIR__ . '/public/install.php';
} else {
    http_response_code(503);
    echo 'Error: NetFusion web installer (public/install.php) not found.';
}
