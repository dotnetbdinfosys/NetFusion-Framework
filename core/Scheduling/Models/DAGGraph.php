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

namespace NetFusion\Core\Scheduling\Models;

use NetFusion\Core\Scheduling\Exceptions\CycleDetectedException;

class DAGGraph
{
    private string $name;
    /** @var array<string, DAGNode> */
    private array $nodes = [];

    public function __construct(string $name = 'default_dag')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addNode(DAGNode $node): self
    {
        $this->nodes[$node->getId()] = $node;
        return $this;
    }

    public function getNode(string $id): ?DAGNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * @return array<string, DAGNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function addDependency(string $nodeId, string $dependsOnId): self
    {
        if (isset($this->nodes[$nodeId])) {
            $this->nodes[$nodeId]->addDependency($dependsOnId);
        }
        return $this;
    }

    /**
     * Get root nodes (nodes with 0 dependencies)
     * @return array<string, DAGNode>
     */
    public function getRoots(): array
    {
        $roots = [];
        foreach ($this->nodes as $id => $node) {
            if (empty($node->getDependencies())) {
                $roots[$id] = $node;
            }
        }
        return $roots;
    }

    /**
     * Validate DAG for cycles
     * @throws CycleDetectedException
     */
    public function validate(): void
    {
        // Simple DFS cycle check
        $visited = [];
        $recursionStack = [];

        foreach ($this->nodes as $id => $node) {
            if (!isset($visited[$id])) {
                if ($this->hasCycleDFS($id, $visited, $recursionStack)) {
                    throw new CycleDetectedException("Cycle detected in DAG workflow '{$this->name}' at node '{$id}'.");
                }
            }
        }
    }

    private function hasCycleDFS(string $id, array &$visited, array &$recursionStack): bool
    {
        $visited[$id] = true;
        $recursionStack[$id] = true;

        $node = $this->nodes[$id] ?? null;
        if ($node !== null) {
            foreach ($node->getDependencies() as $depId) {
                if (!isset($this->nodes[$depId])) {
                    continue; // external or missing dependency
                }

                if (!isset($visited[$depId])) {
                    if ($this->hasCycleDFS($depId, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$depId]) && $recursionStack[$depId] === true) {
                    return true;
                }
            }
        }

        $recursionStack[$id] = false;
        return false;
    }
}
