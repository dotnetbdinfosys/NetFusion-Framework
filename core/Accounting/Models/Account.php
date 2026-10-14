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

use NetFusion\Core\Accounting\Contracts\AccountInterface;

/**
 * Class Account
 *
 * Represents a single entry in the Chart of Accounts.
 *
 * Account Types and their Normal Balance:
 *   asset     → debit  (increases with debit)
 *   expense   → debit
 *   liability → credit (increases with credit)
 *   equity    → credit
 *   income    → credit
 */
final class Account implements AccountInterface
{
    private const NORMAL_BALANCES = [
        'asset'     => 'debit',
        'expense'   => 'debit',
        'liability' => 'credit',
        'equity'    => 'credit',
        'income'    => 'credit',
    ];

    private const VALID_TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function __construct(
        private readonly string  $code,
        private readonly string  $name,
        private readonly string  $type,
        private readonly ?string $parentCode = null,
        private bool             $active = true,
        private string           $description = '',
        private string           $currency = 'USD'
    ) {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid account type '{$type}'. Must be one of: " . implode(', ', self::VALID_TYPES)
            );
        }
    }

    // ------------------------------------------------------------------
    // Factory
    // ------------------------------------------------------------------

    public static function asset(string $code, string $name, ?string $parent = null): self
    {
        return new self($code, $name, 'asset', $parent);
    }

    public static function liability(string $code, string $name, ?string $parent = null): self
    {
        return new self($code, $name, 'liability', $parent);
    }

    public static function equity(string $code, string $name, ?string $parent = null): self
    {
        return new self($code, $name, 'equity', $parent);
    }

    public static function income(string $code, string $name, ?string $parent = null): self
    {
        return new self($code, $name, 'income', $parent);
    }

    public static function expense(string $code, string $name, ?string $parent = null): self
    {
        return new self($code, $name, 'expense', $parent);
    }

    // ------------------------------------------------------------------
    // AccountInterface
    // ------------------------------------------------------------------

    public function getCode(): string   { return $this->code; }
    public function getName(): string   { return $this->name; }
    public function getType(): string   { return $this->type; }

    public function getNormalBalance(): string
    {
        return self::NORMAL_BALANCES[$this->type];
    }

    public function getParentCode(): ?string { return $this->parentCode; }
    public function isActive(): bool         { return $this->active; }

    // ------------------------------------------------------------------
    // Additional accessors
    // ------------------------------------------------------------------

    public function getCurrency(): string    { return $this->currency; }
    public function getDescription(): string { return $this->description; }

    public function deactivate(): void { $this->active = false; }
    public function activate(): void   { $this->active = true; }

    public function isAsset(): bool     { return $this->type === 'asset'; }
    public function isLiability(): bool { return $this->type === 'liability'; }
    public function isEquity(): bool    { return $this->type === 'equity'; }
    public function isIncome(): bool    { return $this->type === 'income'; }
    public function isExpense(): bool   { return $this->type === 'expense'; }

    public function toArray(): array
    {
        return [
            'code'        => $this->code,
            'name'        => $this->name,
            'type'        => $this->type,
            'normal_bal'  => $this->getNormalBalance(),
            'parent_code' => $this->parentCode,
            'active'      => $this->active,
            'currency'    => $this->currency,
            'description' => $this->description,
        ];
    }
}
