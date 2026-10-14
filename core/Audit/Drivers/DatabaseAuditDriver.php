<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Audit/Drivers/DatabaseAuditDriver.php
| Module    : Core\Audit
| Namespace : NetFusion\Core\Audit\Drivers
| Purpose   : Database storage driver for persisting and pruning enterprise audit trails.
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

namespace NetFusion\Core\Audit\Drivers;

use DateTimeImmutable;
use NetFusion\Core\Audit\Contracts\AuditDriverInterface;
use NetFusion\Core\Database\DatabaseManager;

class DatabaseAuditDriver implements AuditDriverInterface
{
    protected DatabaseManager $db;
    protected string $table;

    public function __construct(DatabaseManager $db, string $table = 'audit_logs')
    {
        $this->db = $db;
        $this->table = $table;
    }

    /**
     * Record an audit event entry.
     *
     * @param  array<string, mixed> $data
     * @return bool
     */
    public function record(array $data): bool
    {
        if (isset($data['old_values']) && is_array($data['old_values'])) {
            $data['old_values'] = json_encode($data['old_values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (isset($data['new_values']) && is_array($data['new_values'])) {
            $data['new_values'] = json_encode($data['new_values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $id = $this->db->table($this->table)->insert($data);

        return $id !== 0 && $id !== '0' && $id !== false;
    }

    /**
     * Prune audit records older than the given number of days.
     *
     * @param  int $days
     * @return int Number of pruned records
     */
    public function prune(int $days): int
    {
        $cutoff = (new DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s');

        return $this->db->table($this->table)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
