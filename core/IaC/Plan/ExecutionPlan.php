<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\IaC\Plan;

use JsonSerializable;

/**
 * Complete immutable execution plan describing infrastructure mutations and dependencies.
 */
class ExecutionPlan implements JsonSerializable
{
    /**
     * @var array<string, ResourceChange> Keyed by resource address
     */
    public array $changes = [];

    /**
     * @param string $planId
     * @param int $timestamp
     * @param array<ResourceChange> $changes
     * @param array<string, mixed> $variables
     * @param array<string> $topologicalOrder Ordered resource addresses for sequential execution
     */
    public function __construct(
        public readonly string $planId,
        public readonly int $timestamp,
        array $changes = [],
        public readonly array $variables = [],
        public readonly array $topologicalOrder = []
    ) {
        foreach ($changes as $change) {
            $this->changes[$change->address] = $change;
        }
    }

    public function getChange(string $address): ?ResourceChange
    {
        return $this->changes[$address] ?? null;
    }

    /**
     * @return array<ResourceChange>
     */
    public function getChanges(): array
    {
        return array_values($this->changes);
    }

    /**
     * Tally proposed mutations by action type.
     *
     * @return array{create: int, update: int, replace: int, destroy: int, noop: int}
     */
    public function getCounts(): array
    {
        $counts = [
            'create' => 0,
            'update' => 0,
            'replace' => 0,
            'destroy' => 0,
            'noop' => 0,
        ];

        foreach ($this->changes as $change) {
            $counts[$change->action->value]++;
        }

        return $counts;
    }

    public function hasChanges(): bool
    {
        $counts = $this->getCounts();
        return ($counts['create'] + $counts['update'] + $counts['replace'] + $counts['destroy']) > 0;
    }

    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'timestamp' => $this->timestamp,
            'variables' => $this->variables,
            'topological_order' => $this->topologicalOrder,
            'counts' => $this->getCounts(),
            'resource_changes' => array_map(fn(ResourceChange $c) => $c->toArray(), array_values($this->changes)),
        ];
    }

    public static function fromArray(array $data): self
    {
        $changes = [];
        if (!empty($data['resource_changes']) && is_array($data['resource_changes'])) {
            foreach ($data['resource_changes'] as $cData) {
                $changes[] = $cData instanceof ResourceChange ? $cData : ResourceChange::fromArray($cData);
            }
        }

        return new self(
            planId: (string) ($data['plan_id'] ?? uniqid('plan_', true)),
            timestamp: (int) ($data['timestamp'] ?? time()),
            changes: $changes,
            variables: (array) ($data['variables'] ?? []),
            topologicalOrder: (array) ($data['topological_order'] ?? [])
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
