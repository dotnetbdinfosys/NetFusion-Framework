<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Features/Drivers/DatabaseDriver.php
| Module    : Features\Drivers
| Namespace : NetFusion\Core\Features\Drivers
| Purpose   : Database persistent storage driver for Feature Flags.
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

namespace NetFusion\Core\Features\Drivers;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Features\Contracts\FeatureDriverInterface;

/**
 * Class DatabaseDriver
 *
 * Persistent database-backed feature flag storage.
 *
 * @package NetFusion\Core\Features\Drivers
 */
class DatabaseDriver implements FeatureDriverInterface
{
    /**
     * @param DatabaseManager $db
     * @param string          $table Table name (default: 'feature_flags')
     */
    public function __construct(
        protected DatabaseManager $db,
        protected string $table = 'feature_flags'
    ) {
    }

    public function getAll(): array
    {
        $rows = $this->db->table($this->table)->get();
        $result = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $result[$name] = [
                'status'     => (string) $row['status'],
                'percentage' => $row['percentage'] !== null ? (int) $row['percentage'] : null,
                'rules'      => is_string($row['rules']) ? json_decode($row['rules'], true) : ($row['rules'] ?? []),
            ];
        }

        return $result;
    }

    public function get(string $feature): ?array
    {
        $row = $this->db->table($this->table)->where('name', $feature)->first();

        if ($row === null) {
            return null;
        }

        return [
            'status'     => (string) $row['status'],
            'percentage' => $row['percentage'] !== null ? (int) $row['percentage'] : null,
            'rules'      => is_string($row['rules']) ? json_decode($row['rules'], true) : ($row['rules'] ?? []),
        ];
    }

    public function set(string $feature, array $data): bool
    {
        $existing = $this->db->table($this->table)->where('name', $feature)->first();
        $now = date('Y-m-d H:i:s');

        $record = [
            'name'       => $feature,
            'status'     => $data['status'] ?? 'active',
            'percentage' => $data['percentage'] ?? null,
            'rules'      => isset($data['rules']) ? json_encode($data['rules']) : null,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            return $this->db->table($this->table)->where('name', $feature)->update($record) >= 0;
        }

        $record['created_at'] = $now;
        $this->db->table($this->table)->insert($record);
        return true;
    }

    public function delete(string $feature): bool
    {
        return $this->db->table($this->table)->where('name', $feature)->delete() > 0;
    }

    public function purge(): bool
    {
        return $this->db->table($this->table)->delete() >= 0;
    }
}
