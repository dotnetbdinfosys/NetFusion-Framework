<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Drivers/LogBroadcastDriver.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Drivers
| Purpose   : Broadcast driver outputting payload to logs for local development and testing.
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

namespace NetFusion\Core\Broadcasting\Drivers;

use NetFusion\Core\Broadcasting\Contracts\BroadcastDriverInterface;
use NetFusion\Core\Broadcasting\Contracts\ChannelInterface;

/**
 * Class LogBroadcastDriver
 *
 * Logs broadcast events and retains an in-memory buffer of dispatched events.
 *
 * @package NetFusion\Core\Broadcasting\Drivers
 */
class LogBroadcastDriver implements BroadcastDriverInterface
{
    /**
     * In-memory buffer of logged broadcasts.
     *
     * @var array<int, array{channels: array<int, string>, event: string, payload: array<string, mixed>, time: string}>
     */
    protected array $events = [];

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $channelNames = array_map(
            fn($c) => $c instanceof ChannelInterface ? $c->getName() : (string) $c,
            $channels
        );

        $record = [
            'channels' => $channelNames,
            'event'    => $event,
            'payload'  => $payload,
            'time'     => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $record;

        $logPath = function_exists('storage_path') ? storage_path('logs/broadcast.log') : 'storage/logs/broadcast.log';
        $logDir  = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logLine = sprintf(
            "[%s] BROADCAST [%s] on channels [%s] payload: %s\n",
            $record['time'],
            $event,
            implode(', ', $channelNames),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get in-memory logged events.
     *
     * @return array<int, array{channels: array<int, string>, event: string, payload: array<string, mixed>, time: string}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Clear in-memory event buffer.
     */
    public function clear(): void
    {
        $this->events = [];
    }
}
