<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Contracts/NotificationChannelInterface.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications\Contracts
| Purpose   : Contract for notification delivery channel drivers.
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

namespace NetFusion\Core\Notifications\Contracts;

use NetFusion\Core\Notifications\Notification;

/**
 * Interface NotificationChannelInterface
 *
 * Contract implemented by all notification delivery channel drivers
 * (Database, Mail, Webhook, SMS, Broadcast, etc.).
 *
 * @package NetFusion\Core\Notifications\Contracts
 */
interface NotificationChannelInterface
{
    /**
     * Send the given notification to the given notifiable entity.
     *
     * @param object       $notifiable   Recipient entity (User, Model, or AnonymousNotifiable)
     * @param Notification $notification Notification instance being dispatched
     * @return mixed
     */
    public function send(object $notifiable, Notification $notification): mixed;
}
