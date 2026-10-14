<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Audit/Models/AuditLog.php
| Module    : Core\Audit
| Namespace : NetFusion\Core\Audit\Models
| Purpose   : Active Record model representing persistent audit logs and model revision records.
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

namespace NetFusion\Core\Audit\Models;

use NetFusion\Core\Database\Model;

/**
 * AuditLog Model
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tenant_id
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string|null $old_values
 * @property string|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property string|null $tags
 * @property string $created_at
 */
class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    protected bool $timestamps = false;

    protected array $fillable = [
        'user_id',
        'tenant_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'tags',
        'created_at',
    ];

    /**
     * Decode old values JSON into array.
     *
     * @return array<string, mixed>
     */
    public function getOldValues(): array
    {
        $val = $this->getAttribute('old_values');
        if (empty($val)) {
            return [];
        }

        if (is_array($val)) {
            return $val;
        }

        $decoded = json_decode((string) $val, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decode new values JSON into array.
     *
     * @return array<string, mixed>
     */
    public function getNewValues(): array
    {
        $val = $this->getAttribute('new_values');
        if (empty($val)) {
            return [];
        }

        if (is_array($val)) {
            return $val;
        }

        $decoded = json_decode((string) $val, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Compute field-level diff between old and new values.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getDiff(): array
    {
        $old = $this->getOldValues();
        $new = $this->getNewValues();
        $diff = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal !== $newVal) {
                $diff[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        }

        return $diff;
    }

    /**
     * Find audit entries for a specific entity.
     *
     * @param  string $type
     * @param  int|string $id
     * @return array<static>
     */
    public static function forAuditable(string $type, int|string $id): array
    {
        $rows = static::query()
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map([static::class, 'newFromBuilder'], $rows);
    }

    /**
     * Find audit entries by user.
     *
     * @param  int $userId
     * @return array<static>
     */
    public static function forUser(int $userId): array
    {
        $rows = static::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map([static::class, 'newFromBuilder'], $rows);
    }
}
