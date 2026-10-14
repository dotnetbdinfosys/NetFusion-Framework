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
 * Class DrStatusCommand
 *
 * CLI: php netfusion dr:status [--format=text|json]
 */
class DrStatusCommand extends Command
{
    protected string $name        = 'dr:status';
    protected string $description = 'Display multi-region Disaster Recovery topology, quorum status, and RPO lag';

    public function handle(array $args = []): int
    {
        $format = $this->option($args, 'format', 'text');

        try {
            $dr = dr();
            $status = $dr->getStatus();
        } catch (\Throwable $e) {
            $this->error("DR status failed: " . $e->getMessage());
            return 1;
        }

        if ($format === 'json') {
            echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            return 0;
        }

        echo "\n=== NetFusion Autonomous Disaster Recovery (DR) Status ===\n";
        echo "===========================================================\n";
        echo "Primary Node   : " . ($status['primary_node'] ?? 'NONE') . " (" . ($status['primary_region'] ?? 'N/A') . ")\n";
        echo "Cluster Epoch  : " . $status['cluster_epoch'] . "\n";
        echo "Quorum State   : " . ($status['has_quorum'] ? 'HEALTHY' : 'QUORUM_LOST') . " ({$status['online_nodes']}/{$status['total_nodes']} online, min {$status['quorum_min']})\n";
        echo "CDP RPO Lag    : " . $status['current_rpo_sec'] . " seconds\n";
        echo "Journal Records: " . $status['cdp_records'] . "\n\n";

        echo sprintf("%-20s  %-15s  %-10s  %-10s  %-12s\n", "Node ID", "Region", "Role", "Status", "Lag (sec)");
        echo str_repeat("-", 75) . "\n";

        foreach ($status['nodes'] as $node) {
            echo sprintf(
                "%-20s  %-15s  %-10s  %-10s  %-12.4f\n",
                $node['node_id'],
                $node['region'],
                $node['role'],
                $node['status'],
                $node['replication_lag_sec']
            );
        }

        echo "\n";
        return 0;
    }
}
