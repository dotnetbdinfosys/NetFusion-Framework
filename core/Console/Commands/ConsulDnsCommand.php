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
 * CLI command to query the dynamic Consul DNS resolver.
 * Usage: php netfusion consul:dns <query> [--type=A|SRV|TXT|ANY]
 */
class ConsulDnsCommand extends Command
{
    protected string $name = 'consul:dns';
    protected string $description = 'Resolve service and node records via dynamic Consul DNS';

    public function handle(array $args = []): int
    {
        $query = null;
        $type = 'ANY';

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--type=')) {
                $type = strtoupper(substr($arg, 7));
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $query = $positional[0] ?? null;

        if ($query === null) {
            $this->error('Usage: php netfusion consul:dns <name.service.consul> [--type=A|SRV|TXT|ANY]');
            return 1;
        }

        $consul = function_exists('consul') ? consul() : $this->app->make(\NetFusion\Core\Consul\ConsulManager::class);
        $records = $consul->dnsLookup($query, $type);

        if (empty($records)) {
            $this->line(";; ->>HEADER<<- opcode: QUERY, status: NXDOMAIN, id: " . random_int(1000, 9999));
            $this->line(";; QUESTION SECTION:");
            $this->line(";{$query}.\t\t\tIN\t{$type}");
            $this->line("\n;; No answers found.");
            return 0;
        }

        $this->info(";; ->>HEADER<<- opcode: QUERY, status: NOERROR, answers: " . count($records));
        $this->line(";; QUESTION SECTION:");
        $this->line(";{$query}.\t\t\tIN\t{$type}");
        $this->line("\n;; ANSWER SECTION:");

        foreach ($records as $r) {
            $rType = $r['type']->name;
            $dataStr = is_array($r['data']) ? json_encode($r['data']) : (string)$r['data'];
            $this->line(sprintf("%-30s\t%d\tIN\t%-6s\t%s", $r['name'], $r['ttl'], $rType, $dataStr));
        }

        return 0;
    }
}
