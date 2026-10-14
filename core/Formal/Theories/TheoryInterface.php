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

namespace NetFusion\Core\Formal\Theories;

use NetFusion\Core\Formal\Ast\Term;

/**
 * Interface for dedicated First-Order Logic Theory Solvers (T-Solvers).
 */
interface TheoryInterface
{
    public function getName(): string;

    /**
     * Assert a theory constraint or literal.
     */
    public function assertConstraint(Term $term): void;

    /**
     * Check consistency of asserted theory constraints.
     *
     * @return bool True if consistent, False if conflicting.
     */
    public function checkConsistency(): bool;

    /**
     * Explain conflict: returns subset of constraints causing inconsistency.
     *
     * @return Term[]
     */
    public function getConflictExplanation(): array;

    /**
     * Reset theory solver state.
     */
    public function reset(): void;
}
