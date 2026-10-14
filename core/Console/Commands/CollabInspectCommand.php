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

use NetFusion\Core\Collaboration\CollabManager;
use NetFusion\Core\Console\Command;

class CollabInspectCommand extends Command
{
    protected string $signature = 'collab:inspect {doc-id : Document ID to dump and inspect}';
    protected string $description = 'Display document content and recent OT operational history';

    public function handle(): int
    {
        /** @var CollabManager $collab */
        $collab = function_exists('app') && app()->has('collab') ? app('collab') : new CollabManager();

        $docId = (string)$this->argument('doc-id');
        $doc = $collab->document($docId);

        if (!$doc) {
            $this->error("Document with ID '{$docId}' not found.");
            return 1;
        }

        $this->info("=== Document Inspection: {$doc->title} ({$doc->id}) ===");
        $this->line("Revision  : " . $doc->revision);
        $this->line("Updated At: " . $doc->updatedAt);
        $this->line("Author ID : " . ($doc->authorId ?? 'N/A'));

        $this->info("\n--- Current Content ---");
        $this->line($doc->content);

        $ops = $collab->getStore()->getOperationsSince($docId, 0);
        $this->info("\n--- Operational History (" . count($ops) . " ops) ---");
        foreach ($ops as $opEntry) {
            $opArr = $opEntry['operation']->toArray();
            $this->line(" • Rev {$opEntry['revision']} by {$opEntry['user_id']} at {$opEntry['created_at']}: " . json_encode($opArr['ops']));
        }

        return 0;
    }
}
