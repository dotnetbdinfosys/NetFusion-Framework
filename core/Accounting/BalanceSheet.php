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
 * Class BalanceSheet
 *
 * Snapshot of financial position at a given date.
 *
 * Fundamental Accounting Equation:
 *   Assets = Liabilities + Owner's Equity
 *
 * Equity is calculated as:
 *   Beginning Equity + Net Income − Withdrawals
 */
class BalanceSheet implements ReportInterface
{
    private Money $totalAssets;
    private Money $totalLiabilities;
    private Money $totalEquity;

    /** @var array[] */
    private array $assetRows      = [];
    private array $liabilityRows  = [];
    private array $equityRows     = [];

    private bool $built = false;

    public function __construct(
        private readonly JournalLedger $ledger,
        private readonly string        $asOfDate,
        private readonly string        $title = 'Balance Sheet'
    ) {
        $currency = $ledger->getCurrency();
        $this->totalAssets      = Money::zero($currency);
        $this->totalLiabilities = Money::zero($currency);
        $this->totalEquity      = Money::zero($currency);
    }

    private function build(): void
    {
        if ($this->built) return;
        $this->built = true;
        $currency = $this->ledger->getCurrency();

        foreach ($this->ledger->getCoa()->all() as $account) {
            $balance = $this->ledger->getBalance($account->getCode());
            if ($balance->isZero()) continue;

            $row = ['code' => $account->getCode(), 'name' => $account->getName(), 'balance' => $balance];

            if ($account->isAsset()) {
                $this->assetRows[] = $row;
                $this->totalAssets = $this->totalAssets->add($balance->isNegative() ? Money::zero($currency) : $balance);
            } elseif ($account->isLiability()) {
                $this->liabilityRows[]  = $row;
                $this->totalLiabilities = $this->totalLiabilities->add($balance->isNegative() ? Money::zero($currency) : $balance);
            } elseif ($account->isEquity()) {
                $this->equityRows[] = $row;
                if ($balance->isNegative()) {
                    $this->totalEquity = $this->totalEquity->subtract($balance->negate());
                } else {
                    $this->totalEquity = $this->totalEquity->add($balance);
                }
            }
        }

        // Include current unclosed earnings (Revenue - Expenses) into Equity
        $unclosedRevenue = Money::zero($currency);
        $unclosedExpense = Money::zero($currency);
        foreach ($this->ledger->getCoa()->all() as $account) {
            $balance = $this->ledger->getBalance($account->getCode());
            if ($balance->isZero()) continue;
            if ($account->isIncome()) {
                $unclosedRevenue = $unclosedRevenue->add($balance);
            } elseif ($account->isExpense()) {
                $unclosedExpense = $unclosedExpense->add($balance);
            }
        }
        $netIncome = $unclosedRevenue->subtract($unclosedExpense);
        if (!$netIncome->isZero()) {
            $this->equityRows[] = [
                'code'    => 'NET-INC',
                'name'    => 'Current Period Earnings',
                'balance' => $netIncome,
            ];
            $this->totalEquity = $this->totalEquity->add($netIncome);
        }
    }

    // ------------------------------------------------------------------
    // ReportInterface
    // ------------------------------------------------------------------

    public function getTitle(): string  { return $this->title; }
    public function getPeriod(): string { return "As of {$this->asOfDate}"; }

    public function toArray(): array
    {
        $this->build();
        $currency = $this->ledger->getCurrency();
        $liabAndEquity = $this->totalLiabilities->add($this->totalEquity);

        return [
            'title'               => $this->getTitle(),
            'period'              => $this->getPeriod(),
            'assets'              => array_map(fn($r) => ['code' => $r['code'], 'name' => $r['name'], 'balance' => $r['balance']->getDisplayAmount()], $this->assetRows),
            'liabilities'         => array_map(fn($r) => ['code' => $r['code'], 'name' => $r['name'], 'balance' => $r['balance']->getDisplayAmount()], $this->liabilityRows),
            'equity'              => array_map(fn($r) => ['code' => $r['code'], 'name' => $r['name'], 'balance' => $r['balance']->getDisplayAmount()], $this->equityRows),
            'total_assets'        => $this->totalAssets->getDisplayAmount(),
            'total_liabilities'   => $this->totalLiabilities->getDisplayAmount(),
            'total_equity'        => $this->totalEquity->getDisplayAmount(),
            'liabilities_equity'  => $liabAndEquity->getDisplayAmount(),
            'balanced'            => $this->totalAssets->equals($liabAndEquity),
            'currency'            => $currency,
        ];
    }

    public function render(): string
    {
        $this->build();
        $currency = $this->ledger->getCurrency();
        $line     = str_repeat('-', 52);
        $out      = "\n{$this->getTitle()}\n{$this->getPeriod()}\n{$line}\n";

        $printSection = function (string $heading, array $rows, Money $total) use (&$out, $currency, $line): void {
            $out .= "\n{$heading}\n";
            foreach ($rows as $r) {
                $out .= sprintf("  %-30s  %s %s\n", $r['name'], $currency, $r['balance']->getDisplayAmount());
            }
            $out .= sprintf("  %-30s  %s %s\n", "Total {$heading}", $currency, $total->getDisplayAmount());
        };

        $printSection('ASSETS',      $this->assetRows,     $this->totalAssets);
        $printSection('LIABILITIES', $this->liabilityRows, $this->totalLiabilities);
        $printSection('EQUITY',      $this->equityRows,    $this->totalEquity);

        $out .= $line . "\n";
        $liabEquity = $this->totalLiabilities->add($this->totalEquity);
        $balanced   = $this->totalAssets->equals($liabEquity);
        $out .= sprintf("  %-30s  %s %s\n", 'TOTAL ASSETS', $currency, $this->totalAssets->getDisplayAmount());
        $out .= sprintf("  %-30s  %s %s\n", 'LIABILITIES + EQUITY', $currency, $liabEquity->getDisplayAmount());
        $out .= $line . "\n";
        $out .= $balanced ? "✓ Balanced (A = L + E)\n" : "✗ OUT OF BALANCE!\n";

        return $out;
    }

    // ------------------------------------------------------------------
    // Additional
    // ------------------------------------------------------------------

    public function getTotalAssets(): Money      { $this->build(); return $this->totalAssets; }
    public function getTotalLiabilities(): Money { $this->build(); return $this->totalLiabilities; }
    public function getTotalEquity(): Money      { $this->build(); return $this->totalEquity; }

    public function isBalanced(): bool
    {
        $this->build();
        return $this->totalAssets->equals($this->totalLiabilities->add($this->totalEquity));
    }
}
