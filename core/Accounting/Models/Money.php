<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Accounting\Models;

/**
 * Class Money
 *
 * Immutable monetary value object using BCMath for arbitrary precision.
 * All arithmetic is performed with a configurable decimal scale
 * (default 6 decimal places internally, displayed as 2).
 */
final class Money
{
    private const SCALE = 6;

    private readonly string $amount;
    private readonly string $currency;

    public function __construct(
        int|float|string $amount,
        string $currency = 'USD'
    ) {
        // Normalise to BCMath string
        $this->amount   = bcadd((string) $amount, '0', self::SCALE);
        $this->currency = strtoupper($currency);
    }

    // ------------------------------------------------------------------
    // Factory
    // ------------------------------------------------------------------

    public static function of(int|float|string $amount, string $currency = 'USD'): self
    {
        return new self((string) $amount, strtoupper($currency));
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self('0', $currency);
    }

    // ------------------------------------------------------------------
    // Arithmetic (all return new immutable instances)
    // ------------------------------------------------------------------

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self(bcadd($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self(bcsub($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function multiply(string $factor): self
    {
        return new self(bcmul($this->amount, $factor, self::SCALE), $this->currency);
    }

    public function negate(): self
    {
        return new self(bcmul($this->amount, '-1', self::SCALE), $this->currency);
    }

    // ------------------------------------------------------------------
    // Comparison
    // ------------------------------------------------------------------

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->amount, $other->amount, self::SCALE) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return bccomp($this->amount, $other->amount, self::SCALE) > 0;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getAmount(): string
    {
        return $this->amount;
    }

    /** Return amount rounded to 2 decimal places for display */
    public function getDisplayAmount(): string
    {
        return number_format((float) $this->amount, 2, '.', '');
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function __toString(): string
    {
        return "{$this->currency} {$this->getDisplayAmount()}";
    }

    public function toArray(): array
    {
        return [
            'amount'   => $this->getDisplayAmount(),
            'currency' => $this->currency,
        ];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
