<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Events/SubscriptionCreated.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Events
| Purpose   : Event fired when a tenant activates or subscribes to a new SaaS tier.
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
use NetFusion\Modules\Billing\Models\Plan;
use NetFusion\Modules\Billing\Models\Subscription;

/**
 * NetFusion Subscription Created Event
 *
 * Triggered upon successful tenant subscription activation.
 *
 * @package NetFusion\Modules\Billing\Events
 * @version 1.0.0
 */
class SubscriptionCreated extends Event
{
    /**
     * @param Subscription $subscription
     * @param Plan         $plan
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Plan         $plan
    ) {}
}
