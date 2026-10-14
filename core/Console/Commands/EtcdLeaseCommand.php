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
 * Class EtcdLeaseCommand
 *
 * CLI: php netfusion etcd:lease <grant|revoke|keep-alive|list> [ttl|id]
 */
class EtcdLeaseCommand extends Command
{
    protected string $name = 'etcd:lease';
    protected string $description = 'Manage distributed leases in etcd (grant, revoke, keep-alive, list)';

    public function handle(array $args = []): int
    {
        $action = null;
        $param = null;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $action = $positional[0] ?? 'list';
        $param = $positional[1] ?? null;

        $etcd = function_exists('etcd') ? etcd() : $this->app->make(\NetFusion\Core\Etcd\EtcdManager::class);

        switch (strtolower($action)) {
            case 'grant':
                $ttl = $param !== null ? (int) $param : 60;
                $lease = $etcd->leaseGrant($ttl);
                $this->info("Lease granted successfully: ID {$lease->id} (TTL: {$lease->ttl}s)");
                return 0;

            case 'revoke':
                if ($param === null) {
                    $this->error('Usage: php netfusion etcd:lease revoke <lease_id>');
                    return 1;
                }
                $ok = $etcd->leaseRevoke((int) $param);
                if ($ok) {
                    $this->info("Lease {$param} revoked successfully.");
                } else {
                    $this->error("Lease {$param} not found.");
                }
                return $ok ? 0 : 1;

            case 'keep-alive':
            case 'keepalive':
                if ($param === null) {
                    $this->error('Usage: php netfusion etcd:lease keep-alive <lease_id>');
                    return 1;
                }
                $rem = $etcd->leaseKeepAlive((int) $param);
                if ($rem >= 0) {
                    $this->info("Lease {$param} refreshed: remaining TTL {$rem}s.");
                } else {
                    $this->error("Lease {$param} not found or expired.");
                }
                return $rem >= 0 ? 0 : 1;

            case 'list':
            default:
                $leases = $etcd->leases();
                if (empty($leases)) {
                    $this->line('No active leases found.');
                    return 0;
                }

                $this->line(sprintf('%-20s %-10s %-15s %-20s', 'LEASE ID', 'TTL', 'REMAINING', 'ATTACHED KEYS'));
                $this->line(str_repeat('-', 70));
                foreach ($leases as $lease) {
                    $keysStr = implode(', ', $lease->getKeys());
                    $this->line(sprintf(
                        '%-20d %-10d %-15d %-20s',
                        $lease->id,
                        $lease->ttl,
                        $lease->remainingTtl(),
                        $keysStr ?: '(none)'
                    ));
                }
                return 0;
        }
    }
}
