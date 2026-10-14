<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Audit/Traits/Auditable.php
| Module    : Core\Audit
| Namespace : NetFusion\Core\Audit\Traits
| Purpose   : Trait enabling automatic audit tracking, revision diffs, and audit logs on models.
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

namespace NetFusion\Core\Audit\Traits;

use NetFusion\Core\Audit\AuditManager;
use NetFusion\Core\Audit\Models\AuditLog;

trait Auditable
{
    /**
     * Record a creation audit event for this model.
     *
     * @param  int|null $userId
     * @param  string|null $tags
     * @return bool
     */
    public function auditCreate(?int $userId = null, ?string $tags = null): bool
    {
        /** @var AuditManager $manager */
        $manager = app(AuditManager::class);

        return $manager->record(
            'created',
            static::class,
            $this->getKey(),
            null,
            $this->getAuditableAttributes(),
            $userId,
            $tags,
            $this->getAttribute('tenant_id')
        );
    }

    /**
     * Record an update audit event with diff.
     *
     * @param  array<string, mixed> $oldValues
     * @param  int|null $userId
     * @param  string|null $tags
     * @return bool
     */
    public function auditUpdate(array $oldValues, ?int $userId = null, ?string $tags = null): bool
    {
        /** @var AuditManager $manager */
        $manager = app(AuditManager::class);

        $newValues = $this->getAuditableAttributes();

        return $manager->record(
            'updated',
            static::class,
            $this->getKey(),
            $oldValues,
            $newValues,
            $userId,
            $tags,
            $this->getAttribute('tenant_id')
        );
    }

    /**
     * Record a deletion audit event.
     *
     * @param  int|null $userId
     * @param  string|null $tags
     * @return bool
     */
    public function auditDelete(?int $userId = null, ?string $tags = null): bool
    {
        /** @var AuditManager $manager */
        $manager = app(AuditManager::class);

        return $manager->record(
            'deleted',
            static::class,
            $this->getKey(),
            $this->getAuditableAttributes(),
            null,
            $userId,
            $tags,
            $this->getAttribute('tenant_id')
        );
    }

    /**
     * Record a custom domain audit event.
     *
     * @param  string $event
     * @param  array<string, mixed> $details
     * @param  int|null $userId
     * @return bool
     */
    public function auditCustom(string $event, array $details = [], ?int $userId = null): bool
    {
        /** @var AuditManager $manager */
        $manager = app(AuditManager::class);

        return $manager->record(
            $event,
            static::class,
            $this->getKey(),
            null,
            $details,
            $userId,
            'custom',
            $this->getAttribute('tenant_id')
        );
    }

    /**
     * Retrieve all historical audit log entries for this model instance.
     *
     * @return array<AuditLog>
     */
    public function auditLogs(): array
    {
        return AuditLog::forAuditable(static::class, $this->getKey());
    }

    /**
     * Get auditable attributes filtered by exclusions.
     *
     * @return array<string, mixed>
     */
    public function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $excludes = $this->getAuditExclude();

        foreach ($excludes as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    /**
     * Attributes that should be excluded from audit trails.
     *
     * @return string[]
     */
    public function getAuditExclude(): array
    {
        return property_exists($this, 'auditExclude') && is_array($this->auditExclude)
            ? $this->auditExclude
            : [];
    }
}
