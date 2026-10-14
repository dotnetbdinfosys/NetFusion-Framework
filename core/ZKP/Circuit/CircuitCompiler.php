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
 * Circuit Compiler synthesizing reusable arithmetic gadget primitives:
 * - Equal: a == b
 * - Multiplier: a * b == c
 * - NonZero (Invertibility): x * inv == 1
 * - Boolean check: b * (1 - b) == 0
 * - Range check / Bit decomposition: x == sum(b_i * 2^i)
 */
class CircuitCompiler
{
    /**
     * Builds a circuit proving knowledge of secret pre-image x such that x^3 + x + 5 == out
     */
    public static function cubicPolynomial(string $name = 'cubic_equation', ?FiniteField $field = null): R1csCircuit
    {
        $circuit = new R1csCircuit($name, $field);

        $circuit->privateVariable('x');
        $circuit->publicVariable('out');

        // Intermediate wires
        $circuit->privateVariable('sym_1'); // x * x
        $circuit->privateVariable('sym_2'); // sym_1 * x = x^3

        // 1. x * x = sym_1
        $a1 = $circuit->linearCombination()->addTerm('x', 1);
        $b1 = $circuit->linearCombination()->addTerm('x', 1);
        $c1 = $circuit->linearCombination()->addTerm('sym_1', 1);
        $circuit->addConstraint($a1, $b1, $c1);

        // 2. sym_1 * x = sym_2
        $a2 = $circuit->linearCombination()->addTerm('sym_1', 1);
        $b2 = $circuit->linearCombination()->addTerm('x', 1);
        $c2 = $circuit->linearCombination()->addTerm('sym_2', 1);
        $circuit->addConstraint($a2, $b2, $c2);

        // 3. (sym_2 + x + 5) * 1 = out
        $a3 = $circuit->linearCombination()
            ->addTerm('sym_2', 1)
            ->addTerm('x', 1)
            ->addTerm(Variable::ONE, 5);
        $b3 = $circuit->linearCombination()->addTerm(Variable::ONE, 1);
        $c3 = $circuit->linearCombination()->addTerm('out', 1);
        $circuit->addConstraint($a3, $b3, $c3);

        return $circuit;
    }

    /**
     * Builds a circuit proving knowledge of two secret factors a and b whose product is public n: a * b == n
     */
    public static function factorMultiplier(string $name = 'factor_multiplier', ?FiniteField $field = null): R1csCircuit
    {
        $circuit = new R1csCircuit($name, $field);

        $circuit->privateVariable('a');
        $circuit->privateVariable('b');
        $circuit->publicVariable('n');

        // Constraint: a * b = n
        $a = $circuit->linearCombination()->addTerm('a', 1);
        $b = $circuit->linearCombination()->addTerm('b', 1);
        $c = $circuit->linearCombination()->addTerm('n', 1);
        $circuit->addConstraint($a, $b, $c);

        return $circuit;
    }

    /**
     * Builds an 8-bit Range Proof gadget: proves x in [0, 255] by decomposing into 8 boolean bits.
     */
    public static function rangeProof8Bit(string $name = 'range_8bit', ?FiniteField $field = null): R1csCircuit
    {
        $circuit = new R1csCircuit($name, $field);
        $circuit->publicVariable('val');

        $sumLc = $circuit->linearCombination();

        for ($i = 0; $i < 8; $i++) {
            $bitName = "b_{$i}";
            $circuit->privateVariable($bitName);

            // Boolean constraint: b_i * (1 - b_i) = 0 => b_i * (ONE - b_i) = 0
            $a = $circuit->linearCombination()->addTerm($bitName, 1);
            $b = $circuit->linearCombination()
                ->addTerm(Variable::ONE, 1)
                ->addTerm($bitName, -1);
            $c = $circuit->linearCombination(); // Zero
            $circuit->addConstraint($a, $b, $c);

            // Accumulate sum(b_i * 2^i)
            $pow2 = 1 << $i;
            $sumLc->addTerm($bitName, $pow2);
        }

        // Final sum check: sum(b_i * 2^i) * 1 = val
        $oneLc = $circuit->linearCombination()->addTerm(Variable::ONE, 1);
        $valLc = $circuit->linearCombination()->addTerm('val', 1);
        $circuit->addConstraint($sumLc, $oneLc, $valLc);

        return $circuit;
    }
}
