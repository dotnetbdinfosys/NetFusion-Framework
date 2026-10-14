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

namespace NetFusion\Core\Formal\Ast;

use NetFusion\Core\Formal\Enums\TermKind;

/**
 * Constant literal AST node (Booleans, Integers, Reals, BitVectors).
 */
class Constant extends Term
{
    private mixed $value;

    public function __construct(mixed $value, Sort $sort)
    {
        parent::__construct(TermKind::CONSTANT, $sort);
        $this->value = $value;
    }

    public static function bool(bool $val): self
    {
        return new self($val, Sort::bool());
    }

    public static function int(int $val): self
    {
        return new self($val, Sort::int());
    }

    public static function real(float|string $val): self
    {
        return new self($val, Sort::real());
    }

    public static function bitVec(int $val, int $width): self
    {
        return new self($val, Sort::bitVec($width));
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function toSmtLib(): string
    {
        if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }
        if ($this->sort->getBitWidth() !== null) {
            return "(_ bv{$this->value} {$this->sort->getBitWidth()})";
        }
        return (string)$this->value;
    }

    public function getFreeVariables(): array
    {
        return [];
    }
}
