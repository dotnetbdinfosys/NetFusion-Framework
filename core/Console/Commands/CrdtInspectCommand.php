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

use NetFusion\Core\Console\Command;
use NetFusion\Core\Data\Replication\ReplicationManager;

class CrdtInspectCommand extends Command
{
    protected string $name = 'replication:crdt';
    protected string $description = 'Inspect or manipulate distributed CRDT state (PNCounter, ORSet, LWWMap, MVRegister)';

    public function handle(): int
    {
        /** @var ReplicationManager $manager */
        $manager = $this->app->has('replication') ? $this->app->make('replication') : new ReplicationManager();

        $crdtName = $this->argument(0) ?? 'demo_counter';
        $crdt = $manager->crdt($crdtName);

        $action = $this->argument(1) ?? 'get';

        if ($action === 'inc' && method_exists($crdt, 'increment')) {
            $amount = (int)($this->argument(2) ?? 1);
            $crdt->increment($amount);
            $this->info("Incremented CRDT [{$crdtName}] by {$amount}.");
        } elseif ($action === 'dec' && method_exists($crdt, 'decrement')) {
            $amount = (int)($this->argument(2) ?? 1);
            $crdt->decrement($amount);
            $this->info("Decremented CRDT [{$crdtName}] by {$amount}.");
        }

        $val = $crdt->getValue();
        $state = json_encode($crdt->getState(), JSON_PRETTY_PRINT);

        $this->info("CRDT Entity: <comment>{$crdtName}</comment>");
        $this->line("Resolved Value: <info>" . (is_scalar($val) ? (string)$val : json_encode($val)) . "</info>");
        $this->line("Raw CRDT State:\n{$state}");

        return 0;
    }
}
