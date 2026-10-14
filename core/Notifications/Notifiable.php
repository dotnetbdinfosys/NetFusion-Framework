<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Notifiable.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications
| Purpose   : Trait imbuing entities with multi-channel notification dispatch and query capabilities.
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

use NetFusion\Core\Database\DatabaseManager;

/**
 * Trait Notifiable
 *
 * Imbues user models and domain entities with notification dispatching and database queries.
 *
 * @package NetFusion\Core\Notifications
 */
trait Notifiable
{
    /**
     * Dispatch a notification to this entity (synchronously or asynchronously via queue).
     *
     * @param Notification $notification
     * @return void
     */
    public function notify(Notification $notification): void
    {
        $manager = $this->getNotificationManager();
        $manager->send($this, $notification);
    }

    /**
     * Dispatch a notification immediately, bypassing any queue configuration.
     *
     * @param Notification       $notification
     * @param array<string>|null $channels
     * @return void
     */
    public function notifyNow(Notification $notification, ?array $channels = null): void
    {
        $manager = $this->getNotificationManager();
        $manager->sendNow($this, $notification, $channels);
    }

    /**
     * Get all database notifications for this entity.
     *
     * @return array<DatabaseNotification>
     */
    public function notifications(): array
    {
        $type = $this->getNotifiableType();
        $id = (string) $this->getNotifiableId();

        $rows = DatabaseNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map(fn($row) => DatabaseNotification::newFromBuilder($row), $rows);
    }

    /**
     * Get all unread notifications for this entity.
     *
     * @return array<DatabaseNotification>
     */
    public function unreadNotifications(): array
    {
        $type = $this->getNotifiableType();
        $id = (string) $this->getNotifiableId();

        $rows = DatabaseNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map(fn($row) => DatabaseNotification::newFromBuilder($row), $rows);
    }

    /**
     * Get all read notifications for this entity.
     *
     * @return array<DatabaseNotification>
     */
    public function readNotifications(): array
    {
        $type = $this->getNotifiableType();
        $id = (string) $this->getNotifiableId();

        $rows = DatabaseNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNotNull('read_at')
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map(fn($row) => DatabaseNotification::newFromBuilder($row), $rows);
    }

    /**
     * Mark all or a specific unread notification as read.
     *
     * @param string|null $notificationId
     * @return void
     */
    public function markAsRead(?string $notificationId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $type = $this->getNotifiableType();
        $id = (string) $this->getNotifiableId();

        $builder = DatabaseNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at');

        if ($notificationId !== null) {
            $builder->where('id', $notificationId);
        }

        $builder->update(['read_at' => $now]);
    }

    /**
     * Resolve destination routing for a specific channel.
     *
     * @param string $channel 'mail', 'database', 'sms', 'webhook', etc.
     * @return mixed
     */
    public function routeNotificationFor(string $channel): mixed
    {
        return match ($channel) {
            'mail'     => $this->email ?? ($this->attributes['email'] ?? null),
            'database' => (string) $this->getNotifiableId(),
            'sms'      => $this->phone ?? ($this->mobile ?? ($this->attributes['phone'] ?? ($this->attributes['mobile'] ?? null))),
            'webhook'  => $this->webhook_url ?? ($this->attributes['webhook_url'] ?? null),
            default    => null,
        };
    }

    /**
     * Get notifiable identifier for database storage.
     *
     * @return string|int
     */
    public function getNotifiableId(): string|int
    {
        if (method_exists($this, 'getKey')) {
            $key = $this->getKey();
            if ($key !== null) {
                return $key;
            }
        }

        return $this->id ?? ($this->attributes['id'] ?? 1);
    }

    /**
     * Get notifiable polymorphic type.
     *
     * @return string
     */
    public function getNotifiableType(): string
    {
        return static::class;
    }

    /**
     * Get notification manager instance.
     *
     * @return NotificationManager
     */
    protected function getNotificationManager(): NotificationManager
    {
        if (function_exists('app') && app()->has(NotificationManager::class)) {
            return app(NotificationManager::class);
        }

        return new NotificationManager();
    }
}
