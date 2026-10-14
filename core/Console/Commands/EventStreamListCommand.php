<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/EventStreamListCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Displays all recorded event streams.
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
use NetFusion\Core\EventSourcing\EventSourcingManager;

class EventStreamListCommand extends Command
{
    protected string $name = 'event:streams';
    protected string $description = 'List all event streams in the event store.';

    public function handle(): int
    {
        /** @var EventSourcingManager $es */
        $es = $this->container->get(EventSourcingManager::class);

        $aggregate = $this->option('aggregate');
        $streams = $es->getStore()->getStreams($aggregate ? (string)$aggregate : null);

        if (empty($streams)) {
            $this->info('No event streams found.');
            return 0;
        }

        $this->info(sprintf('%-36s | %-40s | %-7s | %-19s', 'Stream ID', 'Aggregate Type', 'Version', 'Updated At'));
        $this->info(str_repeat('-', 110));

        foreach ($streams as $s) {
            $this->info(sprintf(
                '%-36s | %-40s | %-7d | %-19s',
                $s['stream_id'],
                $s['aggregate_type'],
                (int)$s['current_version'],
                $s['updated_at'] ?? ''
            ));
        }

        return 0;
    }
}