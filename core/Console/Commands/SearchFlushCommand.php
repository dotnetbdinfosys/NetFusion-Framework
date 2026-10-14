<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/SearchFlushCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to flush all records from a search index.
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

use NetFusion\Core\Console\Command;
use NetFusion\Core\Search\SearchManager;

/**
 * Class SearchFlushCommand
 *
 * Command: php netfusion search:flush <index>
 *
 * @package NetFusion\Core\Console\Commands
 */
class SearchFlushCommand extends Command
{
    protected string $name = 'search:flush';
    protected string $description = 'Flush all indexed documents from a specified search index';

    public function handle(): int
    {
        $index = $this->argument(0);

        if (empty($index)) {
            $this->error("Missing index argument. Usage: php netfusion search:flush <index>");
            return 1;
        }

        /** @var SearchManager $search */
        $search = $this->app->has('search') ? $this->app->make('search') : new SearchManager($this->app);

        $this->warn("Flushing search index [{$index}] on driver [{$search->getDefaultDriver()}]...");
        $search->flush($index);

        $this->info("✔ Search index [{$index}] has been completely flushed.");

        return 0;
    }
}
