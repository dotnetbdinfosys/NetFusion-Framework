<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/Money.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency
| Purpose   : Martin Fowler Money Pattern — IEEE 754 ফ্লোট এরর মুক্ত আর্থিক হিসাব।
|             Amount সর্বদা integer minor units (পয়সা/সেন্ট) এ সংরক্ষিত হয়।
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

namespace NetFusion\Core\Currency;

use InvalidArgumentException;
use RuntimeException;

/**
 * Money — Immutable Financial Value Object
 *
 * ⚠️  ফিনান্সিয়াল নিয়ম: float কখনো ব্যবহার করা হয় না।
 *     সমস্ত amount integer minor units এ সংরক্ষিত।
 *     উদাহরণ: ৳১০০.৫০ = 10050 পয়সা (minor units)
 *              $1.00    = 100  cents (minor units)
 *              ¥150     = 150  yen   (JPY এ 0 decimal places)
 *
 * BCMath extension থাকলে ব্যবহার করা হয়; না থাকলে integer arithmetic।
 */
final class Money
{
    /**
     * কারেন্সির decimal_places ম্যাপ (ISO 4217)
     * @var array<string, int>
     */
    private static array $decimalPlaces = [
        'BDT' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2,
        'CAD' => 2, 'AUD' => 2, 'CHF' => 2, 'CNY' => 2,
        'INR' => 2, 'SAR' => 2, 'AED' => 2, 'SGD' => 2,
        'MYR' => 2, 'JPY' => 0, 'KWD' => 3, 'BHD' => 3,
    ];

    /**
     * @param int    $amount   Minor units (পয়সা/সেন্ট) — সর্বদা integer
     * @param string $currency ISO 4217 currency code (e.g. 'USD', 'BDT')
     */
    private function __construct(
        private readonly int    $amount,
        private readonly string $currency,
    ) {
        if (empty($currency) || strlen($currency) !== 3) {
            throw new InvalidArgumentException(
                "Invalid currency code: '{$currency}'. Must be 3-letter ISO 4217 code."
            );
        }
    }

    // =========================================================================
    //  Factory Methods
    // =========================================================================

