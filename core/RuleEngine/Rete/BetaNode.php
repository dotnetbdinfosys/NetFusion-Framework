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

class BetaNode
{
    /** @var callable|null Join condition predicate: fn(FactInterface $rightFact, FactInterface ...$leftFacts): bool */
    protected $joinPredicate;
    /** @var array<string, array<FactInterface>> Left memory: list of fact tuples */
    protected array $leftMemory = [];
    /** @var array<string, FactInterface> Right memory: single facts */
    protected array $rightMemory = [];
    /** @var array<BetaNode|TerminalNode> */
    protected array $children = [];
    /** @var AlphaNode|TypeNode|null Source for right input */
    protected mixed $rightAlphaSource = null;
    protected string $id;

    public function __construct(?callable $joinPredicate = null, ?string $id = null)
    {
        $this->joinPredicate = $joinPredicate;
        $this->id = $id ?? 'beta_' . bin2hex(random_bytes(4));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setRightAlphaSource(mixed $source): self
    {
        $this->rightAlphaSource = $source;
        return $this;
    }

    public function getRightAlphaSource(): mixed
    {
        return $this->rightAlphaSource;
    }

    public function addChild(mixed $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Generate deterministic composite key for a tuple of facts.
     *
     * @param array<FactInterface> $tuple
     */
    protected function getTupleKey(array $tuple): string
    {
        return implode(',', array_map(fn(FactInterface $f) => $f->getId(), $tuple));
    }

    /**
     * Left input: a fact tuple arrives from parent BetaNode or initial AlphaNode.
     *
     * @param array<FactInterface> $tuple
     */
    public function assertLeftTuple(array $tuple): void
    {
        $key = $this->getTupleKey($tuple);
        $this->leftMemory[$key] = $tuple;

        // Match this left tuple against all existing facts in right memory
        foreach ($this->rightMemory as $rightFact) {
            $this->evaluateAndPropagate($tuple, $rightFact);
        }
    }

    /**
     * Right input: a single fact arrives from right Alpha/Type node.
     */
    public function assertRightFact(FactInterface $fact): void
    {
        $this->rightMemory[$fact->getId()] = $fact;

        // Match this right fact against all existing tuples in left memory
        foreach ($this->leftMemory as $leftTuple) {
            $this->evaluateAndPropagate($leftTuple, $fact);
        }
    }

    /**
     * Evaluate join condition and propagate composite tuple to child nodes.
     *
     * @param array<FactInterface> $leftTuple
     * @param FactInterface $rightFact
     */
    protected function evaluateAndPropagate(array $leftTuple, FactInterface $rightFact): void
    {
        if ($this->joinPredicate !== null) {
            $predicate = $this->joinPredicate;
            $matches = false;
            try {
                // Pass rightFact, followed by left tuple facts
                $args = array_merge([$rightFact], $leftTuple);
                $matches = (bool) $predicate(...$args);
            } catch (\Throwable) {
                $matches = false;
            }

            if (!$matches) {
                return;
            }
        }

        $compositeTuple = array_merge($leftTuple, [$rightFact]);

        foreach ($this->children as $child) {
            if ($child instanceof BetaNode) {
                $child->assertLeftTuple($compositeTuple);
            } elseif ($child instanceof TerminalNode) {
                $child->assertTuple($compositeTuple);
            }
        }
    }

    /**
     * Retract left tuple from left memory and propagate downstream.
     *
     * @param array<FactInterface> $tuple
     */
    public function retractLeftTuple(array $tuple): void
    {
        $key = $this->getTupleKey($tuple);
        if (!isset($this->leftMemory[$key])) {
            return;
        }

        unset($this->leftMemory[$key]);

        foreach ($this->rightMemory as $rightFact) {
            $compositeTuple = array_merge($tuple, [$rightFact]);
            foreach ($this->children as $child) {
                if ($child instanceof BetaNode) {
                    $child->retractLeftTuple($compositeTuple);
                } elseif ($child instanceof TerminalNode) {
                    $child->retractTuple($compositeTuple);
                }
            }
        }
    }

    /**
     * Retract right fact from right memory and propagate downstream.
     */
    public function retractRightFact(FactInterface $fact): void
    {
        $factId = $fact->getId();
        if (!isset($this->rightMemory[$factId])) {
            return;
        }

        unset($this->rightMemory[$factId]);

        foreach ($this->leftMemory as $leftTuple) {
            $compositeTuple = array_merge($leftTuple, [$fact]);
            foreach ($this->children as $child) {
                if ($child instanceof BetaNode) {
                    $child->retractLeftTuple($compositeTuple);
                } elseif ($child instanceof TerminalNode) {
                    $child->retractTuple($compositeTuple);
                }
            }
        }
    }

    public function clear(): void
    {
        $this->leftMemory = [];
        $this->rightMemory = [];
        foreach ($this->children as $child) {
            if (method_exists($child, 'clear')) {
                $child->clear();
            }
        }
    }
}
