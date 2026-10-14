<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Accounting/PrivacyAccountant.php
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

/**
 * Privacy Accountant implementing Basic and Advanced Composition Theorems.
 */
class PrivacyAccountant
{
    /** @var array<string, PrivacyBudget> */
    private array $budgets = [];

    public function getOrCreateBudget(string $identifier, float $totalEpsilon = 1.0, float $totalDelta = 1e-5): PrivacyBudget
    {
        if (!isset($this->budgets[$identifier])) {
            $this->budgets[$identifier] = new PrivacyBudget($identifier, $totalEpsilon, $totalDelta);
        }
        return $this->budgets[$identifier];
    }

    public function getBudget(string $identifier): ?PrivacyBudget
    {
        return $this->budgets[$identifier] ?? null;
    }

    /**
     * Compute Basic Composition of k mechanisms:
     * Total ε = sum(ε_i), Total δ = sum(δ_i)
     */
    public function basicComposition(array $queries): array
    {
        $totEps = 0.0;
        $totDel = 0.0;
        foreach ($queries as $q) {
            $totEps += (float)($q['epsilon'] ?? 0.0);
            $totDel += (float)($q['delta'] ?? 0.0);
        }
        return ['epsilon' => $totEps, 'delta' => $totDel];
    }

    /**
     * Compute Advanced Composition Theorem (Dwork et al.):
     * For k (ε', δ') mechanisms, privacy guarantee holds for:
     * ε_total = sqrt(2 * k * ln(1 / δ')) * ε' + k * ε' * (e^ε' - 1)
     * δ_total = k * δ' + δ''
     */
    public function advancedComposition(int $k, float $epsPrime, float $deltaPrime, float $deltaDoublePrime = 1e-5): array
    {
        if ($k <= 0 || $epsPrime <= 0.0) {
            return ['epsilon' => 0.0, 'delta' => 0.0];
        }

        $term1 = sqrt(2.0 * $k * log(1.0 / $deltaDoublePrime)) * $epsPrime;
        $term2 = $k * $epsPrime * (exp($epsPrime) - 1.0);
        $totalEps = $term1 + $term2;
        $totalDel = ($k * $deltaPrime) + $deltaDoublePrime;

        return [
            'epsilon' => $totalEps,
            'delta'   => $totalDel,
            'queries' => $k,
        ];
    }
}
