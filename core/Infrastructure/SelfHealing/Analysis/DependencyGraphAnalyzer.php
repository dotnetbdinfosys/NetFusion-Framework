<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Analysis/DependencyGraphAnalyzer.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing\Analysis;

/**
 * Service Topology and Dependency Graph Analyzer for Causal Fault Propagation.
 */
class DependencyGraphAnalyzer
{
    /** @var array<string, array<string>> caller => [callee1, callee2] */
    private array $forwardEdges = [];

    /** @var array<string, array<string>> callee => [caller1, caller2] */
    private array $reverseEdges = [];

    /**
     * Register a directed dependency edge: $caller depends on $callee.
     */
    public function addDependency(string $caller, string $callee): self
    {
        $this->forwardEdges[$caller][$callee] = $callee;
        $this->reverseEdges[$callee][$caller] = $caller;

        if (!isset($this->forwardEdges[$callee])) {
            $this->forwardEdges[$callee] = [];
        }
        if (!isset($this->reverseEdges[$caller])) {
            $this->reverseEdges[$caller] = [];
        }

        return $this;
    }

    /**
     * Get downstream services that the given service calls.
     *
     * @return string[]
     */
    public function getDownstream(string $service): array
    {
        return array_values($this->forwardEdges[$service] ?? []);
    }

    /**
     * Get upstream services that call the given service.
     *
     * @return string[]
     */
    public function getUpstream(string $service): array
    {
        return array_values($this->reverseEdges[$service] ?? []);
    }

    /**
     * Analyze a set of anomalous services to isolate root causes and causal chains.
     * In cascading failures, the deepest leaf service in the dependency chain is the primary culprit.
     *
     * @param string[] $anomalousServices
     * @return array{primary_culprit: string, causal_chain: string[]}
     */
    public function findCausalPath(array $anomalousServices): array
    {
        if (empty($anomalousServices)) {
            return ['primary_culprit' => 'unknown', 'causal_chain' => []];
        }

        $unique = array_values(array_unique($anomalousServices));
        if (count($unique) === 1) {
            return ['primary_culprit' => $unique[0], 'causal_chain' => [$unique[0]]];
        }

        // Rank services by depth in the anomalous subgraph:
        // A service that calls other anomalous services is higher in the stack.
        // A service that calls NO other anomalous services is the bottom-most leaf (root cause).
        $outDegree = [];
        foreach ($unique as $service) {
            $calledAnomalous = 0;
            foreach ($this->getDownstream($service) as $downstream) {
                if (in_array($downstream, $unique, true)) {
                    $calledAnomalous++;
                }
            }
            $outDegree[$service] = $calledAnomalous;
        }

        // Lowest outDegree to other anomalous services means deepest bottleneck / leaf
        asort($outDegree);
        $primaryCulprit = (string)array_key_first($outDegree);

        // Build causal path from topmost caller down to culprit
        arsort($outDegree);
        $causalChain = array_keys($outDegree);

        return [
            'primary_culprit' => $primaryCulprit,
            'causal_chain'    => $causalChain,
        ];
    }
}
