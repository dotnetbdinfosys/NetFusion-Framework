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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * CLI command to view and manage SWIM gossip cluster members.
 * Usage: php netfusion consul:members <list|join> [address] [--port=8301] [--name=]
 */
class ConsulMembersCommand extends Command
{
    protected string $name = 'consul:members';
    protected string $description = 'Inspect and manage SWIM gossip cluster members';

    public function handle(array $args = []): int
    {
        $action = 'list';
        $address = null;
        $port = 8301;
        $nodeName = '';

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--port=')) {
                $port = (int)substr($arg, 7);
            } elseif (str_starts_with($arg, '--name=')) {
                $nodeName = substr($arg, 7);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $address = $positional[1] ?? null;
        }

        $consul = function_exists('consul') ? consul() : $this->app->make(\NetFusion\Core\Consul\ConsulManager::class);

        switch ($action) {
            case 'join':
                if ($address === null) {
                    $this->error('Usage: php netfusion consul:members join <ip:port|ip> [--port=8301]');
                    return 1;
                }
                if (str_contains($address, ':')) {
                    [$ip, $p] = explode(':', $address, 2);
                    $address = $ip;
                    $port = (int)$p;
                }
                $node = $consul->join($address, $port, $nodeName);
                $this->info("Successfully joined gossip cluster node {$node->getName()} at {$node->getAddress()}:{$node->getPort()}");
                return 0;

            case 'list':
            default:
                $members = $consul->members();
                $this->info('Consul SWIM Gossip Cluster Members:');
                $this->line(sprintf('%-24s %-20s %-8s %-10s %-12s', 'NODE NAME', 'ADDRESS', 'PORT', 'STATUS', 'INCARNATION'));
                $this->line(str_repeat('-', 78));
                foreach ($members as $node) {
                    $this->line(sprintf(
                        '%-24s %-20s %-8d %-10s %-12d',
                        $node->getName(),
                        $node->getAddress(),
                        $node->getPort(),
                        $node->getStatus()->value,
                        $node->getIncarnation()
                    ));
                }
                return 0;
        }
    }
}
