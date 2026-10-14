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

class ActorDeadLettersCommand extends Command
{
    protected string $signature = 'actor:dead-letters {--clear : Clear dead letters queue}';
    protected string $description = 'Inspect undeliverable messages in the actor dead letters queue';

    public function handle(): int
    {
        /** @var ActorSystem $system */
        $system = function_exists('app') && app()->has('actors') ? app('actors') : new ActorSystem();

        $deadLetters = $system->getDeadLetters();

        if ($this->option('clear')) {
            $deadLetters->clear();
            $this->info("Dead letters queue cleared.");
            return 0;
        }

        $all = $deadLetters->all();

        $this->info("=========================================================");
        $this->info("             NetFusion Actor Dead Letters                ");
        $this->info("=========================================================");
        $this->line("Total Undelivered: " . count($all));
        $this->line("---------------------------------------------------------");

        if (empty($all)) {
            $this->line("No dead letters recorded.");
            return 0;
        }

        foreach ($all as $idx => $letter) {
            $senderPath = $letter->sender?->path() ?? 'None';
            $recipientPath = $letter->recipient?->path() ?? 'Unknown';
            $msgStr = is_scalar($letter->message) ? (string)$letter->message : json_encode($letter->message);

            $this->line(sprintf(
                "#%d | Reason: \033[33m%s\033[0m\n   Sender   : %s\n   Recipient: %s\n   Payload  : %s\n",
                $idx + 1,
                $letter->reason,
                $senderPath,
                $recipientPath,
                $msgStr
            ));
        }

        return 0;
    }
}
