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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * Class MeshTopologyCommand
 *
 * CLI: php netfusion mesh:topology [--format=text|json|dot]
 */
class MeshTopologyCommand extends Command
{
    protected string $name        = 'mesh:topology';
    protected string $description = 'Inspect Service Mesh topology, active nodes, and service dependencies';

    public function handle(array $args = []): int
    {
        $format = $this->getOption($args, 'format', 'text');

        try {
            $mesh = service_mesh();
            $topology = $mesh->getTopology();
        } catch (\Throwable $e) {
            $this->error("Service mesh unavailable: " . $e->getMessage());
            return 1;
        }

        if ($format === 'json') {
            echo json_encode($topology->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            return 0;
        }

        if ($format === 'dot') {
            echo $topology->toDot();
            return 0;
        }

        // Text table output
        $nodes = $topology->getNodes();
        echo "\n=== NetFusion Zero-Trust Service Mesh Topology ===\n";
        echo "Trust Domain: {$mesh->getTrustDomain()}\n";
        echo sprintf("%-20s  %-35s  %-10s\n", "Service", "SPIFFE ID", "Status");
        echo str_repeat('-', 70) . "\n";

        foreach ($nodes as $name => $node) {
            echo sprintf("%-20s  %-35s  %-10s\n", $name, (string) $node->getSpiffeId(), $node->getStatus());
        }

        $matrix = $topology->getTrafficMatrix();
        if (!empty($matrix)) {
            echo "\nTraffic Matrix (Inbound / Outbound Calls):\n";
            foreach ($matrix as $from => $targets) {
                foreach ($targets as $to => $count) {
                    echo "  {$from} -> {$to} ({$count} calls)\n";
                }
            }
        }
        echo "\n";

        return 0;
    }

    private function getOption(array $args, string $key, string $default = ''): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) {
                return substr($arg, strlen("--{$key}="));
            }
        }
        return $default;
    }
}
