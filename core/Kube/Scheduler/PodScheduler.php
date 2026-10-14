<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Scheduler;

use NetFusion\Core\Kube\Exceptions\SchedulerException;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Scheduler\Filters\AffinityFilter;
use NetFusion\Core\Kube\Scheduler\Filters\CapacityFilter;
use NetFusion\Core\Kube\Scheduler\Filters\TaintTolerationFilter;

/**
 * Multi-factor Kubernetes Pod scheduler evaluating filtering predicates and priority scoring.
 */
class PodScheduler
{
    /**
     * @var NodeFilterInterface[]
     */
    protected array $filters = [];

    protected PriorityScorer $scorer;

    /**
     * @param NodeFilterInterface[] $filters
     */
    public function __construct(
        array $filters = [],
        ?PriorityScorer $scorer = null
    ) {
        $this->filters = !empty($filters) ? $filters : [
            new CapacityFilter(),
            new AffinityFilter(),
            new TaintTolerationFilter(),
        ];
        $this->scorer = $scorer ?? new PriorityScorer();
    }

    public function addFilter(NodeFilterInterface $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function setScorer(PriorityScorer $scorer): self
    {
        $this->scorer = $scorer;
        return $this;
    }

    /**
     * Schedule a pod onto the most optimal node from the provided cluster nodes.
     *
     * @param Pod $pod Pod to be scheduled
     * @param Node[] $nodes Available cluster nodes
     * @return Node The selected node where pod was successfully bound
     * @throws SchedulerException When no suitable node satisfies predicates
     */
    public function schedule(Pod $pod, array $nodes): Node
    {
        if (empty($nodes)) {
            throw new SchedulerException("0/{0} nodes available: Cluster has no registered nodes.");
        }

        // 1. Filtering Phase (Predicates)
        $candidates = [];
        $failedReasons = [];

        foreach ($nodes as $node) {
            $passed = true;
            foreach ($this->filters as $filter) {
                if (!$filter->filter($pod, $node)) {
                    $passed = false;
                    $failedReasons[$node->getName()][] = get_class($filter);
                    break;
                }
            }

            if ($passed) {
                $candidates[] = $node;
            }
        }

        if (empty($candidates)) {
            $total = count($nodes);
            $reasonSummary = [];
            foreach ($failedReasons as $nodeName => $reasons) {
                $short = array_map(fn($r) => basename(str_replace('\\', '/', $r)), $reasons);
                $reasonSummary[] = "{$nodeName}: " . implode(', ', $short);
            }
            $details = implode('; ', $reasonSummary);
            throw new SchedulerException("0/{$total} nodes available: Insufficient capacity or failed predicates. [{$details}]");
        }

        // 2. Scoring Phase (Priorities)
        $bestNode = null;
        $highestScore = -1.0;

        foreach ($candidates as $node) {
            $score = $this->scorer->score($pod, $node);
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestNode = $node;
            }
        }

        if ($bestNode === null) {
            $bestNode = $candidates[0];
        }

        // 3. Binding Phase
        $allocated = $bestNode->allocate($pod->getTotalResources(), $pod->getName());
        if (!$allocated) {
            throw new SchedulerException("Failed to bind pod '{$pod->getName()}' to node '{$bestNode->getName()}': Resource race condition.");
        }

        $pod->setNodeName($bestNode->getName());

        return $bestNode;
    }
}
