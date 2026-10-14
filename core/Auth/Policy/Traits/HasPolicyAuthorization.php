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

namespace NetFusion\Core\Auth\Policy\Traits;

use NetFusion\Core\Auth\Policy\AccessManager;

trait HasPolicyAuthorization
{
    /**
     * Check if this subject is allowed to perform action on resource.
     */
    public function can(string $action, mixed $resource, array $environment = []): bool
    {
        /** @var AccessManager $access */
        $access = app('access.manager');
        return $access->can($this, $action, $resource, $environment);
    }

    /**
     * Check if this subject is denied from performing action on resource.
     */
    public function cannot(string $action, mixed $resource, array $environment = []): bool
    {
        return !$this->can($action, $resource, $environment);
    }

    /**
     * ReBAC Zanzibar relation check.
     */
    public function hasRelation(string $relation, string $entity): bool
    {
        /** @var AccessManager $access */
        $access = app('access.manager');
        $subjectKey = (new \ReflectionClass($this))->getShortName() . ':' . ($this->id ?? 'unknown');
        return $access->checkRelation(strtolower($subjectKey), $relation, $entity);
    }
}
