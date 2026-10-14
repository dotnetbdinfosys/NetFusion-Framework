<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Controllers/BillingController.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Controllers
| Purpose   : Web Controller providing SaaS subscription management views and dashboards.
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

namespace NetFusion\Modules\Billing\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\Billing\Models\Invoice;
use NetFusion\Modules\Billing\Models\Plan;
use NetFusion\Modules\Billing\Models\Subscription;

/**
 * NetFusion Billing Web Controller
 *
 * Renders HTML views for tenant subscription overview, plans, and invoices.
 *
 * @package NetFusion\Modules\Billing\Controllers
 * @version 1.0.0
 */
class BillingController
{
    /**
     * Display the Billing dashboard.
     */
    public function index(Request $request): Response
    {
        $tenantId = tenant()?->id ?? (string) $request->query('tenant_id', 'default');

        $plans        = Plan::getActive();
        $subscription = Subscription::forTenant($tenantId);
        $invoices     = Invoice::forTenant($tenantId);

        return Response::view('billing::dashboard', [
            'tenantId'     => $tenantId,
            'plans'        => $plans,
            'subscription' => $subscription,
            'invoices'     => $invoices,
            'currency'     => config('billing.currency_symbol', '$'),
        ]);
    }
}