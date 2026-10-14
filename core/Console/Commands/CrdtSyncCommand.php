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
use NetFusion\Core\CRDT\CrdtManager;
use NetFusion\Core\CRDT\Enums\CrdtType;

class CrdtSyncCommand extends Command
{
    protected string $name = 'crdt:sync';
    protected string $description = 'Synchronize two simulated CRDT replicas and demonstrate state convergence';

    public function handle(): int
    {
        /** @var CrdtManager $manager */
        $manager = $this->app->has('crdt') ? $this->app->make('crdt') : new CrdtManager();

        $docId = (string)($this->argument(0) ?? 'demo_doc');
        $typeStr = (string)($this->argument(1) ?? 'rga_text');
        $type = CrdtType::tryFrom($typeStr) ?? CrdtType::RGA_TEXT;

        $this->info("Initializing CRDT Sync for document [<comment>{$docId}</comment>] of type [<comment>{$type->value}</comment>]...");

        // Simulate two independent peer nodes
        $managerA = new CrdtManager(['node_id' => 'node_alpha']);
        $managerB = new CrdtManager(['node_id' => 'node_beta']);

        $replicaA = $managerA->doc($docId, $type);
        $replicaB = $managerB->doc($docId, $type);

        if ($type === CrdtType::RGA_TEXT) {
            /** @var \NetFusion\Core\CRDT\Types\RgaText $replicaA */
            /** @var \NetFusion\Core\CRDT\Types\RgaText $replicaB */
            $replicaA->insert(0, "Hello ");
            $replicaB->insert(0, "World!");
        } elseif ($type === CrdtType::PN_COUNTER) {
            /** @var \NetFusion\Core\CRDT\Types\PnCounter $replicaA */
            /** @var \NetFusion\Core\CRDT\Types\PnCounter $replicaB */
            $replicaA->increment(10);
            $replicaB->decrement(3);
        } elseif ($type === CrdtType::OR_SET) {
            /** @var \NetFusion\Core\CRDT\Types\OrSet $replicaA */
            /** @var \NetFusion\Core\CRDT\Types\OrSet $replicaB */
            $replicaA->add('item_1');
            $replicaB->add('item_2');
        }

        $res = $manager->syncReplicas($replicaA, $replicaB);

        $this->info("Synchronization Complete! Converged Replicas:");
        $this->line("Replica A Value: <info>" . json_encode($res['replicaA_value']) . "</info>");
        $this->line("Replica B Value: <info>" . json_encode($res['replicaB_value']) . "</info>");

        return 0;
    }
}
