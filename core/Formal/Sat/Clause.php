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

namespace NetFusion\Core\Formal\Sat;

/**
 * Disjunctive Clause (l_1 ∨ l_2 ∨ ... ∨ l_k).
 */
class Clause
{
    /** @var Literal[] */
    private array $literals;
    private bool $learned;
    private int $activity;

    public function __construct(array $literals, bool $learned = false)
    {
        $this->literals = $literals;
        $this->learned  = $learned;
        $this->activity = 0;
    }

    /**
     * @return Literal[]
     */
    public function getLiterals(): array
    {
        return $this->literals;
    }

    public function size(): int
    {
        return count($this->literals);
    }

    public function isLearned(): bool
    {
        return $this->learned;
    }

    public function incrementActivity(): void
    {
        $this->activity++;
    }

    public function getActivity(): int
    {
        return $this->activity;
    }

    public function toDimacs(): array
    {
        return array_map(fn(Literal $l) => $l->toDimacs(), $this->literals);
    }

    public function __toString(): string
    {
        return '(' . implode(' ∨ ', array_map(fn($l) => (string)$l, $this->literals)) . ')';
    }
}
