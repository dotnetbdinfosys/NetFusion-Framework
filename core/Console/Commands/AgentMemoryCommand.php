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
use NetFusion\Core\AI\Agent\AgentManager;

/**
 * Class AgentMemoryCommand
 *
 * CLI: php netfusion agent:memory <inspect|remember|recall|clear> [key_or_query] [value]
 */
class AgentMemoryCommand extends Command
{
    protected string $name = 'agent:memory';
    protected string $description = 'Inspect, store, recall, or clear agent working and semantic memories';

    public function handle(array $args = []): int
    {
        $action = null;
        $keyOrQuery = null;
        $value = null;
        $agentName = 'NetFusionAgent';

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--agent=')) {
                $agentName = substr($arg, 8);
            } elseif (!str_starts_with($arg, '--')) {
                if ($action === null) {
                    $action = strtolower($arg);
                } elseif ($keyOrQuery === null) {
                    $keyOrQuery = $arg;
                } elseif ($value === null) {
                    $value = $arg;
                } else {
                    $value .= ' ' . $arg;
                }
            }
        }

        $action = $action ?? (string) $this->argument(0, 'inspect');
        $memory = agent_memory($agentName);

        switch ($action) {
            case 'remember':
                if (!$keyOrQuery) {
                    $this->error("Missing key/document to remember. Usage: agent:memory remember <key> <content>");
                    return 1;
                }
                $key = $keyOrQuery;
                $doc = $value ?? $keyOrQuery;
                $memory->getWorkingMemory()->set($key, $doc);
                $memory->getSemanticMemory()->remember($doc, ['key' => $key]);
                $this->line("<info>[NetFusion Memory]</info> Stored in working & semantic memory under key: '{$key}'");
                return 0;

            case 'recall':
                if (!$keyOrQuery) {
                    $this->error("Missing query to recall. Usage: agent:memory recall <query>");
                    return 1;
                }
                $recalled = $memory->getSemanticMemory()->recall($keyOrQuery, 5);
                $this->line("<info>[NetFusion Memory Recall for: '{$keyOrQuery}']</info>");
                if (empty($recalled)) {
                    $this->line("<comment>No relevant memories found.</comment>");
                    return 0;
                }
                foreach ($recalled as $idx => $item) {
                    $rank = $idx + 1;
                    $score = round($item['score'] * 100, 1);
                    $this->line(" #{$rank} [Relevance: {$score}%] Content: {$item['content']}");
                }
                return 0;

            case 'clear':
                $memory->clearAll();
                $this->line("<info>[NetFusion Memory]</info> Working, short-term, and semantic memories cleared.");
                return 0;

            case 'inspect':
            default:
                $this->line("<info>[NetFusion Agent Memory Inspection: {$agentName}]</info>");
                $this->line("Short-Term Messages : " . count($memory->getShortTermMemory()->getMessages()));
                $this->line("Working Memory Keys : " . count($memory->getWorkingMemory()->all()));
                $this->line("Semantic Documents  : " . $memory->getSemanticMemory()->count());
                $this->line("\n<comment>Working Memory Scratchpad:</comment>");
                $all = $memory->getWorkingMemory()->all();
                if (empty($all)) {
                    $this->line("  (empty)");
                } else {
                    foreach ($all as $k => $v) {
                        $this->line("  - {$k}: " . (is_string($v) ? $v : json_encode($v)));
                    }
                }
                return 0;
        }
    }
}
