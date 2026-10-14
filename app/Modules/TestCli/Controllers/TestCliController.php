<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Controllers/TestCliController.php
| Module    : Modules\TestCli
| Namespace : NetFusion\Modules\TestCli\Controllers
| Purpose   : Primary web controller for TestCli Module.
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

namespace NetFusion\Modules\TestCli\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class TestCliController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'module'  => 'TestCli',
            'status'  => 'active',
            'message' => 'Welcome to TestCli module!',
        ]);
    }
}