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
 * Class DrRestoreCommand
 *
 * CLI: php netfusion dr:restore [--to-timestamp=<unix_timestamp>] [--to-tx=<txId>]
 */
class DrRestoreCommand extends Command
{
    protected string $name        = 'dr:restore';
    protected string $description = 'Point-in-Time Recovery (PITR) to a specific microsecond or transaction ID';

    public function handle(array $args = []): int
    {
        $timestamp = $this->option($args, 'to-timestamp', '');
        $txId = $this->option($args, 'to-tx', '');

        try {
            $cdp = dr()->getCdp();

            if ($timestamp !== '') {
                $targetTs = is_numeric($timestamp) ? (float) $timestamp : (float) strtotime($timestamp);
                echo "Replaying mutations to Point-in-Time timestamp: {$targetTs}...\n";
                $reconstituted = $cdp->restoreToTimestamp($targetTs);
            } elseif ($txId !== '') {
                echo "Replaying mutations to Transaction ID '{$txId}'...\n";
                $reconstituted = $cdp->restoreToTx($txId);
            } else {
                echo "Usage:\n";
                echo "  php netfusion dr:restore --to-timestamp=\"1727448000.123456\"\n";
                echo "  php netfusion dr:restore --to-tx=\"tx_abcdef123456\"\n";
                return 0;
            }

            echo "\n=== Point-in-Time Recovery (PITR) Completed ===\n";
            echo "Reconstituted Tables: " . count($reconstituted) . "\n";
            foreach ($reconstituted as $tableName => $rows) {
                echo "  - {$tableName}: " . count($rows) . " active records\n";
            }
            echo "\n";
            return 0;
        } catch (\Throwable $e) {
            $this->error("PITR restore failed: " . $e->getMessage());
            return 1;
        }
    }
}
