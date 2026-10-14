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

interface GraphStoreInterface
{
    public function addNode(NodeInterface $node): static;
    public function getNode(string $id): ?NodeInterface;
    public function hasNode(string $id): bool;
    public function removeNode(string $id): bool;

    public function addEdge(EdgeInterface $edge): static;
    public function getEdge(string $id): ?EdgeInterface;
    public function hasEdge(string $id): bool;
    public function removeEdge(string $id): bool;

    /**
     * @param string $nodeId
     * @param string|null $type Optional relationship type filter
     * @return array<EdgeInterface>
     */
    public function getOutEdges(string $nodeId, ?string $type = null): array;

    /**
     * @param string $nodeId
     * @param string|null $type Optional relationship type filter
     * @return array<EdgeInterface>
     */
    public function getInEdges(string $nodeId, ?string $type = null): array;

    /**
     * @param string $nodeId
     * @param string|null $type Optional relationship type filter
     * @param string $direction 'out', 'in', or 'both'
     * @return array<NodeInterface>
     */
    public function getAdjacentNodes(string $nodeId, ?string $type = null, string $direction = 'both'): array;

    /**
     * @param string|null $label Optional label filter
     * @return array<string, NodeInterface>
     */
    public function getNodes(?string $label = null): array;

    /**
     * @param string|null $type Optional edge type filter
     * @return array<string, EdgeInterface>
     */
    public function getEdges(?string $type = null): array;

    public function countNodes(): int;
    public function countEdges(): int;
    public function clear(): void;
}
