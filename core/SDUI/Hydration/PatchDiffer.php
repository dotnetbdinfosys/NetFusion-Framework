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
 * RFC 6902 JSON Patch generator and applicator for delta hydration.
 */
class PatchDiffer
{
    /**
     * Compute JSON Patch operations from source tree to target tree.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $target
     * @param string $path
     * @return array<int, array{op: string, path: string, value?: mixed}>
     */
    public static function diff(array $source, array $target, string $path = ''): array
    {
        $patches = [];

        // Check keys in source that are deleted in target
        foreach ($source as $key => $sourceVal) {
            $currentPath = $path . '/' . self::escapePointer((string) $key);

            if (!array_key_exists($key, $target)) {
                $patches[] = [
                    'op'   => 'remove',
                    'path' => $currentPath,
                ];
            } elseif (is_array($sourceVal) && is_array($target[$key])) {
                $subPatches = self::diff($sourceVal, $target[$key], $currentPath);
                $patches = array_merge($patches, $subPatches);
            } elseif ($sourceVal !== $target[$key]) {
                $patches[] = [
                    'op'    => 'replace',
                    'path'  => $currentPath,
                    'value' => $target[$key],
                ];
            }
        }

        // Check keys in target that are newly added
        foreach ($target as $key => $targetVal) {
            $currentPath = $path . '/' . self::escapePointer((string) $key);

            if (!array_key_exists($key, $source)) {
                $patches[] = [
                    'op'    => 'add',
                    'path'  => $currentPath,
                    'value' => $targetVal,
                ];
            }
        }

        return $patches;
    }

    /**
     * Apply JSON Patch operations to a target schema array.
     *
     * @param array<string, mixed> $target
     * @param array<int, array{op: string, path: string, value?: mixed}> $patches
     * @return array<string, mixed>
     */
    public static function apply(array $target, array $patches): array
    {
        $result = $target;

        foreach ($patches as $patch) {
            $op = $patch['op'] ?? '';
            $path = $patch['path'] ?? '';
            $value = $patch['value'] ?? null;

            $segments = self::parsePointer($path);
            if (empty($segments)) {
                if ($op === 'replace' && is_array($value)) {
                    $result = $value;
                }
                continue;
            }

            $result = self::applyOperation($result, $segments, $op, $value);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, string> $segments
     * @return array<string, mixed>
     */
    protected static function applyOperation(array $target, array $segments, string $op, mixed $value): array
    {
        $current = &$target;
        $total = count($segments);

        for ($i = 0; $i < $total - 1; $i++) {
            $seg = $segments[$i];
            if (!isset($current[$seg]) || !is_array($current[$seg])) {
                $current[$seg] = [];
            }
            $current = &$current[$seg];
        }

        $lastSeg = $segments[$total - 1];

        if ($op === 'replace' || $op === 'add') {
            $current[$lastSeg] = $value;
        } elseif ($op === 'remove') {
            if (is_array($current)) {
                unset($current[$lastSeg]);
                // If it was a numeric array, re-index
                if (array_is_list($current)) {
                    $current = array_values($current);
                }
            }
        }

        return $target;
    }

    /**
     * Parse RFC 6901 JSON pointer into segment array.
     *
     * @return array<int, string>
     */
    public static function parsePointer(string $pointer): array
    {
        if ($pointer === '' || $pointer === '/') {
            return [];
        }

        $trimmed = ltrim($pointer, '/');
        $rawSegments = explode('/', $trimmed);

        return array_map(function (string $segment): string {
            return str_replace(['~1', '~0'], ['/', '~'], $segment);
        }, $rawSegments);
    }

    protected static function escapePointer(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
