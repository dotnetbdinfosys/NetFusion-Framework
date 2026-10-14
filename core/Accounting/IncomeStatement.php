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

use NetFusion\Core\Accounting\Contracts\ReportInterface;
use NetFusion\Core\Accounting\Models\Money;

/**
 * Class IncomeStatement
 *
 * Profit & Loss Statement for a given period.
 *
 * Formula:
 *   Gross Profit = Revenue − Cost of Goods Sold
 *   Operating Income = Gross Profit − Operating Expenses
 *   Net Income = Operating Income + Other Income − Other Expenses
 */
class IncomeStatement implements ReportInterface
{
    private Money $totalRevenue;
    private Money $totalExpense;
    private array $revenueRows  = [];
    private array $expenseRows  = [];
    private bool  $built        = false;

    public function __construct(
        private readonly JournalLedger $ledger,
        private readonly string        $periodFrom,
        private readonly string        $periodTo,
        private readonly string        $title = 'Income Statement (P&L)'
    ) {
        $currency = $ledger->getCurrency();
        $this->totalRevenue = Money::zero($currency);
        $this->totalExpense = Money::zero($currency);
    }

    private function build(): void
    {
        if ($this->built) return;
        $this->built = true;
        $currency = $this->ledger->getCurrency();

        // Filter entries to the reporting period
        $entries = $this->ledger->getEntries($this->periodFrom, $this->periodTo);

        // Rebuild temporary mini-ledger for the period
        $revenueBalances = [];
        $expenseBalances = [];

        foreach ($entries as $entry) {
            foreach ($entry->getLines() as $line) {
                $code    = $line->getAccountCode();
                $account = $this->ledger->getCoa()->find($code);
                if ($account === null) continue;

                if ($account->isIncome()) {
                    $revenueBalances[$code] ??= ['name' => $account->getName(), 'balance' => Money::zero($currency)];
                    // Income has credit normal balance: credit increases, debit decreases
                    if ($line->isCredit()) {
                        $revenueBalances[$code]['balance'] = $revenueBalances[$code]['balance']->add($line->getAmount());
                    } else {
                        $revenueBalances[$code]['balance'] = $revenueBalances[$code]['balance']->subtract($line->getAmount());
                    }
                } elseif ($account->isExpense()) {
                    $expenseBalances[$code] ??= ['name' => $account->getName(), 'balance' => Money::zero($currency)];
                    // Expense has debit normal balance: debit increases, credit decreases
                    if ($line->isDebit()) {
                        $expenseBalances[$code]['balance'] = $expenseBalances[$code]['balance']->add($line->getAmount());
                    } else {
                        $expenseBalances[$code]['balance'] = $expenseBalances[$code]['balance']->subtract($line->getAmount());
                    }
                }
            }
        }

        $this->revenueRows = $revenueBalances;
        $this->expenseRows = $expenseBalances;

        foreach ($revenueBalances as $row) {
            $this->totalRevenue = $this->totalRevenue->add($row['balance']);
        }
        foreach ($expenseBalances as $row) {
            $this->totalExpense = $this->totalExpense->add($row['balance']);
        }
    }

    // ------------------------------------------------------------------
    // ReportInterface
    // ------------------------------------------------------------------

    public function getTitle(): string  { return $this->title; }
    public function getPeriod(): string { return "{$this->periodFrom} to {$this->periodTo}"; }

    public function toArray(): array
    {
        $this->build();
        $currency = $this->ledger->getCurrency();

        return [
            'title'         => $this->getTitle(),
            'period'        => $this->getPeriod(),
            'revenue'       => array_map(fn($r) => ['name' => $r['name'], 'amount' => $r['balance']->getDisplayAmount()], $this->revenueRows),
            'expenses'      => array_map(fn($r) => ['name' => $r['name'], 'amount' => $r['balance']->getDisplayAmount()], $this->expenseRows),
            'total_revenue' => $this->totalRevenue->getDisplayAmount(),
            'total_expense' => $this->totalExpense->getDisplayAmount(),
            'net_income'    => $this->getNetIncome()->getDisplayAmount(),
            'currency'      => $currency,
            'profitable'    => !$this->getNetIncome()->isNegative(),
        ];
    }

    public function render(): string
    {
        $this->build();
        $currency = $this->ledger->getCurrency();
        $line     = str_repeat('-', 52);
        $out      = "\n{$this->getTitle()}\nPeriod: {$this->getPeriod()}\n{$line}\n";

        $out .= "REVENUE\n";
        foreach ($this->revenueRows as $row) {
            $out .= sprintf("  %-30s  %s %s\n", $row['name'], $currency, $row['balance']->getDisplayAmount());
        }
        $out .= sprintf("  %-30s  %s %s\n", 'Total Revenue', $currency, $this->totalRevenue->getDisplayAmount());
        $out .= $line . "\n";

        $out .= "EXPENSES\n";
        foreach ($this->expenseRows as $row) {
            $out .= sprintf("  %-30s  %s %s\n", $row['name'], $currency, $row['balance']->getDisplayAmount());
        }
        $out .= sprintf("  %-30s  %s %s\n", 'Total Expenses', $currency, $this->totalExpense->getDisplayAmount());
        $out .= $line . "\n";

        $net = $this->getNetIncome();
        $label = $net->isNegative() ? 'NET LOSS' : 'NET INCOME';
        $out .= sprintf("  %-30s  %s %s\n", $label, $currency, $net->getDisplayAmount());

        return $out;
    }

    // ------------------------------------------------------------------
    // Additional
    // ------------------------------------------------------------------

    public function getTotalRevenue(): Money { $this->build(); return $this->totalRevenue; }
    public function getTotalExpense(): Money { $this->build(); return $this->totalExpense; }

    public function getNetIncome(): Money
    {
        $this->build();
        return $this->totalRevenue->subtract($this->totalExpense);
    }

    public function isProfitable(): bool
    {
        return !$this->getNetIncome()->isNegative();
    }
}
