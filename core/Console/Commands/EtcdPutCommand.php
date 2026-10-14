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
 * Class EtcdPutCommand
 *
 * CLI: php netfusion etcd:put <key> <value> [--lease=ID]
 */
class EtcdPutCommand extends Command
{
    protected string $name = 'etcd:put';
    protected string $description = 'Put a key-value pair into the etcd MVCC store';

    public function handle(array $args = []): int
    {
        $key = null;
        $value = null;
        $leaseId = 0;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (str_starts_with($arg, '--lease=')) {
                $leaseId = (int) substr($arg, 8);
            } elseif ($arg === '--lease' && isset($args[$i + 1])) {
                $leaseId = (int) $args[++$i];
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $key = $positional[0] ?? null;
        $value = $positional[1] ?? null;

        if ($key === null || $value === null) {
            $this->error('Usage: php netfusion etcd:put <key> <value> [--lease=ID]');
            return 1;
        }

        $etcd = function_exists('etcd') ? etcd() : $this->app->make(\NetFusion\Core\Etcd\EtcdManager::class);
        $rev = $etcd->put($key, $value, $leaseId);

        $this->info("OK (Revision: {$rev->modRevision}, Version: {$rev->version})");
        return 0;
    }
}
