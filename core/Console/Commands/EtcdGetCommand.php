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
 * Class EtcdGetCommand
 *
 * CLI: php netfusion etcd:get <key> [--prefix] [--rev=N]
 */
class EtcdGetCommand extends Command
{
    protected string $name = 'etcd:get';
    protected string $description = 'Retrieve a key or range of keys from the etcd MVCC store';

    public function handle(array $args = []): int
    {
        $key = null;
        $isPrefix = false;
        $rev = 0;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if ($arg === '--prefix') {
                $isPrefix = true;
            } elseif (str_starts_with($arg, '--rev=')) {
                $rev = (int) substr($arg, 6);
            } elseif ($arg === '--rev' && isset($args[$i + 1])) {
                $rev = (int) $args[++$i];
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $key = $positional[0] ?? null;

        if ($key === null) {
            $this->error('Usage: php netfusion etcd:get <key> [--prefix] [--rev=N]');
            return 1;
        }

        $etcd = function_exists('etcd') ? etcd() : $this->app->make(\NetFusion\Core\Etcd\EtcdManager::class);

        if ($isPrefix) {
            $results = $etcd->range($key, $rev);
            if (empty($results)) {
                $this->line("No keys found matching prefix '{$key}'");
                return 0;
            }

            foreach ($results as $k => $revision) {
                $this->line("{$k} => {$revision->value} [createRev: {$revision->createRevision}, modRev: {$revision->modRevision}, version: {$revision->version}]");
            }
            return 0;
        }

        $result = $etcd->get($key, $rev);
        if ($result === null) {
            $this->line("Key '{$key}' not found");
            return 0;
        }

        $this->line("{$key} => {$result->value} [createRev: {$result->createRevision}, modRev: {$result->modRevision}, version: {$result->version}]");
        return 0;
    }
}
