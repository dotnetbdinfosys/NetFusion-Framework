<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Controllers/PaymentController.php
| Module    : Modules\TestCli
| Namespace : NetFusion\Modules\TestCli\Controllers
| Purpose   : Controller handling HTTP requests and application actions.
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

class PaymentController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        return Response::json([
            'status'  => 'success',
            'message' => 'Hello from PaymentController::index()',
        ]);
    }
}