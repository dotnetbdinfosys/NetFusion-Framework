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

namespace NetFusion\Core\RuleEngine\Contracts;

interface FeelEvaluatorInterface
{
    /**
     * Evaluate a FEEL unary test (condition) against an input value.
     * E.g. unary test: '>= 100', '[18..65]', '"VIP", "GOLD"', '-' (dash/any)
     */
    public function evaluateUnaryTest(string $expression, mixed $inputValue, array $context = []): bool;

    /**
     * Evaluate a general FEEL expression returning computed value.
     * E.g. 'amount * 0.15', 'starts with(name, "Dr.")', 'if score > 80 then "A" else "B"'
     */
    public function evaluate(string $expression, array $context = []): mixed;
}
