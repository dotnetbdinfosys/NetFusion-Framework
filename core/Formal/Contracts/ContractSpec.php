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

namespace NetFusion\Core\Formal\Contracts;

use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;

/**
 * Formal Method Contract Specification (Preconditions, Postconditions, Invariants).
 * Design-by-Contract formal representation for Hoare logic verification.
 */
class ContractSpec
{
    private string $methodName;
    /** @var Term[] Preconditions P (requires) */
    private array $requires = [];
    /** @var Term[] Postconditions Q (ensures) */
    private array $ensures = [];
    /** @var Term[] Invariants I */
    private array $invariants = [];

    public function __construct(string $methodName)
    {
        $this->methodName = $methodName;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function requires(Term $condition): self
    {
        $this->requires[] = $condition;
        return $this;
    }

    public function ensures(Term $condition): self
    {
        $this->ensures[] = $condition;
        return $this;
    }

    public function invariant(Term $condition): self
    {
        $this->invariants[] = $condition;
        return $this;
    }

    /**
     * @return Term[]
     */
    public function getRequires(): array
    {
        return $this->requires;
    }

    /**
     * @return Term[]
     */
    public function getEnsures(): array
    {
        return $this->ensures;
    }

    /**
     * @return Term[]
     */
    public function getInvariants(): array
    {
        return $this->invariants;
    }

    public function getPreconditionExpression(): ?Term
    {
        if (empty($this->requires)) {
            return null;
        }
        return count($this->requires) === 1 ? $this->requires[0] : Expression::and(...$this->requires);
    }

    public function getPostconditionExpression(): ?Term
    {
        if (empty($this->ensures)) {
            return null;
        }
        return count($this->ensures) === 1 ? $this->ensures[0] : Expression::and(...$this->ensures);
    }
}
