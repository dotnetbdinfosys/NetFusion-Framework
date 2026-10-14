<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Notification\Channels;

use NetFusion\Core\Notification\Contracts\ChannelInterface;
use NetFusion\Core\Notification\Contracts\NotificationStoreInterface;
use NetFusion\Core\Notification\Notification;

/**
 * Class DatabaseChannel
 *
 * Persists notifications to the database as a reliable fallback / inbox channel.
 * Useful for in-app notification centers, read receipts, and offline users.
 */
class DatabaseChannel implements ChannelInterface
{
    /** @var array[] In-memory store when no persistence layer provided */
    private static array $memoryStore = [];

    public function __construct(
        private readonly ?NotificationStoreInterface $store = null
    ) {}

    public function getName(): string { return 'database'; }

    public function isAvailable(): bool { return true; }

    public function send(string $recipient, Notification $notification): array
    {
        $record = [
            'id'              => uniqid('dbn_', true),
            'notification_id' => $notification->getId(),
            'recipient'       => $recipient,
            'title'           => $notification->getTitle(),
            'body'            => $notification->getBody(),
            'data'            => json_encode($notification->getData()),
            'read'            => false,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        if ($this->store !== null) {
            $this->store->log([
                'channel'         => 'database',
                'recipient'       => $recipient,
                'notification_id' => $notification->getId(),
                'success'         => true,
                'message_id'      => $record['id'],
                'error'           => null,
                'sent_at'         => $record['created_at'],
            ]);
        } else {
            self::$memoryStore[] = $record;
        }

        return [
            'success'    => true,
            'message_id' => $record['id'],
            'error'      => null,
        ];
    }

    public function sendBatch(array $recipients, Notification $notification): array
    {
        $results = [];
        foreach ($recipients as $recipient) {
            $results[$recipient] = $this->send($recipient, $notification);
        }
        return $results;
    }

    public static function getMemoryStore(): array { return self::$memoryStore; }
    public static function clearMemoryStore(): void { self::$memoryStore = []; }
}
