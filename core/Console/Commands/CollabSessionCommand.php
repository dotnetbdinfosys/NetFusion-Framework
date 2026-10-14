<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Collaboration\CollaborationManager;

/**
 * Class CollabSessionCommand
 *
 * CLI: php netfusion collab:session <doc_id> [--peer=peer-1] [--text="content"]
 */
class CollabSessionCommand extends Command
{
    protected string $name = 'collab:session';
    protected string $description = 'Inspect or initialize a real-time collaborative document session';

    public function handle(array $args = []): int
    {
        $docId = null;
        $peerId = 'cli-peer';
        $initialText = '';

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--peer=')) {
                $peerId = substr($arg, 7);
            } elseif (str_starts_with($arg, '--text=')) {
                $initialText = substr($arg, 7);
            } elseif (!str_starts_with($arg, '--')) {
                if ($docId === null) {
                    $docId = $arg;
                }
            }
        }

        $docId = $docId ?? (string) $this->argument(0, 'doc-general');

        $manager = CollaborationManager::getInstance();
        $doc = $manager->getOrCreateDocument($docId, $peerId, $initialText);

        $this->line("<info>[NetFusion Collaboration Session]</info>");
        $this->line("Document ID     : {$doc->getDocId()}");
        $this->line("Local Peer ID   : {$doc->getLocalPeerId()}");
        $this->line("Current Revision: {$doc->getRevision()}");
        $this->line("Visible Length  : {$doc->length()} characters");
        $this->line("Active Presences: " . count($doc->getActivePresences()));
        $this->line(str_repeat('-', 60));
        $this->line("<comment>Document Content:</comment>");
        $this->line($doc->getText() !== '' ? $doc->getText() : '  (empty document)');
        $this->line(str_repeat('-', 60));

        return 0;
    }
}
