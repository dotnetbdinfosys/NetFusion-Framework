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

namespace NetFusion\Core\RuleEngine;

use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\FeelEvaluatorInterface;
use NetFusion\Core\RuleEngine\Contracts\RuleInterface;
use NetFusion\Core\RuleEngine\Contracts\RuleStoreInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;
use NetFusion\Core\RuleEngine\DMN\DecisionResult;
use NetFusion\Core\RuleEngine\DMN\DecisionRule;
use NetFusion\Core\RuleEngine\DMN\DecisionTable;
use NetFusion\Core\RuleEngine\DSL\DecisionTableBuilder;
use NetFusion\Core\RuleEngine\DSL\RuleBuilder;
use NetFusion\Core\RuleEngine\Exceptions\DecisionNotFoundException;
use NetFusion\Core\RuleEngine\Fact\Fact;
use NetFusion\Core\RuleEngine\FEEL\FeelEvaluator;
use NetFusion\Core\RuleEngine\Rete\Agenda;
use NetFusion\Core\RuleEngine\Rete\ReteNetwork;
use NetFusion\Core\RuleEngine\Rete\WorkingMemory;
use NetFusion\Core\RuleEngine\Store\ArrayRuleStore;

class RuleEngineManager
{
    protected ReteNetwork $network;
    protected WorkingMemoryInterface $workingMemory;
    protected Agenda $agenda;
    protected FeelEvaluatorInterface $feelEvaluator;
    protected RuleStoreInterface $store;
    /** @var array<string, DecisionTable> In-memory registered decision tables */
    protected array $decisionTables = [];
    protected float $startTime;

    public function __construct(
        ?RuleStoreInterface $store = null,
        ?FeelEvaluatorInterface $feelEvaluator = null,
        ?WorkingMemoryInterface $workingMemory = null,
        ?Agenda $agenda = null
    ) {
        $this->store = $store ?? new ArrayRuleStore();
        $this->feelEvaluator = $feelEvaluator ?? new FeelEvaluator();
        $this->agenda = $agenda ?? new Agenda();
        $this->workingMemory = $workingMemory ?? new WorkingMemory();
        $this->network = new ReteNetwork($this->workingMemory, $this->agenda);
        $this->startTime = microtime(true);
    }

    public function getNetwork(): ReteNetwork
    {
        return $this->network;
    }

    public function getWorkingMemory(): WorkingMemoryInterface
    {
        return $this->workingMemory;
    }

    public function getAgenda(): Agenda
    {
        return $this->agenda;
    }

    public function getFeelEvaluator(): FeelEvaluatorInterface
    {
        return $this->feelEvaluator;
    }

    public function getStore(): RuleStoreInterface
    {
        return $this->store;
    }

    /**
     * Assert a fact into working memory and propagate down RETE network.
     *
     * @param FactInterface|string $factOrType
     * @param array<string, mixed> $attributes
     */
    public function assertFact(FactInterface|string $factOrType, array $attributes = []): FactInterface
    {
        $fact = is_string($factOrType) ? new Fact($factOrType, $attributes) : $factOrType;
        return $this->workingMemory->assertFact($fact);
    }

    /**
     * Retract a fact by instance or ID.
     */
    public function retractFact(FactInterface|string $factOrId): bool
    {
        return $this->workingMemory->retractFact($factOrId);
    }

    /**
     * Modify an existing fact in working memory.
     *
     * @param array<string, mixed> $newAttributes
     */
    public function modifyFact(FactInterface|string $factOrId, array $newAttributes): FactInterface
    {
        return $this->workingMemory->modifyFact($factOrId, $newAttributes);
    }

    /**
     * Get active facts from working memory.
     *
     * @return array<FactInterface>
     */
    public function getFacts(?string $type = null): array
    {
        return $this->workingMemory->getFacts($type);
    }

    /**
     * Register a rule in the RETE network.
     */
    public function addRule(RuleInterface $rule): self
    {
        $this->network->addRule($rule);
        $this->store->saveRule($rule->getName(), $rule->jsonSerialize());
        return $this;
    }

    /**
     * Start building a fluent rule.
     */
    public function rule(string $name): RuleBuilder
    {
        $builder = new RuleBuilder($name);
        // Automatically attach helper to register when built
        return $builder;
    }

    /**
     * Fire all matching rules in working memory.
     */
    public function fireRules(int $maxFirings = 1000): int
    {
        return $this->network->fireAllRules($maxFirings);
    }

    /**
     * Register a DMN decision table.
     */
    public function addDecisionTable(DecisionTable $table): self
    {
        $this->decisionTables[$table->getName()] = $table;
        $this->store->saveDecisionTable($table->getName(), $table->jsonSerialize());
        return $this;
    }

    /**
     * Start building a fluent decision table.
     */
    public function decisionTable(string $name): DecisionTableBuilder
    {
        return new DecisionTableBuilder($name);
    }

    /**
     * Evaluate a registered DMN Decision Table against an input context.
     *
     * @param string $tableName
     * @param array<string, mixed> $context
     */
    public function evaluateDecision(string $tableName, array $context = []): DecisionResult
    {
        $table = $this->decisionTables[$tableName] ?? null;

        if ($table === null) {
            // Check persistence store
            $data = $this->store->getDecisionTable($tableName);
            if ($data !== null) {
                $table = $this->hydrateDecisionTable($data);
                $this->decisionTables[$tableName] = $table;
            }
        }

        if ($table === null) {
            throw new DecisionNotFoundException("Decision table '{$tableName}' not found");
        }

        $result = $table->evaluate($context);

        // Audit log
        $this->store->logAudit([
            'decision_table' => $tableName,
            'facts' => $context,
            'result' => $result->getOutputs(),
            'execution_time_ms' => $result->getEvaluationTimeMs(),
        ]);

        return $result;
    }

    /**
     * Reconstitute DecisionTable instance from stored array schema.
     *
     * @param array<string, mixed> $data
     */
    protected function hydrateDecisionTable(array $data): DecisionTable
    {
        $builder = DecisionTableBuilder::name($data['name'])
            ->hitPolicy($data['hit_policy'] ?? 'FIRST');

        foreach ($data['inputs'] ?? [] as $in) {
            $builder->input($in['expression'], $in['label'] ?? null, $in['typeRef'] ?? 'string', $in['allowedValues'] ?? null);
        }

        foreach ($data['outputs'] ?? [] as $out) {
            $builder->output($out['name'], $out['label'] ?? null, $out['typeRef'] ?? 'string', $out['defaultValue'] ?? null, $out['priorityValues'] ?? null);
        }

        foreach ($data['rules'] ?? [] as $r) {
            $builder->rule($r['inputEntries'] ?? [], $r['outputEntries'] ?? [], $r['description'] ?? null);
        }

        return $builder->build();
    }

    public function clear(): void
    {
        $this->workingMemory->clear();
        $this->network->reset();
    }

    /**
     * Get system metrics and execution statistics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'uptime_seconds' => round(microtime(true) - $this->startTime, 3),
            'facts_count' => $this->workingMemory->count(),
            'rules_count' => count($this->network->getRules()),
            'decision_tables_count' => count($this->decisionTables),
            'pending_activations' => $this->agenda->count(),
            'total_rules_fired' => $this->agenda->getTotalFiredCount(),
        ];
    }
}
