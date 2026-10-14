<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\CRDT\Sync;

/**
 * High-performance delta compression utility for compact network replication.
 */
class DeltaCompressor
{
    /**
     * Compress payload array into a compact base64-encoded string.
     */
    public static function compress(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (function_exists('gzdeflate')) {
            $compressed = gzdeflate($json, 9);
            if ($compressed !== false) {
                return 'gz:' . base64_encode($compressed);
            }
        }
        return 'raw:' . base64_encode($json);
    }

    /**
     * Decompress delta string back to payload array.
     */
    public static function decompress(string $payload): array
    {
        if (str_starts_with($payload, 'gz:')) {
            $raw = base64_decode(substr($payload, 3));
            $json = gzinflate($raw);
            return json_decode($json ?: '{}', true) ?: [];
        }

        if (str_starts_with($payload, 'raw:')) {
            $json = base64_decode(substr($payload, 4));
            return json_decode($json ?: '{}', true) ?: [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
