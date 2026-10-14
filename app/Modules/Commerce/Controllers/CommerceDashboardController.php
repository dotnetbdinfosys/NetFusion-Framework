<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Controllers/CommerceDashboardController.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Controllers
| Purpose   : Web dashboard controller for Commerce & POS management.
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

namespace NetFusion\Modules\Commerce\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\Commerce\Models\Customer;
use NetFusion\Modules\Commerce\Models\Order;
use NetFusion\Modules\Commerce\Models\Payment;

class CommerceDashboardController
{
    public function index(Request $request): Response
    {
        $ordersCount = Order::count();
        $customersCount = Customer::count();
        $paidOrders = Order::where('payment_status', '=', 'paid')->get();
        $totalSales = 0.0;
        foreach ($paidOrders as $o) {
            $totalSales += (float)($o['total_amount'] ?? 0.0);
        }
        $recentOrders = Order::query()->orderBy('id', 'DESC')->limit(10)->get();

        $data = [
            'ordersCount'    => $ordersCount,
            'customersCount' => $customersCount,
            'totalSales'     => $totalSales,
            'recentOrders'   => $recentOrders,
        ];

        return Response::html((string) view('commerce::dashboard', $data));
    }
}
