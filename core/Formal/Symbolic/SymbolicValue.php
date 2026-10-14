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

use NetFusion\Core\Formal\Ast\Constant;
use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Sort;
use NetFusion\Core\Formal\Ast\Term;
use NetFusion\Core\Formal\Ast\Variable;

/**
 * Symbolic value representing an algebraic formula or concrete literal in symbolic execution.
 */
class SymbolicValue
{
    private Term $term;

    public function __construct(Term $term)
    {
        $this->term = $term;
    }

    public static function ofVar(string $name, ?Sort $sort = null): self
    {
        return new self(new Variable($name, $sort ?? Sort::int()));
    }

    public static function ofInt(int $val): self
    {
        return new self(Constant::int($val));
    }

    public static function ofBool(bool $val): self
    {
        return new self(Constant::bool($val));
    }

    public function getTerm(): Term
    {
        return $this->term;
    }

    public function add(self|int $other): self
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return new self(Expression::add($this->term, $tOther));
    }

    public function sub(self|int $other): self
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return new self(Expression::sub($this->term, $tOther));
    }

    public function mul(self|int $other): self
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return new self(Expression::mul($this->term, $tOther));
    }

    public function div(self|int $other): self
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return new self(Expression::div($this->term, $tOther));
    }

    public function eq(self|int $other): Term
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return Expression::eq($this->term, $tOther);
    }

    public function lt(self|int $other): Term
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return Expression::lt($this->term, $tOther);
    }

    public function lte(self|int $other): Term
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return Expression::lte($this->term, $tOther);
    }

    public function gt(self|int $other): Term
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return Expression::gt($this->term, $tOther);
    }

    public function gte(self|int $other): Term
    {
        $tOther = $other instanceof self ? $other->getTerm() : Constant::int($other);
        return Expression::gte($this->term, $tOther);
    }

    public function __toString(): string
    {
        return $this->term->toSmtLib();
    }
}
