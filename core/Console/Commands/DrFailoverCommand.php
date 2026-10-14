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
 * Class DrFailoverCommand
 *
 * CLI: php netfusion dr:failover [--target=<nodeId>] [--force]
 */
class DrFailoverCommand extends Command
{
    protected string $name        = 'dr:failover';
    protected string $description = 'Trigger automated cross-region failover and primary promotion';

    public function handle(array $args = []): int
    {
        $target = $this->option($args, 'target', '');
        $force = in_array('--force', $args, true);

        try {
            $dr = dr();

            if ($target === '') {
                // Pick first available online standby
                foreach ($dr->getQuorum()->getNodes() as $node) {
                    if ($node->isStandby()) {
                        $target = $node->nodeId;
                        break;
                    }
                }
            }

            if ($target === '') {
                $this->error("No suitable standby candidate found for failover");
                return 1;
            }

            echo "Initiating Disaster Recovery Failover to '{$target}'...\n";
            $res = $dr->promoteStandby($target, $force);

            if (!$res['success']) {
                $this->error("Failover rejected: " . $res['reason']);
                return 1;
            }

            echo "\n=== Failover Completed Successfully ===\n";
            echo "New Primary Node   : " . $res['primary_node'] . "\n";
            echo "New Primary Region : " . $res['primary_region'] . "\n";
            echo "Cluster Epoch      : " . $res['epoch'] . "\n";
            echo "Fencing Token      : Epoch " . $res['fencing_token']['epoch'] . "\n";
            echo "Measured RTO       : " . $res['measured_rto_sec'] . " seconds\n";
            echo "Measured RPO       : " . $res['measured_rpo_sec'] . " seconds\n";
            echo "Diverted Domains   : " . implode(', ', $res['diverted_domains']) . "\n\n";

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failover failed: " . $e->getMessage());
            return 1;
        }
    }
}
