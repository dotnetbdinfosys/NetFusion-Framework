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

namespace NetFusion\Core\ZKP\Math;

/**
 * An element belonging to finite field F_p.
 */
class FieldElement
{
    private BigInt $value;
    private FiniteField $field;

    public function __construct(int|string|BigInt|\GMP $value, FiniteField $field)
    {
        $this->field = $field;
        $big = $value instanceof BigInt ? $value : new BigInt($value);
        $this->value = $big->mod($field->getModulus());
    }

    public function getValue(): BigInt
    {
        return $this->value;
    }

    public function getField(): FiniteField
    {
        return $this->field;
    }

    public function add(FieldElement|int|string $b): self
    {
        $bVal = $b instanceof FieldElement ? $b->getValue() : new BigInt($b);
        return new self($this->value->add($bVal), $this->field);
    }

    public function sub(FieldElement|int|string $b): self
    {
        $bVal = $b instanceof FieldElement ? $b->getValue() : new BigInt($b);
        return new self($this->value->sub($bVal), $this->field);
    }

    public function mul(FieldElement|int|string $b): self
    {
        $bVal = $b instanceof FieldElement ? $b->getValue() : new BigInt($b);
        return new self($this->value->mul($bVal), $this->field);
    }

    public function pow(int|string|BigInt $exp): self
    {
        return new self($this->value->modPow($exp, $this->field->getModulus()), $this->field);
    }

    public function inverse(): self
    {
        return new self($this->value->modInverse($this->field->getModulus()), $this->field);
    }

    public function div(FieldElement|int|string $b): self
    {
        $bElem = $b instanceof FieldElement ? $b : new self($b, $this->field);
        return $this->mul($bElem->inverse());
    }

    public function equals(FieldElement|int|string $b): bool
    {
        $bVal = $b instanceof FieldElement ? $b->getValue() : (new BigInt($b))->mod($this->field->getModulus());
        return $this->value->equals($bVal);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function toString(int $base = 10): string
    {
        return $this->value->toString($base);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
