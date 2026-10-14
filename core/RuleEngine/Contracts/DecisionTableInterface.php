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

use JsonSerializable;

interface DecisionTableInterface extends JsonSerializable
{
    /**
     * Get unique decision table name.
     */
    public function getName(): string;

    /**
     * Get DMN Hit Policy (e.g., UNIQUE, FIRST, PRIORITY, ANY, RULE_ORDER, COLLECT_SUM, etc.).
     */
    public function getHitPolicy(): string;

    /**
     * Evaluate the decision table against a given input data context.
     *
     * @param array<string, mixed> $context Input variables
     * @return array<string, mixed>|mixed Evaluated decision output result
     */
    public function evaluate(array $context): mixed;
}
