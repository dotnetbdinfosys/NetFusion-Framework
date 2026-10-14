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
use NetFusion\Core\CRDT\OT\TextOp;

class CrdtResolveCommand extends Command
{
    protected string $name = 'crdt:resolve';
    protected string $description = 'Demonstrate Operational Transformation (OT) conflict resolution for concurrent text edits';

    public function handle(): int
    {
        /** @var CrdtManager $manager */
        $manager = $this->app->has('crdt') ? $this->app->make('crdt') : new CrdtManager();

        $docId = (string)($this->argument(0) ?? 'collab_doc');
        $initialText = "The quick brown fox jumps over the lazy dog";

        $this->info("Initializing OT Engine for document [<comment>{$docId}</comment>]...");
        $this->line("Base Revision 0 Content: <info>\"{$initialText}\"</info>");

        $server = $manager->ot($docId, $initialText);

        // Client 1 inserts " energetic" after "quick" (at position 9)
        $client1Op = TextOp::insert(9, " energetic");
        $res1 = $server->submitOperation($client1Op, 0);
        $this->info("Client 1 submitted Insert at revision 0 -> Server Revision {$res1['new_revision']}: \"{$server->getContent()}\"");

        // Client 2 (concurrently at rev 0) inserts " super" after "quick" (at position 9)
        $client2Op = TextOp::insert(9, " super");
        $res2 = $server->submitOperation($client2Op, 0);
        $this->info("Client 2 submitted Concurrent Insert at revision 0 -> Transformed to Server Revision {$res2['new_revision']}: \"{$server->getContent()}\"");

        $this->info("Final Linearized Document Content: <comment>\"{$server->getContent()}\"</comment>");
        return 0;
    }
}
