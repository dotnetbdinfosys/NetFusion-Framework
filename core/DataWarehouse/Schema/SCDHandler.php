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

namespace NetFusion\Core\DataWarehouse\Schema;

class SCDHandler
{
    /**
     * Process SCD Type 1 (In-Place Overwrite).
     *
     * @param array<string, mixed> $existing Current record
     * @param array<string, mixed> $new New attributes
     * @return array<string, mixed> Updated record
     */
    public static function applyType1(array $existing, array $new): array
    {
        return array_merge($existing, $new, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Process SCD Type 2 (Add New Version with Valid-From / Valid-To / is_current).
     *
     * @param array<string, mixed> $existing Current active record
     * @param array<string, mixed> $new New attributes
     * @param int $newSurrogateKey New unique surrogate key
     * @param string|null $timestamp Custom timestamp (defaults to current time)
     * @return array{closed_record: array<string, mixed>, new_record: array<string, mixed>}
     */
    public static function applyType2(
        array $existing,
        array $new,
        int $newSurrogateKey,
        ?string $timestamp = null
    ): array {
        $now = $timestamp ?? date('Y-m-d H:i:s');

        // 1. Close current record
        $closedRecord = array_merge($existing, [
            'valid_to' => $now,
            'is_current' => 0,
        ]);

        // 2. Spawn new versioned record
        $newRecord = array_merge($existing, $new, [
            'id' => $newSurrogateKey,
            'version' => ((int)($existing['version'] ?? 1)) + 1,
            'valid_from' => $now,
            'valid_to' => '9999-12-31 23:59:59',
            'is_current' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'closed_record' => $closedRecord,
            'new_record' => $newRecord,
        ];
    }
}
