<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/EventReplayCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Replays domain events from store to rebuild projections.
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

class EventReplayCommand extends Command
{
    protected string $name = 'event:replay';
    protected string $description = 'Replays domain events from event store to rebuild projections (Read Models).';

    public function handle(): int
    {
        /** @var EventSourcingManager $es */
        $es = $this->container->get(EventSourcingManager::class);

        $streamId = $this->argument('stream');
        $projection = $this->option('projection');

        $projections = [];
        if ($projection) {
            $projInstance = $es->getProjections()->get((string)$projection);
            if (!$projInstance) {
                $this->error("Projection [{$projection}] is not registered.");
                return 1;
            }
            $projections = [$projInstance];
        }

        $this->info('Starting event replay...');

        if ($streamId) {
            $this->info("Replaying stream [{$streamId}]...");
            $count = $es->getStore()->replayStream((string)$streamId, $projections);
        } else {
            $this->info("Replaying all event streams...");
            $count = $es->getStore()->replayAll($projections);
        }

        $this->info("Replay completed successfully. {$count} events projected.");
        return 0;
    }
}