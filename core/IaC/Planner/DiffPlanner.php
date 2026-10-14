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

namespace NetFusion\Core\IaC\Planner;

use NetFusion\Core\IaC\Ast\AstBlock;
use NetFusion\Core\IaC\Ast\BlockType;
use NetFusion\Core\IaC\Graph\DependencyGraph;
use NetFusion\Core\IaC\Graph\ResourceNode;
use NetFusion\Core\IaC\Plan\ActionType;
use NetFusion\Core\IaC\Plan\ExecutionPlan;
use NetFusion\Core\IaC\Plan\ResourceChange;
use NetFusion\Core\IaC\State\StateFile;

/**
 * Differential engine comparing desired AST configuration against current StateFile.
 */
class DiffPlanner
{
    /**
     * @var array<string> Attributes requiring full destroy-and-recreate
     */
    private array $immutableAttributes = [
        'cidr_block',
        'availability_zone',
        'bucket_prefix',
        'network_interface_id',
    ];

    /**
     * Plan infrastructure mutations between desired AST blocks and current StateFile.
     *
     * @param array<AstBlock> $desiredBlocks
     * @param StateFile|null $currentState
     * @param array<string, mixed> $variables
     * @return ExecutionPlan
     */
    public function plan(array $desiredBlocks, ?StateFile $currentState = null, array $variables = []): ExecutionPlan
    {
        $currentState ??= new StateFile();

        // Index desired managed resources by canonical address (e.g. "aws_vpc.main")
        /** @var array<string, AstBlock> $desiredMap */
        $desiredMap = [];
        foreach ($desiredBlocks as $block) {
            if ($block->blockType === BlockType::RESOURCE) {
                $desiredMap[$block->getAddress()] = $block;
            }
        }

        $changes = [];
        $graph = new DependencyGraph();

        // 1. Detect CREATE, UPDATE, REPLACE, NOOP
        foreach ($desiredMap as $address => $astBlock) {
            $stateRes = $currentState->getResource($address);
            $instance = $stateRes?->getPrimaryInstance();
            $beforeAttrs = $instance?->attributes;
            $afterAttrs = $this->resolveReferencesAgainstState($astBlock->attributes, $currentState);

            $node = new ResourceNode(
                address: $address,
                astBlock: $astBlock,
                stateResource: $stateRes,
                dependencies: $astBlock->getDependencies()
            );
            $graph->addNode($node);

            if ($stateRes === null || $instance === null) {
                $changes[] = new ResourceChange(
                    address: $address,
                    action: ActionType::CREATE,
                    before: null,
                    after: $afterAttrs,
                    changedAttributes: array_keys($afterAttrs)
                );
            } else {
                $diff = $this->diffAttributes($beforeAttrs ?? [], $afterAttrs);
                if (empty($diff)) {
                    $changes[] = new ResourceChange(
                        address: $address,
                        action: ActionType::NOOP,
                        before: $beforeAttrs,
                        after: $afterAttrs,
                        changedAttributes: []
                    );
                } else {
                    $isReplace = $this->hasImmutableChange($diff);
                    $action = $isReplace ? ActionType::REPLACE : ActionType::UPDATE;
                    $changes[] = new ResourceChange(
                        address: $address,
                        action: $action,
                        before: $beforeAttrs,
                        after: $afterAttrs,
                        changedAttributes: $diff
                    );
                }
            }
        }

        // 2. Detect DESTROY (resources present in state but removed from desired AST)
        foreach ($currentState->resources as $address => $stateRes) {
            if (!isset($desiredMap[$address]) && $stateRes->mode === 'managed') {
                $instance = $stateRes->getPrimaryInstance();
                $beforeAttrs = $instance?->attributes;

                $changes[] = new ResourceChange(
                    address: $address,
                    action: ActionType::DESTROY,
                    before: $beforeAttrs,
                    after: null,
                    changedAttributes: array_keys($beforeAttrs ?? [])
                );

                $graph->addNode(new ResourceNode(
                    address: $address,
                    astBlock: null,
                    stateResource: $stateRes,
                    dependencies: []
                ));
            }
        }

        // 3. Connect dependency edges in graph
        foreach ($desiredMap as $address => $astBlock) {
            foreach ($astBlock->getDependencies() as $dep) {
                if ($graph->getNode($dep) !== null) {
                    $graph->addEdge($address, $dep);
                }
            }
        }

        $topologicalOrder = $graph->buildTopologicalOrder();

        return new ExecutionPlan(
            planId: uniqid('plan_', true),
            timestamp: time(),
            changes: $changes,
            variables: $variables,
            topologicalOrder: $topologicalOrder
        );
    }

    /**
     * Compute array of attribute keys that changed between state and target.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string>
     */
    private function diffAttributes(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $val) {
            if ($key === 'id' || $key === 'arn') {
                continue; // Computed server attributes
            }

            if (!array_key_exists($key, $before) || $before[$key] !== $val) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function hasImmutableChange(array $changedAttributes): bool
    {
        foreach ($changedAttributes as $attr) {
            if (in_array($attr, $this->immutableAttributes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve cross-resource references against current state if available.
     *
     * @param mixed $value
     * @param StateFile $state
     * @return mixed
     */
    private function resolveReferencesAgainstState(mixed $value, StateFile $state): mixed
    {
        if (is_string($value)) {
            // Check for exact single reference
            if (preg_match('/^(?:\$\{)?([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\})?$/', $value, $m)) {
                $addr = $m[1] . '.' . $m[2];
                $attr = $m[3];
                $res = $state->getResource($addr);
                $inst = $res?->getPrimaryInstance();
                if ($inst !== null && array_key_exists($attr, $inst->attributes)) {
                    return $inst->attributes[$attr];
                }
            }

            // Replace embedded references
            return preg_replace_callback('/(?:\$\{)?([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\})?/', function ($matches) use ($state) {
                $addr = $matches[1] . '.' . $matches[2];
                $attr = $matches[3];
                $res = $state->getResource($addr);
                $inst = $res?->getPrimaryInstance();
                if ($inst !== null && array_key_exists($attr, $inst->attributes)) {
                    return (string) $inst->attributes[$attr];
                }
                return $matches[0];
            }, $value);
        }

        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $k => $v) {
                $resolved[$k] = $this->resolveReferencesAgainstState($v, $state);
            }
            return $resolved;
        }

        return $value;
    }
}
