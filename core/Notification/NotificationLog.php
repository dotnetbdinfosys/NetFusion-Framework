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

namespace NetFusion\Core\Notification;

/**
 * Class NotificationLog
 *
 * In-memory delivery tracking log with basic statistics.
 * Can be extended to use a persistent store.
 */
class NotificationLog
{
    /** @var array[] */
    private array $records = [];

    public function record(
        string  $channel,
        string  $recipient,
        string  $notificationId,
        bool    $success,
        ?string $messageId = null,
        ?string $error     = null
    ): void {
        $this->records[] = [
            'channel'         => $channel,
            'recipient'       => $recipient,
            'notification_id' => $notificationId,
            'success'         => $success,
            'message_id'      => $messageId,
            'error'           => $error,
            'sent_at'         => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(
        ?string $channel   = null,
        ?bool   $successOnly = null
    ): array {
        return array_values(array_filter($this->records, function ($r) use ($channel, $successOnly) {
            if ($channel !== null && $r['channel'] !== $channel) return false;
            if ($successOnly !== null && $r['success'] !== $successOnly) return false;
            return true;
        }));
    }

    public function countSuccess(): int
    {
        return count(array_filter($this->records, fn($r) => $r['success'] === true));
    }

    public function countFailure(): int
    {
        return count(array_filter($this->records, fn($r) => $r['success'] === false));
    }

    public function total(): int { return count($this->records); }

    public function clear(): void { $this->records = []; }

    public function toArray(): array { return $this->records; }
}