    /**
     * Integer minor units থেকে Money তৈরি করুন।
     *
     * @param  int    $minorUnits পয়সা/সেন্ট (e.g. 10050 = ৳100.50)
     * @param  string $currency   ISO 4217 code
     */
    public static function ofMinor(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * Decimal string থেকে Money তৈরি করুন।
     * ফ্লোট precision loss এড়াতে string ব্যবহার করুন।
     *
     * @param  string $amount   BCMath-safe decimal string (e.g. '100.50')
     * @param  string $currency ISO 4217 code
     */
    public static function of(string $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        $dp = self::decimalPlacesFor($currency);
        $multiplier = (int) ('1' . str_repeat('0', $dp));

        // BCMath があれば使う; なければ string manipulation
        if (extension_loaded('bcmath')) {
            $scaled = bcmul($amount, (string) $multiplier, 0);
            $minorUnits = (int) $scaled;
        } else {
            // String-based rounding to avoid float precision issues
            // decimal point খুঁজি
            $parts = explode('.', $amount, 2);
            $intPart  = (int) $parts[0];
            $fracPart = isset($parts[1]) ? substr($parts[1] . str_repeat('0', $dp), 0, $dp) : str_repeat('0', $dp);
            $minorUnits = $intPart * $multiplier + (int) $fracPart * ($amount[0] === '-' ? -1 : 1);
        }

        return new self($minorUnits, $currency);
    }

    /**
     * Float থেকে Money — শুধুমাত্র UI input এর জন্য।
     * ⚠️ Internal calculation এ float ব্যবহার করবেন না।
     *
     * @param  float  $amount
     * @param  string $currency
     */
    public static function fromFloat(float $amount, string $currency): self
    {
        // float → string করে `of()` এ পাঠাই
        $currency = strtoupper($currency);
        $dp = self::decimalPlacesFor($currency);
        return self::of(number_format($amount, $dp, '.', ''), $currency);
    }

    /**
     * Zero amount Money তৈরি করুন।
     */
    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    // =========================================================================
    //  Arithmetic Operations (Immutable — নতুন instance return করে)
    // =========================================================================

    /**
     * দুটি Money যোগ করুন।
     *
     * @throws \RuntimeException কারেন্সি মিলে না হলে
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * অন্য Money বিয়োগ করুন।
     *
     * @throws \RuntimeException কারেন্সি মিলে না হলে
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * একটি multiplier দিয়ে গুণ করুন।
     * BCMath দিয়ে precision বজায় রাখা হয়।
     *
     * @param  int|float|string $multiplier
     */
    public function multiply(int|float|string $multiplier): self
    {
        if (extension_loaded('bcmath')) {
            $result = bcmul((string) $this->amount, (string) $multiplier, 0);
            return new self((int) $result, $this->currency);
        }

        // Integer arithmetic (integer multiplier এর জন্য সঠিক)
        if (is_int($multiplier)) {
            return new self($this->amount * $multiplier, $this->currency);
        }

        // float multiplier: round করে integer তে রাখি
        return new self((int) round($this->amount * (float) $multiplier), $this->currency);
    }

    /**
     * একটি divisor দিয়ে ভাগ করুন।
     * BCMath দিয়ে precision বজায় রাখা হয়।
     *
     * @param  int|float|string $divisor
     * @throws \InvalidArgumentException ভাগ শূন্য হলে
     */
    public function divide(int|float|string $divisor): self
    {
        if ((float) $divisor === 0.0) {
            throw new InvalidArgumentException('Cannot divide Money by zero.');
        }

        if (extension_loaded('bcmath')) {
            $result = bcdiv((string) $this->amount, (string) $divisor, 0);
            return new self((int) $result, $this->currency);
        }

        return new self((int) round($this->amount / (float) $divisor), $this->currency);
    }

    /**
     * Percentage ডিসকাউন্ট/ট্যাক্স apply করুন।
     *
     * @param  string $percentage BCMath-safe percentage string (e.g. '7.5' for 7.5%)
     */
    public function percentage(string $percentage): self
    {
        if (extension_loaded('bcmath')) {
            $result = bcdiv(bcmul((string) $this->amount, $percentage, 10), '100', 0);
            return new self((int) $result, $this->currency);
        }

        return new self((int) round($this->amount * ((float) $percentage / 100)), $this->currency);
    }

    /**
     * Absolute value (negative amount কে positive করুন)।
     */
    public function abs(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    /**
     * Amount কে নেগেটিভ করুন।
     */
    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    // =========================================================================
    //  Comparison
    // =========================================================================

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount >= $other->amount;
    }

    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function lessThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount <= $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    // =========================================================================
    //  Accessors
    // =========================================================================

    /**
     * Minor units (integer) হিসেবে amount পান।
     */
    public function getMinorAmount(): int
    {
        return $this->amount;
    }

    /**
     * Currency code পান।
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Decimal string হিসেবে amount পান (e.g. '100.50')।
     */
    public function getAmount(): string
    {
        $dp = self::decimalPlacesFor($this->currency);
        if ($dp === 0) {
            return (string) $this->amount;
        }

        $divisor = (int) ('1' . str_repeat('0', $dp));
        $negative = $this->amount < 0;
        $abs      = abs($this->amount);
        $major    = intdiv($abs, $divisor);
        $minor    = $abs % $divisor;

        $formatted = $major . '.' . str_pad((string) $minor, $dp, '0', STR_PAD_LEFT);
        return $negative ? '-' . $formatted : $formatted;
    }

    /**
     * Human-readable format (symbol + formatted amount)।
     *
     * @param  string|null $symbol Override symbol (default: currency code)
     */
    public function format(?string $symbol = null): string
    {
        $symbols = [
            'BDT' => '৳', 'USD' => '$', 'EUR' => '€', 'GBP' => '£',
            'JPY' => '¥', 'CNY' => '¥', 'INR' => '₹', 'CAD' => 'CA$',
            'AUD' => 'A$', 'CHF' => 'Fr', 'SAR' => '﷼', 'AED' => 'د.إ',
            'SGD' => 'S$', 'MYR' => 'RM',
        ];

        $sym = $symbol ?? ($symbols[$this->currency] ?? $this->currency . ' ');
        $dp  = self::decimalPlacesFor($this->currency);
        $amt = $this->getAmount();

        // হাজার separator যোগ করি
        $parts = explode('.', $amt, 2);
        $intFormatted = number_format((int) $parts[0]);
        $formatted = $dp > 0 ? ($intFormatted . '.' . ($parts[1] ?? str_repeat('0', $dp))) : $intFormatted;

        return $sym . $formatted;
    }

    /**
     * JSON serialize-এর জন্য array representation।
     *
     * @return array{amount: string, minor_amount: int, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount'       => $this->getAmount(),
            'minor_amount' => $this->amount,
            'currency'     => $this->currency,
            'formatted'    => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * একটি currency এর decimal places পান।
     */
    public static function decimalPlacesFor(string $currency): int
    {
        return self::$decimalPlaces[strtoupper($currency)] ?? 2;
    }

    /**
     * Custom decimal places রেজিস্টার করুন (এক্সটেনশন পয়েন্ট)।
     */
    public static function registerCurrency(string $code, int $decimalPlaces): void
    {
        self::$decimalPlaces[strtoupper($code)] = $decimalPlaces;
    }

    /**
     * Currency mismatch validation।
     *
     * @throws \RuntimeException
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new RuntimeException(
                "Currency mismatch: cannot operate on {$this->currency} and {$other->currency}. "
                . 'Convert currencies first using CurrencyManager::convert().'
            );
        }
    }
}
