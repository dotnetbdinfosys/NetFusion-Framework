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

namespace NetFusion\Core\Formal\Symbolic;

use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;

/**
 * Path Condition maintaining accumulated branch constraints along an execution trajectory.
 */
class PathCondition
{
    /** @var Term[] */
    private array $constraints = [];

    public function __construct(array $constraints = [])
    {
        $this->constraints = $constraints;
    }

    public function addConstraint(Term $term): self
    {
        $new = new self($this->constraints);
        $new->constraints[] = $term;
        return $new;
    }

    /**
     * @return Term[]
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function toExpression(): ?Term
    {
        if (empty($this->constraints)) {
            return null;
        }
        if (count($this->constraints) === 1) {
            return $this->constraints[0];
        }
        return Expression::and(...$this->constraints);
    }

    public function __toString(): string
    {
        $expr = $this->toExpression();
        return $expr !== null ? $expr->toSmtLib() : 'true';
    }
}
