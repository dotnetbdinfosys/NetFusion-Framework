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

namespace NetFusion\Core\Graph\Contracts;

use NetFusion\Core\Graph\Models\Path;

interface TraverserInterface
{
    /**
     * Breadth-First Search traversal from startNode.
     *
     * @param string $startNodeId
     * @param int $maxDepth
     * @param callable|null $visitor Callback fn(NodeInterface $node, int $depth): bool (return false to stop branch)
     * @return array<string, int> NodeId => Depth map
     */
    public function bfs(string $startNodeId, int $maxDepth = PHP_INT_MAX, ?callable $visitor = null): array;

    /**
     * Depth-First Search traversal from startNode.
     *
     * @param string $startNodeId
     * @param int $maxDepth
     * @param callable|null $visitor
     * @return array<string> Visited Node IDs in DFS order
     */
    public function dfs(string $startNodeId, int $maxDepth = PHP_INT_MAX, ?callable $visitor = null): array;

    /**
     * Find shortest weighted path from startNode to targetNode (e.g. Dijkstra).
     *
     * @param string $startNodeId
     * @param string $targetNodeId
     * @param string|null $relationshipType
     * @return Path|null
     */
    public function shortestPath(string $startNodeId, string $targetNodeId, ?string $relationshipType = null): ?Path;

    /**
     * Find all simple paths between startNode and targetNode up to maxHops.
     *
     * @param string $startNodeId
     * @param string $targetNodeId
     * @param int $maxHops
     * @param string|null $relationshipType
     * @return array<Path>
     */
    public function allPaths(string $startNodeId, string $targetNodeId, int $maxHops = 5, ?string $relationshipType = null): array;
}
