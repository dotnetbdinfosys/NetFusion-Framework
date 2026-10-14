<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SwarmAuctionCommand.php
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
use NetFusion\Core\AI\Swarm\SwarmManager;
use NetFusion\Core\AI\Swarm\Auction\TaskProposal;

class SwarmAuctionCommand extends Command
{
    protected string $signature = 'swarm:auction {task? : Task ID} {--colony=colony_alpha : Colony ID} {--effort=2.5 : Estimated computational effort}';
    protected string $description = 'Conduct a decentralized task auction via Contract Net Protocol';

    public function handle(): int
    {
        $taskId   = (string)($this->argument(0) ?: 'task_auto_' . substr(md5((string)mt_rand()), 0, 6));
        $colonyId = (string)($this->option('colony') ?: 'colony_alpha');
        $effort   = (float)($this->option('effort') ?: 2.5);

        $mgr = $this->app->has('swarm')
            ? $this->app->make('swarm')
            : SwarmManager::getInstance();

        $proposal = new TaskProposal($taskId, 'distributed_batch_job', estimatedEffort: $effort);
        $res = $mgr->auction($colonyId, $proposal);

        $this->info("=== Contract Net Protocol Task Auction Completed ===");
        $this->line("Task Identifier : " . $res['task_id']);
        $this->line("Auction State   : " . $res['state']->value);
        $this->line("Bids Received   : " . $res['total_bids']);
        $this->line("Awarded Agent   : " . ($res['awarded_agent'] ?? 'NONE'));
        $this->line("Winning Score   : " . $res['winning_score']);

        return 0;
    }
}
