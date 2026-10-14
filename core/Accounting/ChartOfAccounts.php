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

namespace NetFusion\Core\Accounting;

use NetFusion\Core\Accounting\Contracts\AccountInterface;
use NetFusion\Core\Accounting\Exceptions\AccountNotFoundException;
use NetFusion\Core\Accounting\Models\Account;

/**
 * Class ChartOfAccounts
 *
 * Registry of all accounts organized into a hierarchical tree.
 * Accounts are indexed by their unique code (e.g., "1001").
 *
 * Standard numbering convention:
 *   1xxx → Assets
 *   2xxx → Liabilities
 *   3xxx → Equity
 *   4xxx → Income / Revenue
 *   5xxx → Expenses
 */
class ChartOfAccounts
{
    /** @var Account[] keyed by code */
    private array $accounts = [];

    // ------------------------------------------------------------------
    // Account management
    // ------------------------------------------------------------------

    public function add(Account $account): self
    {
        $this->accounts[$account->getCode()] = $account;
        return $this;
    }

    public function addMany(Account ...$accounts): self
    {
        foreach ($accounts as $account) {
            $this->add($account);
        }
        return $this;
    }

    public function remove(string $code): void
    {
        unset($this->accounts[$code]);
    }

    // ------------------------------------------------------------------
    // Querying
    // ------------------------------------------------------------------

    public function has(string $code): bool
    {
        return isset($this->accounts[$code]);
    }

    /**
     * @throws AccountNotFoundException
     */
    public function get(string $code): Account
    {
        if (!isset($this->accounts[$code])) {
            throw new AccountNotFoundException($code);
        }
        return $this->accounts[$code];
    }

    /**
     * Find account or return null.
     */
    public function find(string $code): ?Account
    {
        return $this->accounts[$code] ?? null;
    }

    /**
     * @return Account[] All accounts of the given type
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->accounts,
            fn(Account $a) => $a->getType() === $type && $a->isActive()
        ));
    }

    public function assets(): array     { return $this->ofType('asset'); }
    public function liabilities(): array{ return $this->ofType('liability'); }
    public function equity(): array     { return $this->ofType('equity'); }
    public function income(): array     { return $this->ofType('income'); }
    public function expenses(): array   { return $this->ofType('expense'); }

    /**
     * @return Account[] Immediate children of a parent account
     */
    public function children(string $parentCode): array
    {
        return array_values(array_filter(
            $this->accounts,
            fn(Account $a) => $a->getParentCode() === $parentCode
        ));
    }

    /** @return Account[] All root accounts (no parent) */
    public function roots(): array
    {
        return array_values(array_filter(
            $this->accounts,
            fn(Account $a) => $a->getParentCode() === null
        ));
    }

    public function count(): int { return count($this->accounts); }

    /** @return Account[] All accounts */
    public function all(): array { return array_values($this->accounts); }

    // ------------------------------------------------------------------
    // Seed standard accounts
    // ------------------------------------------------------------------

    /**
     * Load a minimal standard Chart of Accounts (US GAAP style).
     */
    public function seedDefaults(string $currency = 'USD'): self
    {
        return $this->addMany(
            // Assets (1xxx)
            Account::asset('1000', 'Assets',                    null),
            Account::asset('1001', 'Cash',                      '1000'),
            Account::asset('1002', 'Accounts Receivable',       '1000'),
            Account::asset('1003', 'Inventory',                 '1000'),
            Account::asset('1100', 'Fixed Assets',              '1000'),
            Account::asset('1101', 'Equipment',                 '1100'),
            Account::asset('1102', 'Accumulated Depreciation',  '1100'),
            // Liabilities (2xxx)
            Account::liability('2000', 'Liabilities',            null),
            Account::liability('2001', 'Accounts Payable',       '2000'),
            Account::liability('2002', 'Short-Term Loans',       '2000'),
            Account::liability('2003', 'Sales Tax Payable',      '2000'),
            Account::liability('2100', 'Long-Term Debt',         '2000'),
            // Equity (3xxx)
            Account::equity('3000', 'Owner Equity',              null),
            Account::equity('3001', 'Common Stock',              '3000'),
            Account::equity('3002', 'Retained Earnings',         '3000'),
            Account::equity('3003', 'Income Summary',            '3000'),
            // Income (4xxx)
            Account::income('4000', 'Revenue',                   null),
            Account::income('4001', 'Sales Revenue',             '4000'),
            Account::income('4002', 'Service Revenue',           '4000'),
            Account::income('4003', 'Interest Income',           '4000'),
            // Expenses (5xxx)
            Account::expense('5000', 'Expenses',                 null),
            Account::expense('5001', 'Cost of Goods Sold',       '5000'),
            Account::expense('5002', 'Salaries Expense',         '5000'),
            Account::expense('5003', 'Rent Expense',             '5000'),
            Account::expense('5004', 'Depreciation Expense',     '5000'),
            Account::expense('5005', 'Interest Expense',         '5000'),
        );
    }

    public function toArray(): array
    {
        return array_map(fn(Account $a) => $a->toArray(), $this->all());
    }
}
