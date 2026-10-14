<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/DatabaseNotification.php
| Module    : Notifications
| Namespace : NetFusion\Core\Notifications
| Purpose   : Active Record Model for persisted database notifications.
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

use NetFusion\Core\Database\Model;

/**
 * Class DatabaseNotification
 *
 * Active Record model representing a persistent in-app notification.
 *
 * @package NetFusion\Core\Notifications
 * @property string      $id
 * @property string      $type
 * @property string      $notifiable_type
 * @property string      $notifiable_id
 * @property string      $data
 * @property string|null $read_at
 * @property string      $created_at
 */
class DatabaseNotification extends Model
{
    protected string $table = 'netfusion_notifications';
    protected string $primaryKey = 'id';
    protected bool $incrementing = false;
    protected bool $timestamps = false;

    protected array $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'created_at',
    ];

    /**
     * Mark notification as read.
     *
     * @return void
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = date('Y-m-d H:i:s');
            $this->save();
        }
    }

    /**
     * Mark notification as unread.
     *
     * @return void
     */
    public function markAsUnread(): void
    {
        if ($this->read_at !== null) {
            $this->read_at = null;
            $this->save();
        }
    }

    /**
     * Determine if notification has been read.
     *
     * @return bool
     */
    public function read(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if notification is unread.
     *
     * @return bool
     */
    public function unread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Decode and return data payload as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $raw = $this->getAttribute('data');
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return json_decode($raw, true) ?: [];
        }

        return [];
    }
}
