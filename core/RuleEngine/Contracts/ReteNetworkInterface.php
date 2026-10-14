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

interface ReteNetworkInterface
{
    /**
     * Add and compile a rule into the RETE pattern network.
     */
    public function addRule(RuleInterface $rule): void;

    /**
     * Remove a rule from the network.
     */
    public function removeRule(string $ruleName): bool;

    /**
     * Propagate a newly asserted fact down the RETE network.
     */
    public function propagateAssert(FactInterface $fact): void;

    /**
     * Propagate a retracted fact down the RETE network.
     */
    public function propagateRetract(FactInterface $fact): void;

    /**
     * Propagate a modified fact down the RETE network.
     */
    public function propagateModify(FactInterface $oldFact, FactInterface $newFact): void;

    /**
     * Fire all pending rule activations in the agenda until quiescent or max firings reached.
     *
     * @param int $maxFirings Maximum number of activations to execute (0 for unlimited)
     * @return int Number of activations fired
     */
    public function fireAllRules(int $maxFirings = 1000): int;

    /**
     * Clear working memory and agenda activations.
     */
    public function reset(): void;
}
