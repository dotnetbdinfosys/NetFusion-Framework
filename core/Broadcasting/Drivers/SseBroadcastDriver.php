<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/Drivers/SseBroadcastDriver.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting\Drivers
| Purpose   : High-performance Server-Sent Events (SSE) broadcast transport driver.
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
 * Class SseBroadcastDriver
 *
 * Implements Server-Sent Events (SSE) message queueing, persistence,
 * Last-Event-ID resumption, and SSE wire-format generation.
 *
 * @package NetFusion\Core\Broadcasting\Drivers
 */
class SseBroadcastDriver implements BroadcastDriverInterface
{
    protected string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (
            function_exists('storage_path') 
                ? storage_path('framework/broadcasting/sse') 
                : 'storage/framework/broadcasting/sse'
        );

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $now = microtime(true);
        $eventId = (int) ($now * 10000); // Unique microsecond ID

        $channelNames = array_map(
            fn($c) => $c instanceof ChannelInterface ? $c->getName() : (string) $c,
            $channels
        );

        $record = [
            'id'        => $eventId,
            'channels'  => $channelNames,
            'event'     => $event,
            'payload'   => $payload,
            'timestamp' => time(),
        ];

        $filename = sprintf('%016d_%s.json', $eventId, substr(md5($event), 0, 8));
        $filePath = $this->storageDir . DIRECTORY_SEPARATOR . $filename;

        @file_put_contents(
            $filePath,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /**
     * Retrieve queued events for the given channels since the specified Last-Event-ID.
     *
     * @param array<int, string> $channels Subscribed channel names.
     * @param int|string         $lastEventId ID of the last event received by the client.
     * @param int                $limit Maximum number of events to fetch.
     * @return array<int, array{id: int, channels: array<int, string>, event: string, payload: array<string, mixed>, timestamp: int}>
     */
    public function getEventsSince(array $channels, int|string $lastEventId = 0, int $limit = 50): array
    {
        $lastId = (int) $lastEventId;
        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files);

        $matchingEvents = [];

        foreach ($files as $file) {
            $basename = basename($file);
            $parts = explode('_', $basename, 2);
            $fileId = (int) $parts[0];

            if ($fileId <= $lastId) {
                continue;
            }

            $content = @file_get_contents($file);
            if (!$content) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || empty($data['channels'])) {
                continue;
            }

            // Check if any subscribed channel matches the event channels
            $hasChannelMatch = !empty(array_intersect($channels, $data['channels'])) || in_array('*', $channels, true);
            if ($hasChannelMatch) {
                $matchingEvents[] = $data;
                if (count($matchingEvents) >= $limit) {
                    break;
                }
            }
        }

        return $matchingEvents;
    }

    /**
     * Format a message according to the W3C Server-Sent Events (SSE) specification.
     *
     * @param string $event Event name
     * @param array<string, mixed>|string $data Event payload
     * @param int|string|null $id Optional event ID for reconnection resumption
     * @param int|null $retry Client reconnect retry time in milliseconds
     */
    public function formatSseMessage(string $event, array|string $data, int|string|null $id = null, ?int $retry = null): string
    {
        $payloadStr = is_array($data)
            ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : (string) $data;

        $lines = [];

        if ($id !== null) {
            $lines[] = "id: {$id}";
        }

        if ($retry !== null) {
            $lines[] = "retry: {$retry}";
        }

        $lines[] = "event: {$event}";

        // Multi-line data handling (each line must start with "data: ")
        $dataLines = explode("\n", $payloadStr);
        foreach ($dataLines as $dl) {
            $lines[] = "data: {$dl}";
        }

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * Generate an SSE heartbeat comment line to keep long-lived connections alive.
     */
    public function formatHeartbeat(): string
    {
        return ": ping\n\n";
    }

    /**
     * Prune SSE messages older than given retention seconds (default: 1 hour).
     */
    public function pruneOldEvents(int $ttlSeconds = 3600): int
    {
        $threshold = time() - $ttlSeconds;
        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Clear all stored SSE events (useful for testing or cache reset).
     */
    public function clear(): void
    {
        $files = glob($this->storageDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
