<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/CacheClearCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console command to flush application cache or specific tags.
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
 * NetFusion Cache Clear Command
 *
 * Flushes all cached items from the default or specified cache store, or by tags.
 * Usage:
 *   php netfusion cache:clear
 *   php netfusion cache:clear --store=database
 *   php netfusion cache:clear --tags=billing,plans
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Flush the application cache or tag group';

    public function handle(): int
    {
        /** @var CacheManager $cache */
        $cache = $this->app->make('cache');

        $storeName = (string) ($this->option('store') ?: $cache->getDefaultStore());
        $tagsOption = $this->option('tags');

        if (!empty($tagsOption)) {
            $tags = array_map('trim', explode(',', (string) $tagsOption));
            $cache->store($storeName)->tags($tags)->flush();
            $this->success("Cache tags [" . implode(', ', $tags) . "] flushed successfully on [{$storeName}] store.");
            return 0;
        }

        $cleared = $cache->store($storeName)->clear();

        if ($cleared) {
            $this->success("Application cache on [{$storeName}] store cleared successfully.");
            return 0;
        }

        $this->error("Failed to clear application cache on [{$storeName}] store.");
        return 1;
    }
}
