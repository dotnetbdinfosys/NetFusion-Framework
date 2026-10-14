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

class CrdtInspectDocCommand extends Command
{
    protected string $name = 'crdt:inspect';
    protected string $description = 'Inspect CRDT document internal state, vector clock, and converged value';

    public function handle(): int
    {
        /** @var CrdtManager $manager */
        $manager = $this->app->has('crdt') ? $this->app->make('crdt') : new CrdtManager();

        $docId = (string)($this->argument(0) ?? 'demo_doc');
        $typeStr = (string)($this->argument(1) ?? 'rga_text');
        $type = CrdtType::tryFrom($typeStr) ?? CrdtType::RGA_TEXT;

        $doc = $manager->doc($docId, $type);
        $val = $doc->getValue();
        $state = json_encode($doc->getState(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->info("CRDT Document Inspection: [<comment>{$docId}</comment>]");
        $this->line("Type: <comment>{$type->value}</comment>");
        $this->line("Node ID: <comment>{$doc->getNodeId()}</comment>");
        $this->line("Converged Value: <info>" . (is_scalar($val) ? (string)$val : json_encode($val)) . "</info>");
        $this->line("Internal State Representation:\n{$state}");

        return 0;
    }
}
