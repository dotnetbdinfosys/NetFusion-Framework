<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/NotificationManager.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications
| Purpose   : Central multi-channel notification dispatcher and channel manager.
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

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Notifications\Channels\BroadcastChannel;
use NetFusion\Core\Notifications\Channels\DatabaseChannel;
use NetFusion\Core\Notifications\Channels\MailChannel;
use NetFusion\Core\Notifications\Channels\SmsChannel;
use NetFusion\Core\Notifications\Channels\WebhookChannel;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Queue\Contracts\ShouldQueue;

/**
 * Class NotificationManager
 *
 * Central dispatcher orchestrating delivery of notifications across multiple channels
 * (Database, Mail, Webhook, SMS, Broadcast, and custom extensions) with queue support.
 *
 * @package NetFusion\Core\Notifications
 */
class NotificationManager
{
    /**
     * Instantiated channels cache.
     *
     * @var array<string, NotificationChannelInterface>
     */
    protected array $channels = [];

    /**
     * Custom channel creator closures.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * @param Application|null $app
     */
    public function __construct(
        protected ?Application $app = null
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);
    }

    /**
     * Resolve a channel instance by name.
     *
     * @param string|null $channel Channel name ('database', 'mail', 'webhook', 'sms', 'broadcast')
     * @return NotificationChannelInterface
     * @throws InvalidArgumentException
     */
    public function channel(?string $channel = null): NotificationChannelInterface
    {
        $channel = $channel ?? 'database';

        if (! isset($this->channels[$channel])) {
            $this->channels[$channel] = $this->createChannel($channel);
        }

        return $this->channels[$channel];
    }

    /**
     * Register a custom channel creator closure.
     *
     * @param string  $channel
     * @param Closure $callback
     * @return $this
     */
    public function extend(string $channel, Closure $callback): static
    {
        $this->customCreators[$channel] = $callback;
        return $this;
    }

    /**
     * Send a notification to one or multiple notifiable entities.
     *
     * Automatically dispatches to the background queue if the notification
     * implements ShouldQueue and queue is available.
     *
     * @param object|array<object> $notifiables
     * @param Notification         $notification
     * @return void
     */
    public function send(object|array $notifiables, Notification $notification): void
    {
        if ($notification instanceof ShouldQueue && $this->app !== null && $this->app->has('queue')) {
            $this->queueNotification($notifiables, $notification);
            return;
        }

        $this->sendNow($notifiables, $notification);
    }

    /**
     * Send a notification immediately without queuing.
     *
     * @param object|array<object> $notifiables
     * @param Notification         $notification
     * @param array<string>|null   $channels
     * @return void
     */
    public function sendNow(object|array $notifiables, Notification $notification, ?array $channels = null): void
    {
        $entities = is_array($notifiables) ? $notifiables : [$notifiables];

        foreach ($entities as $notifiable) {
            $targetChannels = $channels ?? $notification->via($notifiable);

            foreach ($targetChannels as $channelName) {
                $driver = $this->channel($channelName);
                $driver->send($notifiable, $notification);
            }
        }
    }

    /**
     * Push notification to background queue.
     *
     * @param object|array<object> $notifiables
     * @param Notification         $notification
     * @return void
     */
    protected function queueNotification(object|array $notifiables, Notification $notification): void
    {
        $job = new SendQueuedNotificationJob($notifiables, $notification);
        $queueName = $notification->queue ?? 'default';

        if ($this->app !== null && $this->app->has('queue')) {
            $this->app->make('queue')->push($job, $queueName);
        }
    }

    /**
     * Create a channel instance by name.
     *
     * @param string $channel
     * @return NotificationChannelInterface
     * @throws InvalidArgumentException
     */
    protected function createChannel(string $channel): NotificationChannelInterface
    {
        if (isset($this->customCreators[$channel])) {
            return ($this->customCreators[$channel])($this->app);
        }

        return match ($channel) {
            'database'  => new DatabaseChannel($this->app),
            'mail'      => new MailChannel($this->app),
            'webhook'   => new WebhookChannel($this->app),
            'sms'       => new SmsChannel($this->app),
            'broadcast' => new BroadcastChannel($this->app),
            default     => throw new InvalidArgumentException("Notification channel [{$channel}] is not supported."),
        };
    }
}
