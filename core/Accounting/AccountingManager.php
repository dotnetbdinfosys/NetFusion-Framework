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

use NetFusion\Core\Accounting\Models\JournalEntry;
use NetFusion\Core\Accounting\Models\Money;

/**
 * Class AccountingManager
 *
 * Central facade for the Double-Entry Accounting Engine.
 *
 * Usage:
 *   $mgr = AccountingManager::create();
 *   $mgr->getCoa()->seedDefaults();
 *
 *   $entry = JournalEntry::create('2026-01-01', 'Initial investment')
 *       ->debit('1001', Money::of(50000))    // Cash
 *       ->credit('3001', Money::of(50000));  // Common Stock
 *
 *   $mgr->post($entry);
 *   echo $mgr->trialBalance('2026-12-31')->render();
 */
class AccountingManager
{
    private static ?self $instance = null;

    private ChartOfAccounts $coa;
    private JournalLedger   $ledger;
    private string          $currency;

    public function __construct(
        ?ChartOfAccounts $coa      = null,
        string           $currency = 'USD'
    ) {
        $this->currency = strtoupper($currency);
        $this->coa      = $coa ?? new ChartOfAccounts();
        $this->ledger   = new JournalLedger($this->coa, $this->currency);
    }

    // ------------------------------------------------------------------
    // Factory / singleton
    // ------------------------------------------------------------------

    public static function create(
        ?ChartOfAccounts $coa      = null,
        string           $currency = 'USD'
    ): self {
        return new self($coa, $currency);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // ------------------------------------------------------------------
    // Core operations
    // ------------------------------------------------------------------

    public function getCoa(): ChartOfAccounts { return $this->coa; }
    public function getLedger(): JournalLedger { return $this->ledger; }
    public function getCurrency(): string { return $this->currency; }

    /**
     * Post a journal entry, validating balance and account existence.
     */
    public function post(JournalEntry $entry): void
    {
        $this->ledger->post($entry);
    }

    /**
     * Get the balance of an account by code.
     */
    public function balance(string $accountCode): Money
    {
        return $this->ledger->getBalance($accountCode);
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    public function trialBalance(string $asOfDate): TrialBalance
    {
        return new TrialBalance($this->ledger, $asOfDate);
    }

    public function incomeStatement(string $from, string $to): IncomeStatement
    {
        return new IncomeStatement($this->ledger, $from, $to);
    }

    public function balanceSheet(string $asOfDate): BalanceSheet
    {
        return new BalanceSheet($this->ledger, $asOfDate);
    }

    /**
     * Run period-end closing entries.
     * Returns posted JournalEntry objects.
     *
     * @return JournalEntry[]
     */
    public function close(
        string $closingDate,
        string $incomeSummaryCode   = '3003',
        string $retainedEarningsCode = '3002'
    ): array {
        $closing = new ClosingEntries(
            $this->ledger,
            $closingDate,
            $incomeSummaryCode,
            $retainedEarningsCode
        );
        return $closing->close();
    }

    // ------------------------------------------------------------------
    // Statistics
    // ------------------------------------------------------------------

    public function getStats(): array
    {
        return [
            'total_accounts' => $this->coa->count(),
            'total_entries'  => $this->ledger->countEntries(),
            'currency'       => $this->currency,
        ];
    }
}
