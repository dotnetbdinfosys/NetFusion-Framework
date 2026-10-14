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

namespace NetFusion\Core\ServiceMesh\Mesh;

/**
 * Class MeshTopology
 *
 * Represents the directed dependency graph and traffic matrix across the service mesh.
 */
class MeshTopology
{
    /** @var array<string, MeshNode> serviceName => MeshNode */
    private array $nodes = [];

    /** @var array<string, array<string, int>> fromService => [toService => callCount] */
    private array $edges = [];

    public function addNode(MeshNode $node): self
    {
        $this->nodes[$node->getServiceName()] = $node;
        return $this;
    }

    public function getNode(string $serviceName): ?MeshNode
    {
        return $this->nodes[$serviceName] ?? null;
    }

    public function recordTraffic(string $fromService, string $toService): void
    {
        $this->edges[$fromService] ??= [];
        $this->edges[$fromService][$toService] = ($this->edges[$fromService][$toService] ?? 0) + 1;
    }

    public function getDependencies(string $serviceName): array
    {
        return array_keys($this->edges[$serviceName] ?? []);
    }

    public function getConsumers(string $serviceName): array
    {
        $consumers = [];
        foreach ($this->edges as $from => $targets) {
            if (isset($targets[$serviceName])) {
                $consumers[] = $from;
            }
        }
        return $consumers;
    }

    public function getTrafficMatrix(): array
    {
        return $this->edges;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function toDot(): string
    {
        $dot = "digraph ServiceMesh {\n";
        $dot .= "  rankdir=LR;\n";
        $dot .= "  node [shape=box, style=rounded, fontname=\"Arial\"];\n\n";

        foreach ($this->nodes as $name => $node) {
            $color = match ($node->getStatus()) {
                'HEALTHY' => 'green',
                'DEGRADED' => 'orange',
                default => 'red'
            };
            $dot .= "  \"{$name}\" [label=\"{$name}\\n{$node->getStatus()}\", color={$color}];\n";
        }

        $dot .= "\n";
        foreach ($this->edges as $from => $targets) {
            foreach ($targets as $to => $count) {
                $dot .= "  \"{$from}\" -> \"{$to}\" [label=\"{$count} reqs\"];\n";
            }
        }

        $dot .= "}\n";
        return $dot;
    }

    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn(MeshNode $n) => $n->toArray(), $this->nodes),
            'edges' => $this->edges,
            'node_count' => count($this->nodes),
            'edge_count' => array_sum(array_map('count', $this->edges)),
        ];
    }
}
