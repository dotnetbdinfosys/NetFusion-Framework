<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Root HTTP Entry Point Bridge
|--------------------------------------------------------------------------
|
| File      : index.php (Project Root)
| Purpose   : Forwards web requests to public/index.php when web servers
|             (e.g., Apache/cPanel/LiteSpeed) point DocumentRoot to root.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : https://www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.1.0
|
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/public/index.php')) {
    require __DIR__ . '/public/index.php';
} else {
    http_response_code(503);
    echo 'Error: NetFusion public entry point (public/index.php) not found.';
}
