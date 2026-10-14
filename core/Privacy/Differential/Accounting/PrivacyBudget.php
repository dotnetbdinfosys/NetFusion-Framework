<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Accounting/PrivacyBudget.php
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

namespace NetFusion\Core\Privacy\Differential\Accounting;

use NetFusion\Core\Privacy\Differential\Enums\BudgetStatus;
use NetFusion\Core\Privacy\Differential\Exceptions\BudgetExhaustedException;

/**
 * Privacy Budget representation for an entity (tenant, user, dataset).
 */
class PrivacyBudget
{
    private float $consumedEpsilon = 0.0;
    private float $consumedDelta   = 0.0;
    /** @var array<int, array{epsilon: float, delta: float, query: string, time: float}> */
    private array $history = [];

    public function __construct(
        private string $identifier,
        private float $totalEpsilon = 1.0,
        private float $totalDelta = 1e-5
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTotalEpsilon(): float
    {
        return $this->totalEpsilon;
    }

    public function getTotalDelta(): float
    {
        return $this->totalDelta;
    }

    public function getConsumedEpsilon(): float
    {
        return $this->consumedEpsilon;
    }

    public function getConsumedDelta(): float
    {
        return $this->consumedDelta;
    }

    public function getRemainingEpsilon(): float
    {
        return max(0.0, $this->totalEpsilon - $this->consumedEpsilon);
    }

    public function getRemainingDelta(): float
    {
        return max(0.0, $this->totalDelta - $this->consumedDelta);
    }

    public function getStatus(): BudgetStatus
    {
        if ($this->consumedEpsilon >= $this->totalEpsilon || $this->consumedDelta >= $this->totalDelta) {
            return BudgetStatus::EXHAUSTED;
        }

        if ($this->consumedEpsilon >= 0.8 * $this->totalEpsilon) {
            return BudgetStatus::WARNING;
        }

        return BudgetStatus::ACTIVE;
    }

    /**
     * Consume privacy budget.
     * Throws BudgetExhaustedException if request exceeds total remaining budget.
     */
    public function consume(float $epsilon, float $delta = 0.0, string $query = ''): void
    {
        if ($this->consumedEpsilon + $epsilon > $this->totalEpsilon + 1e-9) {
            throw new BudgetExhaustedException("Privacy budget epsilon exhausted for '{$this->identifier}'. Requested: {$epsilon}, Remaining: {$this->getRemainingEpsilon()}");
        }

        if ($this->consumedDelta + $delta > $this->totalDelta + 1e-9) {
            throw new BudgetExhaustedException("Privacy budget delta exhausted for '{$this->identifier}'. Requested: {$delta}, Remaining: {$this->getRemainingDelta()}");
        }

        $this->consumedEpsilon += $epsilon;
        $this->consumedDelta   += $delta;

        $this->history[] = [
            'epsilon' => $epsilon,
            'delta'   => $delta,
            'query'   => $query,
            'time'    => microtime(true),
        ];
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function reset(): void
    {
        $this->consumedEpsilon = 0.0;
        $this->consumedDelta   = 0.0;
        $this->history         = [];
    }
}
