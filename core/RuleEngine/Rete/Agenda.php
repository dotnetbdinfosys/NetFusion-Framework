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

use Countable;
use JsonSerializable;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;

class Agenda implements Countable, JsonSerializable
{
    /** @var array<string, Activation> */
    protected array $activations = [];
    /** @var array<string, bool> Track executed activations for refraction */
    protected array $firedHistory = [];
    protected int $totalFired = 0;

    /**
     * Add an activation to the agenda if not already fired (Refraction principle).
     */
    public function addActivation(Activation $activation): bool
    {
        $id = $activation->getId();

        if (isset($this->firedHistory[$id])) {
            return false;
        }

        $this->activations[$id] = $activation;
        return true;
    }

    /**
     * Remove activations that depend on a retracted fact.
     */
    public function removeActivationsForFact(FactInterface $fact): void
    {
        $factId = $fact->getId();
        foreach ($this->activations as $id => $activation) {
            foreach ($activation->getFacts() as $f) {
                if ($f->getId() === $factId) {
                    unset($this->activations[$id]);
                    break;
                }
            }
        }
    }

    /**
     * Remove all activations for a given rule name.
     */
    public function removeActivationsForRule(string $ruleName): void
    {
        foreach ($this->activations as $id => $activation) {
            if ($activation->getRule()->getName() === $ruleName) {
                unset($this->activations[$id]);
            }
        }
    }

    /**
     * Pick the next highest priority activation based on conflict resolution strategy:
     * 1. Salience (descending)
     * 2. Recency (descending)
     * 3. Specificity (descending)
     * 4. FIFO
     */
    public function nextActivation(): ?Activation
    {
        if (empty($this->activations)) {
            return null;
        }

        $candidates = array_values($this->activations);

        usort($candidates, function (Activation $a, Activation $b) {
            // 1. Salience comparison
            if ($a->getSalience() !== $b->getSalience()) {
                return $b->getSalience() <=> $a->getSalience();
            }

            // 2. Recency comparison
            if ($a->getRecency() !== $b->getRecency()) {
                return $b->getRecency() <=> $a->getRecency();
            }

            // 3. Specificity comparison
            if ($a->getSpecificity() !== $b->getSpecificity()) {
                return $b->getSpecificity() <=> $a->getSpecificity();
            }

            return 0;
        });

        $chosen = $candidates[0];
        unset($this->activations[$chosen->getId()]);
        return $chosen;
    }

    /**
     * Record an activation as fired.
     */
    public function recordFired(Activation $activation): void
    {
        $id = $activation->getId();
        $this->firedHistory[$id] = true;
        $this->totalFired++;
    }

    /**
     * Clear all pending activations and reset state.
     */
    public function clear(): void
    {
        $this->activations = [];
        $this->firedHistory = [];
        $this->totalFired = 0;
    }

    public function count(): int
    {
        return count($this->activations);
    }

    public function getTotalFiredCount(): int
    {
        return $this->totalFired;
    }

    /**
     * @return array<Activation>
     */
    public function getPendingActivations(): array
    {
        return array_values($this->activations);
    }

    public function jsonSerialize(): array
    {
        return [
            'pending_count' => count($this->activations),
            'total_fired' => $this->totalFired,
            'pending_rules' => array_map(fn(Activation $a) => $a->getRule()->getName(), array_values($this->activations)),
        ];
    }
}
