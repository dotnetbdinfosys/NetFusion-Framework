<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * High-level manager and ACT-R / SOAR cognitive agent orchestrator facade.
 *
 * @package    NetFusion\Core\Cognitive
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive;

use NetFusion\Core\Cognitive\Enums\BufferType;
use NetFusion\Core\Cognitive\Memory\Chunk;
use NetFusion\Core\Cognitive\Memory\DeclarativeMemory;
use NetFusion\Core\Cognitive\Memory\EpisodicMemory;
use NetFusion\Core\Cognitive\Production\ProductionRule;
use NetFusion\Core\Cognitive\Production\RuleEngine;

class CognitiveManager
{
    private DeclarativeMemory $declarativeMemory;
    private EpisodicMemory $episodicMemory;
    private RuleEngine $ruleEngine;

    /**
     * @var array<string, array<string, mixed>> Active working buffers
     */
    private array $buffers = [
        'goal'      => [],
        'retrieval' => [],
        'context'   => [],
        'action'    => [],
    ];

    public function __construct(
        float $retrievalThreshold = -10.0,
        float $activationNoise = 0.0
    ) {
        $this->declarativeMemory = new DeclarativeMemory($retrievalThreshold, $activationNoise);
        $this->episodicMemory = new EpisodicMemory();
        $this->ruleEngine = new RuleEngine();
    }

    public function declarative(): DeclarativeMemory
    {
        return $this->declarativeMemory;
    }

    public function episodic(): EpisodicMemory
    {
        return $this->episodicMemory;
    }

    public function rules(): RuleEngine
    {
        return $this->ruleEngine;
    }

    /**
     * Set slot value in a working buffer.
     */
    public function setBufferSlot(string $buffer, string $key, mixed $value): void
    {
        $this->buffers[$buffer][$key] = $value;
    }

    public function getBuffer(string $buffer): array
    {
        return $this->buffers[$buffer] ?? [];
    }

    public function getBufferSlot(string $buffer, string $key, mixed $default = null): mixed
    {
        return $this->buffers[$buffer][$key] ?? $default;
    }

    public function clearBuffer(string $buffer): void
    {
        $this->buffers[$buffer] = [];
    }

    /**
     * Store a fact / chunk in declarative memory.
     *
     * @param array<string, mixed> $slots
     */
    public function learnFact(string $id, string $type, array $slots): Chunk
    {
        $chunk = new Chunk($id, $type, $slots);
        $this->declarativeMemory->addChunk($chunk);
        return $chunk;
    }

    /**
     * Define a procedural production rule.
     *
     * @param array<string, array<string, mixed>> $conditions
     */
    public function defineRule(string $name, array $conditions, callable $action, float $utility = 0.0): ProductionRule
    {
        $rule = new ProductionRule($name, $conditions, $action, $utility);
        $this->ruleEngine->addRule($rule);
        return $rule;
    }

    /**
     * Execute N cognitive Recognize-Act cycles.
     *
     * @return array<int, array{cycle: int, fired_rule: string, utility: float, result: mixed}>
     */
    public function runCycles(int $maxCycles = 5): array
    {
        $history = [];

        for ($c = 1; $c <= $maxCycles; $c++) {
            try {
                $stepResult = $this->ruleEngine->step(
                    $this->buffers,
                    $this->declarativeMemory,
                    $this->episodicMemory
                );

                $history[] = array_merge(['cycle' => $c], $stepResult);
            } catch (\Throwable $e) {
                // Impasse reached, stop cycles
                $history[] = [
                    'cycle'      => $c,
                    'fired_rule' => 'NONE',
                    'utility'    => 0.0,
                    'result'     => 'Impasse: ' . $e->getMessage(),
                ];
                break;
            }
        }

        return $history;
    }
}
