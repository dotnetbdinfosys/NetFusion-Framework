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

namespace NetFusion\Core\Notification\Contracts;

/**
 * Interface NotificationStoreInterface
 *
 * Persistence contract for notification delivery logs.
 */
interface NotificationStoreInterface
{
    /**
     * Persist a delivery attempt record.
     *
     * @param array{
     *   channel:string, recipient:string, notification_id:string,
     *   success:bool, message_id:string|null, error:string|null,
     *   sent_at:string
     * } $record
     */
    public function log(array $record): void;

    /**
     * Retrieve delivery logs, optionally filtered.
     *
     * @return array[]
     */
    public function getLogs(
        ?string $channel     = null,
        ?string $recipient   = null,
        ?bool   $successOnly = null,
        int     $limit       = 100
    ): array;

    /** Count delivered notifications in a time window */
    public function countDelivered(string $channel, int $windowSeconds = 3600): int;
}
