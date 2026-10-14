<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Actors\ActorSystem;
use NetFusion\Core\Console\Command;

class ActorTreeCommand extends Command
{
    protected string $signature = 'actor:tree';
    protected string $description = 'Display hierarchy tree and mailbox statistics of all active actors';

    public function handle(): int
    {
        /** @var ActorSystem $system */
        $system = function_exists('app') && app()->has('actors') ? app('actors') : new ActorSystem();

        $actors = $system->allActors();

        $this->info("=========================================================");
        $this->info("             NetFusion Actor System Hierarchy            ");
        $this->info("=========================================================");
        $this->line("System Name : {$system->name}");
        $this->line("Total Actors: " . count($actors));
        $this->line("---------------------------------------------------------");

        if (empty($actors)) {
            $this->line("No active actors registered in system.");
            return 0;
        }

        foreach ($actors as $path => $ref) {
            $aliveLabel = $ref->isAlive() ? "\033[32mALIVE\033[0m" : "\033[31mSTOPPED\033[0m";
            $cell = $ref->getCell();
            $mailboxCount = $cell->getMailbox()->count();
            $childrenCount = count($cell->getChildren());

            $this->line(sprintf(
                " - \033[1;37m%-45s\033[0m [%s] Mailbox: %d | Children: %d",
                $path,
                $aliveLabel,
                $mailboxCount,
                $childrenCount
            ));
        }

        $this->info("=========================================================");
        return 0;
    }
}
