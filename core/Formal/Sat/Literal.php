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

namespace NetFusion\Core\Formal\Sat;

/**
 * Propositional Boolean Literal (Variable or Negated Variable).
 * Represented as signed integers (DIMACS standard: +v or -v).
 */
class Literal
{
    private int $variableId;
    private bool $negated;

    public function __construct(int $variableId, bool $negated = false)
    {
        $this->variableId = abs($variableId);
        $this->negated    = $negated;
    }

    public static function pos(int $var): self
    {
        return new self($var, false);
    }

    public static function neg(int $var): self
    {
        return new self($var, true);
    }

    public function getVariableId(): int
    {
        return $this->variableId;
    }

    public function isNegated(): bool
    {
        return $this->negated;
    }

    public function negate(): self
    {
        return new self($this->variableId, !$this->negated);
    }

    public function toDimacs(): int
    {
        return $this->negated ? -$this->variableId : $this->variableId;
    }

    public function __toString(): string
    {
        return ($this->negated ? '¬x' : 'x') . $this->variableId;
    }
}
