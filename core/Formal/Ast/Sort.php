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

use NetFusion\Core\Formal\Enums\SortType;

/**
 * First-Order Logic Sort / Type representation.
 */
class Sort
{
    private SortType $type;
    private ?int $bitWidth;
    private ?Sort $indexSort;
    private ?Sort $elementSort;
    private ?string $name;

    public function __construct(
        SortType $type,
        ?int $bitWidth = null,
        ?Sort $indexSort = null,
        ?Sort $elementSort = null,
        ?string $name = null
    ) {
        $this->type        = $type;
        $this->bitWidth    = $bitWidth;
        $this->indexSort   = $indexSort;
        $this->elementSort = $elementSort;
        $this->name        = $name ?? $type->value;
    }

    public static function bool(): self
    {
        return new self(SortType::BOOL);
    }

    public static function int(): self
    {
        return new self(SortType::INT);
    }

    public static function real(): self
    {
        return new self(SortType::REAL);
    }

    public static function bitVec(int $width): self
    {
        return new self(SortType::BITVEC, bitWidth: $width, name: "(_ BitVec {$width})");
    }

    public static function array(Sort $index, Sort $element): self
    {
        return new self(SortType::ARRAY, indexSort: $index, elementSort: $element, name: "(Array {$index} {$element})");
    }

    public static function uninterpreted(string $name): self
    {
        return new self(SortType::UNINTERPRETED, name: $name);
    }

    public function getType(): SortType
    {
        return $this->type;
    }

    public function getBitWidth(): ?int
    {
        return $this->bitWidth;
    }

    public function getName(): string
    {
        return $this->name ?? $this->type->value;
    }

    public function equals(Sort $other): bool
    {
        return $this->getName() === $other->getName();
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
