<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\SDUI\Hydration;

/**
 * Formats Server-Sent Events (SSE) and WebSocket message frames for live UI updates.
 */
class StreamPusher
{
    /**
     * Format a payload as a standard Server-Sent Event (SSE) block.
     *
     * @param string $event Event type (e.g. 'sdui_patch', 'sdui_screen')
     * @param mixed $data Serializable payload or array
     * @param string|null $id Unique event sequence ID
     */
    public static function formatSse(string $event, mixed $data, ?string $id = null): string
    {
        $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES);
        $output = '';

        if ($id !== null) {
            $output .= "id: {$id}\n";
        }

        $output .= "event: {$event}\n";

        // Multi-line data formatting according to W3C SSE standard
        $lines = explode("\n", (string) $payload);
        foreach ($lines as $line) {
            $output .= "data: {$line}\n";
        }

        $output .= "\n";
        return $output;
    }

    /**
     * Format an RFC 6902 JSON patch as a WebSocket delta message.
     *
     * @param string $screenId
     * @param array<int, array{op: string, path: string, value?: mixed}> $patches
     * @param int $sequence
     * @return array<string, mixed>
     */
    public static function formatWebSocketFrame(string $screenId, array $patches, int $sequence = 1): array
    {
        return [
            'type'      => 'SDUI_PATCH',
            'screenId'  => $screenId,
            'sequence'  => $sequence,
            'timestamp' => time(),
            'patches'   => $patches,
        ];
    }
}
