<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Memory;

class AgenticMemoryFabric
{
    private ShortTermMemory $shortTerm;
    private WorkingMemory $working;
    private SemanticMemory $semantic;
    private array $config;

    public function __construct(
        ShortTermMemory|array|null $shortTermOrConfig = null,
        ?WorkingMemory $working = null,
        ?SemanticMemory $semantic = null
    ) {
        if (is_array($shortTermOrConfig)) {
            $this->config = array_merge([
                'window_size' => 20,
                'compact_observations' => true,
                'observation_char_limit' => 1500,
            ], $shortTermOrConfig);
            $this->shortTerm = new ShortTermMemory($this->config['window_size']);
            $this->working = $working ?? new WorkingMemory();
            $this->semantic = $semantic ?? new SemanticMemory();
        } else {
            $this->config = [
                'window_size' => 20,
                'compact_observations' => true,
                'observation_char_limit' => 1500,
            ];
            $this->shortTerm = $shortTermOrConfig ?? new ShortTermMemory();
            $this->working = $working ?? new WorkingMemory();
            $this->semantic = $semantic ?? new SemanticMemory();
        }
    }

    public function shortTerm(): ShortTermMemory
    {
        return $this->shortTerm;
    }

    public function working(): WorkingMemory
    {
        return $this->working;
    }

    public function semantic(): SemanticMemory
    {
        return $this->semantic;
    }

    public function getShortTermMemory(): ShortTermMemory
    {
        return $this->shortTerm;
    }

    public function getWorkingMemory(): WorkingMemory
    {
        return $this->working;
    }

    public function getSemanticMemory(): SemanticMemory
    {
        return $this->semantic;
    }

    public function clearAll(): void
    {
        $this->shortTerm->clear();
        $this->working->clear();
        $this->semantic->clear();
    }

    public function compactObservation(string $observation, ?int $limit = null): string
    {
        $maxLen = $limit ?? ($this->config['observation_char_limit'] ?? 1500);
        if (strlen($observation) > $maxLen) {
            return substr($observation, 0, $maxLen) . '... [truncated ' . (strlen($observation) - $maxLen) . ' chars]';
        }
        return $observation;
    }

    public function recordObservation(string $tool, string $observation): void
    {
        $compacted = $this->compactObservation($observation);
        $this->shortTerm->addToolMessage($compacted, uniqid('obs_'), $tool);
    }

    public function compileContextPrompt(string $query = ''): string
    {
        $formatted = $this->getFormattedContextForPrompt($query);
        return !empty($formatted) ? "[Agent Memory Context]\n" . $formatted : "";
    }

    public function addMessage(MemoryMessage $message): void
    {
        $this->shortTerm->add($message);
    }

    public function rememberFact(string $fact, array $metadata = []): string
    {
        return $this->semantic->store($fact, $metadata);
    }

    /**
     * @return array<int, array{id: string, content: string, metadata: array, score: float}>
     */
    public function recallContext(string $query, int $limit = 3): array
    {
        return $this->semantic->recall($query, $limit);
    }

    /**
     * Compacts short-term message history, archiving tool observations into semantic memory.
     */
    public function compact(int $keepLast = 5): void
    {
        $all = $this->shortTerm->getMessages();
        if (count($all) <= $keepLast) {
            return;
        }

        $systemMsg = null;
        if (!empty($all) && $all[0]->getRole() === 'system') {
            $systemMsg = array_shift($all);
        }

        $evictedCount = count($all) - $keepLast;
        for ($i = 0; $i < $evictedCount; $i++) {
            $evicted = array_shift($all);
            if ($evicted !== null && in_array($evicted->getRole(), ['tool', 'assistant'], true)) {
                $content = $evicted->getContent();
                if (strlen($content) > 10) {
                    $this->semantic->store($content, [
                        'source' => 'compacted_history',
                        'role' => $evicted->getRole(),
                    ]);
                }
            }
        }

        $this->shortTerm->clear();
        if ($systemMsg !== null) {
            $this->shortTerm->add($systemMsg);
        }
        foreach ($all as $remaining) {
            $this->shortTerm->add($remaining);
        }
    }

    /**
     * Render working state and relevant semantic facts as an injectable context string.
     */
    public function getFormattedContextForPrompt(string $query = ''): string
    {
        $parts = [];

        // Working memory scratchpad
        $scratch = $this->working->all();
        if (!empty($scratch)) {
            $parts[] = "### Working Scratchpad:\n" . json_encode($scratch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Working memory plan
        $plan = $this->working->getPlan();
        if (!empty($plan)) {
            $planLines = [];
            foreach ($plan as $idx => $item) {
                $statusMark = $item['status'] === 'completed' ? '[x]' : '[ ]';
                $planLines[] = "{$statusMark} Step " . ($idx + 1) . ": {$item['task']} ({$item['status']})";
            }
            $parts[] = "### Plan Checklist:\n" . implode("\n", $planLines);
        }

        // Semantic memory recall
        if ($query !== '') {
            $recalled = $this->recallContext($query, 3);
            if (!empty($recalled)) {
                $factLines = [];
                foreach ($recalled as $rec) {
                    $factLines[] = "- {$rec['content']} (relevance: {$rec['score']})";
                }
                $parts[] = "### Relevant Past Memory:\n" . implode("\n", $factLines);
            }
        }

        return implode("\n\n", $parts);
    }
}
