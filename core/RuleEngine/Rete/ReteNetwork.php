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

namespace NetFusion\Core\RuleEngine\Rete;

use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\ReteNetworkInterface;
use NetFusion\Core\RuleEngine\Contracts\RuleInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;
use NetFusion\Core\RuleEngine\Exceptions\InfiniteLoopException;

class ReteNetwork implements ReteNetworkInterface
{
    /** @var array<string, TypeNode> Root type nodes indexed by fact type */
    protected array $typeNodes = [];
    /** @var array<string, RuleInterface> Registered rules indexed by name */
    protected array $rules = [];
    /** @var array<string, TerminalNode> Terminal nodes indexed by rule name */
    protected array $terminalNodes = [];

    protected Agenda $agenda;
    protected ?WorkingMemoryInterface $workingMemory = null;
    /** @var array<string, mixed> Global execution context */
    protected array $context = [];
    /** @var array<callable> Action execution listeners */
    protected array $listeners = [];

    public function __construct(?WorkingMemoryInterface $workingMemory = null, ?Agenda $agenda = null)
    {
        $this->agenda = $agenda ?? new Agenda();
        $this->workingMemory = $workingMemory ?? new WorkingMemory($this);
        if ($this->workingMemory instanceof WorkingMemory && $this->workingMemory->getNetwork() !== $this) {
            $this->workingMemory->setNetwork($this);
        }
    }

    public function getWorkingMemory(): WorkingMemoryInterface
    {
        return $this->workingMemory;
    }

    public function getAgenda(): Agenda
    {
        return $this->agenda;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function onRuleFired(callable $listener): self
    {
        $this->listeners[] = $listener;
        return $this;
    }

    public function getTypeNode(string $type): TypeNode
    {
        $cleanType = trim($type);
        if (!isset($this->typeNodes[$cleanType])) {
            $this->typeNodes[$cleanType] = new TypeNode($cleanType);
        }
        return $this->typeNodes[$cleanType];
    }

    /**
     * Compile a rule and insert into the RETE network graph.
     */
    public function addRule(RuleInterface $rule): void
    {
        $ruleName = $rule->getName();
        $this->rules[$ruleName] = $rule;

        $patterns = $rule->getPatterns();
        if (empty($patterns)) {
            return;
        }

        $terminalNode = new TerminalNode($rule, $this->agenda);
        $this->terminalNodes[$ruleName] = $terminalNode;

        if (count($patterns) === 1) {
            // Single pattern rule
            $p = $patterns[0];
            $typeNode = $this->getTypeNode($p['type']);

            if (!empty($p['filter'])) {
                $alphaNode = new AlphaNode($p['filter']);
                $typeNode->addChild($alphaNode);
                $alphaNode->addChild($terminalNode);
            } else {
                $typeNode->addChild($terminalNode);
            }

            // Catch-up: evaluate against any existing facts already in WorkingMemory
            foreach ($this->workingMemory->getFacts($p['type']) as $existingFact) {
                $typeNode->assertFact($existingFact);
            }

            return;
        }

        // Multi-pattern rule: compile Alpha nodes and chain with Beta join nodes
        $previousJoinSource = null;

        foreach ($patterns as $index => $p) {
            $typeNode = $this->getTypeNode($p['type']);

            // Create Alpha filter node if filter provided
            $alphaSource = $typeNode;
            if (!empty($p['filter'])) {
                $alphaNode = new AlphaNode($p['filter']);
                $typeNode->addChild($alphaNode);
                $alphaSource = $alphaNode;
            }

            if ($index === 0) {
                // First pattern is initial left source
                $previousJoinSource = $alphaSource;
            } else {
                // Subsequent patterns create a 2-input Beta join node
                $joinPredicate = $p['join'] ?? null;
                $betaNode = new BetaNode($joinPredicate);
                $betaNode->setRightAlphaSource($alphaSource);

                // Attach left source
                if ($previousJoinSource instanceof AlphaNode || $previousJoinSource instanceof TypeNode) {
                    $previousJoinSource->addChild($betaNode);
                } elseif ($previousJoinSource instanceof BetaNode) {
                    $previousJoinSource->addChild($betaNode);
                }

                // Attach right source
                $alphaSource->addChild($betaNode);

                $previousJoinSource = $betaNode;
            }
        }

        // Attach terminal node to the final Beta join node
        if ($previousJoinSource !== null) {
            $previousJoinSource->addChild($terminalNode);
        }

        // Catch-up: propagate existing facts in working memory
        foreach ($patterns as $p) {
            $typeNode = $this->getTypeNode($p['type']);
            foreach ($this->workingMemory->getFacts($p['type']) as $existingFact) {
                $typeNode->assertFact($existingFact);
            }
        }
    }

    public function removeRule(string $ruleName): bool
    {
        if (!isset($this->rules[$ruleName])) {
            return false;
        }

        unset($this->rules[$ruleName]);
        unset($this->terminalNodes[$ruleName]);
        $this->agenda->removeActivationsForRule($ruleName);

        return true;
    }

    public function propagateAssert(FactInterface $fact): void
    {
        $type = $fact->getType();
        if (isset($this->typeNodes[$type])) {
            $this->typeNodes[$type]->assertFact($fact);
        }
    }

    public function propagateRetract(FactInterface $fact): void
    {
        $type = $fact->getType();
        if (isset($this->typeNodes[$type])) {
            $this->typeNodes[$type]->retractFact($fact);
        }
        $this->agenda->removeActivationsForFact($fact);
    }

    public function propagateModify(FactInterface $oldFact, FactInterface $newFact): void
    {
        $this->propagateRetract($oldFact);
        $this->propagateAssert($newFact);
    }

    /**
     * Fire all queued rule activations according to conflict resolution strategy.
     */
    public function fireAllRules(int $maxFirings = 1000): int
    {
        $firings = 0;

        while (($activation = $this->agenda->nextActivation()) !== null) {
            if ($maxFirings > 0 && $firings >= $maxFirings) {
                throw new InfiniteLoopException(
                    sprintf("Rule engine detected potential infinite loop: exceeded limit of %d rule firings", $maxFirings)
                );
            }

            $result = $activation->fire($this->workingMemory, $this->context);
            $this->agenda->recordFired($activation);
            $firings++;

            // Trigger execution listeners
            foreach ($this->listeners as $listener) {
                $listener($activation, $result, $this->workingMemory);
            }
        }

        return $firings;
    }

    public function reset(): void
    {
        $this->agenda->clear();
        foreach ($this->typeNodes as $typeNode) {
            $typeNode->clear();
        }
    }

    /**
     * @return array<string, RuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
