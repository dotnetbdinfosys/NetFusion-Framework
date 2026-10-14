<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/SendQueuedNotificationJob.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications
| Purpose   : Asynchronous background job for queued notification delivery.
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

namespace NetFusion\Core\Notifications;

use NetFusion\Core\Queue\Contracts\ShouldQueue;
use NetFusion\Core\Queue\Job;
use Throwable;

/**
 * Class SendQueuedNotificationJob
 *
 * Dispatches notification asynchronously across configured channels.
 *
 * @package NetFusion\Core\Notifications
 */
class SendQueuedNotificationJob extends Job implements ShouldQueue
{
    /**
     * @param object|array<object> $notifiables
     * @param Notification         $notification
     * @param array<string>|null   $channels
     */
    public function __construct(
        protected object|array $notifiables,
        protected Notification $notification,
        protected ?array $channels = null
    ) {
        if (! empty($notification->queue)) {
            $this->queue = $notification->queue;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        /** @var NotificationManager $manager */
        $manager = function_exists('app') && app()->has(NotificationManager::class)
            ? app(NotificationManager::class)
            : new NotificationManager();

        $manager->sendNow($this->notifiables, $this->notification, $this->channels);
    }

    /**
     * {@inheritdoc}
     */
    public function failed(Throwable $exception): void
    {
        if (function_exists('app') && app()->has('log')) {
            app('log')->error(
                "Failed to send queued notification [" . get_class($this->notification) . "]: " . $exception->getMessage(),
                ['exception' => $exception]
            );
        }
    }
}
