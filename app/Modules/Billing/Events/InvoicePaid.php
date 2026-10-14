<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Events/InvoicePaid.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Events
| Purpose   : Event fired when a tenant invoice payment is confirmed.
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

namespace NetFusion\Modules\Billing\Events;

use NetFusion\Core\Events\Event;
use NetFusion\Modules\Billing\Models\Invoice;

/**
 * NetFusion Invoice Paid Event
 *
 * Triggered upon successful settlement of a tenant invoice.
 *
 * @package NetFusion\Modules\Billing\Events
 * @version 1.0.0
 */
class InvoicePaid extends Event
{
    /**
     * @param Invoice $invoice
     */
    public function __construct(
        public readonly Invoice $invoice
    ) {}
}
