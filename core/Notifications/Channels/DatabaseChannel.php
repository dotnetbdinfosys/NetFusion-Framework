<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Channels/DatabaseChannel.php
| Module    : Notifications\Channels
| Namespace : NetFusion\Core\Notifications\Channels
| Purpose   : Database notification channel persisting records into netfusion_notifications table.
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
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\Notification;
use RuntimeException;

/**
 * Class DatabaseChannel
 *
 * Persists notifications directly into the database for in-app notification centers.
 *
 * @package NetFusion\Core\Notifications\Channels
 */
class DatabaseChannel implements NotificationChannelInterface
{
    /**
     * @param Application|null $app
     * @param string           $table
     */
    public function __construct(
        protected ?Application $app = null,
        protected string $table = 'netfusion_notifications'
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function send(object $notifiable, Notification $notification): mixed
    {
        $data = $this->getData($notifiable, $notification);
        $type = get_class($notification);

        $notifiableType = method_exists($notifiable, 'getNotifiableType')
            ? $notifiable->getNotifiableType()
            : get_class($notifiable);

        $notifiableId = method_exists($notifiable, 'getNotifiableId')
            ? (string) $notifiable->getNotifiableId()
            : (string) ($notifiable->id ?? '1');

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $now = date('Y-m-d H:i:s');

        $db = $this->getDatabaseManager();
        if ($db === null) {
            throw new RuntimeException("DatabaseChannel requires an active DatabaseManager.");
        }

        $conn = $db->connection();
        $conn->insert(
            "INSERT INTO {$this->table} (id, type, notifiable_type, notifiable_id, data, read_at, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, ?)",
            [$notification->id, $type, $notifiableType, $notifiableId, $jsonData, $now]
        );

        return [
            'id'              => $notification->id,
            'type'            => $type,
            'notifiable_type' => $notifiableType,
            'notifiable_id'   => $notifiableId,
            'data'            => $data,
            'read_at'         => null,
            'created_at'      => $now,
        ];
    }

    /**
     * Extract array payload from notification.
     *
     * @param object       $notifiable
     * @param Notification $notification
     * @return array<string, mixed>
     */
    protected function getData(object $notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'toDatabase')) {
            return $notification->toDatabase($notifiable);
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        return [];
    }

    /**
     * Resolve database manager.
     *
     * @return DatabaseManager|null
     */
    protected function getDatabaseManager(): ?DatabaseManager
    {
        if ($this->app !== null && $this->app->has(DatabaseManager::class)) {
            return $this->app->make(DatabaseManager::class);
        }

        if (function_exists('app') && app()->has(DatabaseManager::class)) {
            return app(DatabaseManager::class);
        }

        return null;
    }
}
