<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/CacheForgetCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console command to remove a specific key from the cache store.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Cache\CacheManager;
use NetFusion\Core\Console\Command;

/**
 * NetFusion Cache Forget Command
 *
 * Removes a specific key from the default or specified cache store.
 * Usage:
 *   php netfusion cache:forget my_cache_key
 *   php netfusion cache:forget my_cache_key --store=database
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class CacheForgetCommand extends Command
{
    protected string $name = 'cache:forget';
    protected string $description = 'Remove an item from the cache by key';

    public function handle(): int
    {
        $key = $this->argument(0);

        if (!$key) {
            $this->error("Cache key is required. Usage: php netfusion cache:forget <key>");
            return 1;
        }

        /** @var CacheManager $cache */
        $cache = $this->app->make('cache');

        $storeName = (string) ($this->option('store') ?: $cache->getDefaultStore());
        $deleted = $cache->store($storeName)->delete($key);

        if ($deleted) {
            $this->success("Key [{$key}] forgotten from [{$storeName}] cache store.");
            return 0;
        }

        $this->line("Key [{$key}] did not exist or could not be removed from [{$storeName}] cache store.");
        return 0;
    }
}
