<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Channels/BroadcastChannel.php
| Module    : Notifications\Channels
| Namespace : NetFusion\Core\Notifications\Channels
| Purpose   : Real-time broadcast channel delivering notifications via SSE / WebSockets.
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

namespace NetFusion\Core\Notifications\Channels;

use NetFusion\Core\Application;
use NetFusion\Core\Broadcasting\BroadcastManager;
use NetFusion\Core\Broadcasting\Channels\PrivateChannel;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\Notification;

/**
 * Class BroadcastChannel
 *
 * Dispatches real-time in-app notification alerts across active client SSE/WebSocket streams.
 *
 * @package NetFusion\Core\Notifications\Channels
 */
class BroadcastChannel implements NotificationChannelInterface
{
    /**
     * @param Application|null $app
     */
    public function __construct(
        protected ?Application $app = null
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function send(object $notifiable, Notification $notification): mixed
    {
        $broadcastManager = $this->getBroadcastManager();
        if ($broadcastManager === null) {
            return null;
        }

        $notifiableId = method_exists($notifiable, 'getNotifiableId')
            ? (string) $notifiable->getNotifiableId()
            : (string) ($notifiable->id ?? '1');

        $channelName = 'notifications.' . $notifiableId;
        $eventData = $this->getData($notifiable, $notification);
        $eventData['id'] = $notification->id;
        $eventData['type'] = get_class($notification);

        $channel = new PrivateChannel($channelName);
        $broadcastManager->broadcast([$channel], 'notification.created', $eventData);

        return [
            'channel' => (string) $channel,
            'event'   => 'notification.created',
            'data'    => $eventData,
        ];
    }

    /**
     * Extract broadcast data payload.
     *
     * @param object       $notifiable
     * @param Notification $notification
     * @return array<string, mixed>
     */
    protected function getData(object $notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'toBroadcast')) {
            return $notification->toBroadcast($notifiable);
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        if (method_exists($notification, 'toDatabase')) {
            return $notification->toDatabase($notifiable);
        }

        return [];
    }

    /**
     * Resolve BroadcastManager from container.
     *
     * @return BroadcastManager|null
     */
    protected function getBroadcastManager(): ?BroadcastManager
    {
        if ($this->app !== null && $this->app->has('broadcast')) {
            return $this->app->make('broadcast');
        }

        if (function_exists('app') && app()->has('broadcast')) {
            return app('broadcast');
        }

        return null;
    }
}
