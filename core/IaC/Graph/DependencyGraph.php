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

namespace NetFusion\Core\IaC\Graph;

/**
 * Directed Acyclic Graph (DAG) computing topological execution order and cycle detection.
 */
class DependencyGraph
{
    /**
     * @var array<string, ResourceNode>
     */
    private array $nodes = [];

    public function addNode(ResourceNode $node): self
    {
        $this->nodes[$node->address] = $node;
        return $this;
    }

    public function getNode(string $address): ?ResourceNode
    {
        return $this->nodes[$address] ?? null;
    }

    /**
     * @return array<string, ResourceNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Register dependency edge: $fromAddress depends on $toAddress.
     * ($toAddress must be provisioned before $fromAddress).
     */
    public function addEdge(string $fromAddress, string $toAddress): self
    {
        if (isset($this->nodes[$fromAddress])) {
            $this->nodes[$fromAddress]->addDependency($toAddress);
        }

        if (isset($this->nodes[$toAddress])) {
            $this->nodes[$toAddress]->addDependent($fromAddress);
        }

        return $this;
    }

    /**
     * Compute topological sorting using Kahn's algorithm.
     *
     * @return array<string>
     */
    public function buildTopologicalOrder(): array
    {
        // Calculate in-degree (number of unmet dependencies) for each node
        $inDegree = [];
        foreach ($this->nodes as $addr => $node) {
            $actualDeps = 0;
            foreach ($node->dependencies as $dep) {
                if (isset($this->nodes[$dep])) {
                    $actualDeps++;
                }
            }
            $inDegree[$addr] = $actualDeps;
        }

        // Collect nodes with 0 in-degree (no prerequisites)
        $queue = [];
        foreach ($inDegree as $addr => $deg) {
            if ($deg === 0) {
                $queue[] = $addr;
            }
        }

        $order = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $order[] = $current;

            $node = $this->nodes[$current] ?? null;
            if ($node === null) {
                continue;
            }

            foreach ($node->dependents as $dependentAddr) {
                if (isset($inDegree[$dependentAddr])) {
                    $inDegree[$dependentAddr]--;
                    if ($inDegree[$dependentAddr] === 0) {
                        $queue[] = $dependentAddr;
                    }
                }
            }
        }

        // If not all nodes were ordered, a cycle exists
        if (count($order) < count($this->nodes)) {
            $cycles = $this->detectCycles();
            throw new \RuntimeException("Circular dependency detected in IaC DAG: " . implode(' -> ', $cycles));
        }

        return $order;
    }

    /**
     * Detect cyclical dependency path.
     *
     * @return array<string>
     */
    public function detectCycles(): array
    {
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($this->nodes) as $addr) {
            $cycle = $this->checkCycleDfs($addr, $visited, $recursionStack);
            if (!empty($cycle)) {
                return $cycle;
            }
        }

        return [];
    }

    private function checkCycleDfs(string $node, array &$visited, array &$stack): array
    {
        if (!empty($stack[$node])) {
            return array_keys($stack);
        }

        if (!empty($visited[$node])) {
            return [];
        }

        $visited[$node] = true;
        $stack[$node] = true;

        $resourceNode = $this->nodes[$node] ?? null;
        if ($resourceNode !== null) {
            foreach ($resourceNode->dependencies as $dep) {
                if (isset($this->nodes[$dep])) {
                    $res = $this->checkCycleDfs($dep, $visited, $stack);
                    if (!empty($res)) {
                        return $res;
                    }
                }
            }
        }

        unset($stack[$node]);
        return [];
    }
}
