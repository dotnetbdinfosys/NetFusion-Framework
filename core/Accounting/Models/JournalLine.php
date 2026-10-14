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
 * Class JournalLine
 *
 * A single debit or credit line within a JournalEntry.
 * One entry must have at least one debit line and one credit line,
 * and the total debits must equal total credits.
 */
final class JournalLine
{
    public function __construct(
        private readonly string $accountCode,
        private readonly string $side,       // 'debit' | 'credit'
        private readonly Money  $amount,
        private readonly string $description = ''
    ) {
        if (!in_array($side, ['debit', 'credit'], true)) {
            throw new \InvalidArgumentException("Side must be 'debit' or 'credit', got '{$side}'");
        }
        if ($amount->isNegative() || $amount->isZero()) {
            throw new \InvalidArgumentException('JournalLine amount must be a positive value');
        }
    }

    // ------------------------------------------------------------------
    // Factory helpers
    // ------------------------------------------------------------------

    public static function debit(string $accountCode, Money $amount, string $desc = ''): self
    {
        return new self($accountCode, 'debit', $amount, $desc);
    }

    public static function credit(string $accountCode, Money $amount, string $desc = ''): self
    {
        return new self($accountCode, 'credit', $amount, $desc);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getAccountCode(): string { return $this->accountCode; }
    public function getSide(): string        { return $this->side; }
    public function getAmount(): Money       { return $this->amount; }
    public function getDescription(): string { return $this->description; }

    public function isDebit(): bool  { return $this->side === 'debit'; }
    public function isCredit(): bool { return $this->side === 'credit'; }

    public function toArray(): array
    {
        return [
            'account_code' => $this->accountCode,
            'side'         => $this->side,
            'amount'       => $this->amount->toArray(),
            'description'  => $this->description,
        ];
    }
}
