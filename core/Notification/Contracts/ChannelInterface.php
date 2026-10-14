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

use NetFusion\Core\Notification\Notification;

/**
 * Interface ChannelInterface
 *
 * Contract for a push notification delivery channel.
 * Implementations handle FCM, APNs, Web Push, SMS, Database, etc.
 */
interface ChannelInterface
{
    /** @return string Channel identifier (e.g., "fcm", "apns", "web_push") */
    public function getName(): string;

    /**
     * Send a single notification to a device token / recipient.
     *
     * @param  string       $recipient Device token or user identifier
     * @param  Notification $notification Payload to deliver
     * @return array{success:bool, message_id:string|null, error:string|null}
     */
    public function send(string $recipient, Notification $notification): array;

    /**
     * Send notification to multiple recipients (batch).
     *
     * @param  string[]     $recipients
     * @param  Notification $notification
     * @return array<string, array{success:bool, message_id:string|null, error:string|null}>
     */
    public function sendBatch(array $recipients, Notification $notification): array;

    /** Whether channel is currently available / configured */
    public function isAvailable(): bool;
}
