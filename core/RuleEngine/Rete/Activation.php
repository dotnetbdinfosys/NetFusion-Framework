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

use JsonSerializable;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\RuleInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;

class Activation implements JsonSerializable
{
    protected string $id;
    protected RuleInterface $rule;
    /** @var array<FactInterface> */
    protected array $facts;
    protected int $salience;
    protected float $recency;
    protected int $specificity;
    protected float $createdAt;
    protected bool $fired = false;

    /**
     * @param RuleInterface $rule
     * @param array<FactInterface> $facts
     */
    public function __construct(RuleInterface $rule, array $facts = [])
    {
        $this->rule = $rule;
        $this->facts = array_values($facts);
        $this->salience = $rule->getSalience();
        $this->createdAt = microtime(true);

        // Compute recency: latest timestamp among participating facts
        $maxTimestamp = $this->createdAt;
        foreach ($this->facts as $fact) {
            if (method_exists($fact, 'getTimestamp')) {
                $maxTimestamp = max($maxTimestamp, $fact->getTimestamp());
            }
        }
        $this->recency = $maxTimestamp;

        // Compute specificity: number of patterns/conditions in the rule
        $this->specificity = count($rule->getPatterns());

        // Deterministic ID based on rule name and fact IDs
        $factIds = array_map(fn(FactInterface $f) => $f->getId(), $this->facts);
        $this->id = $rule->getName() . '#' . implode(',', $factIds);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRule(): RuleInterface
    {
        return $this->rule;
    }

    /**
     * @return array<FactInterface>
     */
    public function getFacts(): array
    {
        return $this->facts;
    }

    public function getSalience(): int
    {
        return $this->salience;
    }

    public function getRecency(): float
    {
        return $this->recency;
    }

    public function getSpecificity(): int
    {
        return $this->specificity;
    }

    public function isFired(): bool
    {
        return $this->fired;
    }

    /**
     * Execute the rule action on working memory.
     */
    public function fire(WorkingMemoryInterface $workingMemory, array $context = []): mixed
    {
        if ($this->fired) {
            return null;
        }

        $action = $this->rule->getAction();
        if ($action === null) {
            $this->fired = true;
            return null;
        }

        $this->fired = true;

        // Invoke action with matched facts and working memory
        $args = array_merge($this->facts, [$workingMemory, $context]);
        return $action(...$args);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'rule' => $this->rule->getName(),
            'salience' => $this->salience,
            'recency' => $this->recency,
            'specificity' => $this->specificity,
            'fact_count' => count($this->facts),
            'fired' => $this->fired,
        ];
    }
}
