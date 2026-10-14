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

namespace NetFusion\Core\ZKP\Circuit;

use NetFusion\Core\ZKP\Math\FiniteField;

/**
 * Rank-1 Constraint of the form: (A . s) * (B . s) = (C . s)
 */
class Constraint
{
    private LinearCombination $a;
    private LinearCombination $b;
    private LinearCombination $c;
    private FiniteField $field;

    public function __construct(
        LinearCombination $a,
        LinearCombination $b,
        LinearCombination $c,
        FiniteField $field
    ) {
        $this->a     = $a;
        $this->b     = $b;
        $this->c     = $c;
        $this->field = $field;
    }

    public function getA(): LinearCombination
    {
        return $this->a;
    }

    public function getB(): LinearCombination
    {
        return $this->b;
    }

    public function getC(): LinearCombination
    {
        return $this->c;
    }

    /**
     * Check if a given witness assignment satisfies this constraint: (A*s) * (B*s) == (C*s).
     */
    public function isSatisfied(array $witness): bool
    {
        $evalA = $this->a->evaluate($witness);
        $evalB = $this->b->evaluate($witness);
        $evalC = $this->c->evaluate($witness);

        return $evalA->mul($evalB)->equals($evalC);
    }
}
