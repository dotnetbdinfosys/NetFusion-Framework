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

namespace NetFusion\Core\Graph\Cypher;

use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;

class PatternMatcher
{
    public function __construct(
        protected GraphStoreInterface $store
    ) {
    }

    /**
     * Match path patterns against graph store and return variable bindings.
     *
     * @param array<PathPattern> $patterns
     * @return array<array<string, NodeInterface|EdgeInterface>> List of binding maps
     */
    public function match(array $patterns): array
    {
        if (empty($patterns)) {
            return [[]];
        }

        $bindings = [[]];

        foreach ($patterns as $pathPattern) {
            $pathBindings = $this->matchSinglePath($pathPattern, $bindings);
            $bindings = $pathBindings;
            if (empty($bindings)) {
                return [];
            }
        }

        return $bindings;
    }

    protected function matchSinglePath(PathPattern $path, array $currentBindings): array
    {
        $elements = $path->elements;
        if (empty($elements)) {
            return $currentBindings;
        }

        /** @var NodePattern $startNodePattern */
        $startNodePattern = $elements[0];
        $newBindings = [];

        foreach ($currentBindings as $binding) {
            // Determine candidate start nodes
            $candidateNodes = [];

            if ($startNodePattern->variable !== null && isset($binding[$startNodePattern->variable])) {
                $bound = $binding[$startNodePattern->variable];
                if ($bound instanceof NodeInterface && $this->nodeMatches($bound, $startNodePattern)) {
                    $candidateNodes[] = $bound;
                }
            } else {
                $allNodes = $this->store->getNodes($startNodePattern->label);
                foreach ($allNodes as $node) {
                    if ($this->nodeMatches($node, $startNodePattern)) {
                        $candidateNodes[] = $node;
                    }
                }
            }

            foreach ($candidateNodes as $startNode) {
                $b = $binding;
                if ($startNodePattern->variable !== null) {
                    $b[$startNodePattern->variable] = $startNode;
                }

                $this->expandPath($elements, 1, $startNode, $b, $newBindings);
            }
        }

        return $newBindings;
    }

    protected function expandPath(array $elements, int $index, NodeInterface $currentNode, array $currentBinding, array &$results): void
    {
        if ($index >= count($elements)) {
            $results[] = $currentBinding;
            return;
        }

        /** @var EdgePattern $edgePattern */
        $edgePattern = $elements[$index];
        /** @var NodePattern $targetNodePattern */
        $targetNodePattern = $elements[$index + 1];

        $minHops = $edgePattern->minHops;
        $maxHops = $edgePattern->maxHops ?? 10; // safety ceiling for unbounded

        $this->traverseHops(
            $currentNode,
            $edgePattern,
            $targetNodePattern,
            $minHops,
            $maxHops,
            0,
            [],
            $currentBinding,
            $elements,
            $index + 2,
            $results
        );
    }

    protected function traverseHops(
        NodeInterface $node,
        EdgePattern $edgePattern,
        NodePattern $targetNodePattern,
        int $minHops,
        int $maxHops,
        int $currentHop,
        array $visitedEdges,
        array $binding,
        array $elements,
        int $nextIndex,
        array &$results
    ): void {
        if ($currentHop >= $minHops && $currentHop > 0) {
            if ($this->nodeMatches($node, $targetNodePattern)) {
                $b = $binding;
                if ($targetNodePattern->variable !== null) {
                    // Check if variable already bound and must match
                    if (isset($b[$targetNodePattern->variable]) && $b[$targetNodePattern->variable]->getId() !== $node->getId()) {
                        // Skip mismatch
                    } else {
                        $b[$targetNodePattern->variable] = $node;
                        $this->expandPath($elements, $nextIndex, $node, $b, $results);
                    }
                } else {
                    $this->expandPath($elements, $nextIndex, $node, $b, $results);
                }
            }
        }

        if ($currentHop >= $maxHops) {
            return;
        }

        // Fetch candidate outgoing/incoming edges from node
        $candidateEdges = $this->getCandidateEdges($node->getId(), $edgePattern);

        foreach ($candidateEdges as $edge) {
            if (in_array($edge->getId(), $visitedEdges, true)) {
                // Avoid edge cycles
                continue;
            }

            if (!$this->edgeMatches($edge, $edgePattern)) {
                continue;
            }

            $nextNodeId = ($edge->getSourceId() === $node->getId()) ? $edge->getTargetId() : $edge->getSourceId();
            $nextNode = $this->store->getNode($nextNodeId);

            if ($nextNode === null) {
                continue;
            }

            $newBinding = $binding;
            if ($edgePattern->variable !== null && $currentHop === 0) {
                $newBinding[$edgePattern->variable] = $edge;
            }

            $newVisited = $visitedEdges;
            $newVisited[] = $edge->getId();

            $this->traverseHops(
                $nextNode,
                $edgePattern,
                $targetNodePattern,
                $minHops,
                $maxHops,
                $currentHop + 1,
                $newVisited,
                $newBinding,
                $elements,
                $nextIndex,
                $results
            );
        }
    }

    protected function getCandidateEdges(string $nodeId, EdgePattern $edgePattern): array
    {
        $dir = $edgePattern->direction;
        $type = $edgePattern->type;

        if ($dir === '->') {
            return $this->store->getOutEdges($nodeId, $type);
        }
        if ($dir === '<-') {
            return $this->store->getInEdges($nodeId, $type);
        }

        // Undirected '--'
        $out = $this->store->getOutEdges($nodeId, $type);
        $in = $this->store->getInEdges($nodeId, $type);
        $all = [];
        foreach (array_merge($out, $in) as $e) {
            $all[$e->getId()] = $e;
        }
        return array_values($all);
    }

    public function nodeMatches(NodeInterface $node, NodePattern $pattern): bool
    {
        if ($pattern->label !== null && !$node->hasLabel($pattern->label)) {
            return false;
        }

        foreach ($pattern->properties as $k => $v) {
            if ($node->getProperty($k) !== $v) {
                return false;
            }
        }

        return true;
    }

    public function edgeMatches(EdgeInterface $edge, EdgePattern $pattern): bool
    {
        if ($pattern->type !== null && $edge->getType() !== $pattern->type) {
            return false;
        }

        foreach ($pattern->properties as $k => $v) {
            if ($edge->getProperty($k) !== $v) {
                return false;
            }
        }

        return true;
    }
}
