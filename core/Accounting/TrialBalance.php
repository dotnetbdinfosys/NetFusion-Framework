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
 * Class TrialBalance
 *
 * Generates a Trial Balance report — a two-column summary of all
 * account debit/credit totals that proves the ledger is balanced.
 *
 * If Σ debits ≠ Σ credits, the books contain an error.
 */
class TrialBalance implements ReportInterface
{
    private array $rows = [];
    private Money $totalDebit;
    private Money $totalCredit;
    private bool  $built = false;

    public function __construct(
        private readonly JournalLedger $ledger,
        private readonly string        $asOfDate,
        private readonly string        $title    = 'Trial Balance'
    ) {
        $this->totalDebit  = Money::zero($ledger->getCurrency());
        $this->totalCredit = Money::zero($ledger->getCurrency());
    }

    private function build(): void
    {
        if ($this->built) return;
        $this->built = true;

        $currency = $this->ledger->getCurrency();
        $this->rows = [];

        foreach ($this->ledger->getCoa()->all() as $account) {
            $totals = $this->ledger->getTAccountTotals($account->getCode());
            $debit  = $totals['debit'];
            $credit = $totals['credit'];

            // Skip accounts with no activity
            if ($debit->isZero() && $credit->isZero()) continue;

            $this->rows[] = [
                'code'    => $account->getCode(),
                'name'    => $account->getName(),
                'type'    => $account->getType(),
                'debit'   => $debit,
                'credit'  => $credit,
            ];
            $this->totalDebit  = $this->totalDebit->add($debit);
            $this->totalCredit = $this->totalCredit->add($credit);
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
        return [
            'title'        => $this->getTitle(),
            'period'       => $this->getPeriod(),
            'rows'         => array_map(fn($r) => [
                'code'   => $r['code'],
                'name'   => $r['name'],
                'type'   => $r['type'],
                'debit'  => $r['debit']->getDisplayAmount(),
                'credit' => $r['credit']->getDisplayAmount(),
            ], $this->rows),
            'total_debit'  => $this->totalDebit->getDisplayAmount(),
            'total_credit' => $this->totalCredit->getDisplayAmount(),
            'balanced'     => $this->isBalanced(),
        ];
    }

    public function render(): string
    {
        $this->build();
        $currency = $this->ledger->getCurrency();
        $line     = str_repeat('-', 72);
        $out      = "\n" . strtoupper($this->getTitle()) . "\n{$this->getPeriod()}\n{$line}\n";
        $out     .= sprintf("%-6s  %-28s  %14s  %14s\n", 'Code', 'Account', 'Debit', 'Credit');
        $out     .= $line . "\n";

        foreach ($this->rows as $r) {
            $out .= sprintf(
                "%-6s  %-28s  %14s  %14s\n",
                $r['code'],
                substr($r['name'], 0, 28),
                $r['debit']->isZero()  ? '' : $currency . ' ' . $r['debit']->getDisplayAmount(),
                $r['credit']->isZero() ? '' : $currency . ' ' . $r['credit']->getDisplayAmount()
            );
        }

        $out .= $line . "\n";
        $out .= sprintf(
            "%-6s  %-28s  %14s  %14s\n",
            '', 'TOTALS',
            $currency . ' ' . $this->totalDebit->getDisplayAmount(),
            $currency . ' ' . $this->totalCredit->getDisplayAmount()
        );
        $out .= $line . "\n";
        $out .= $this->isBalanced() ? "✓ Balanced\n" : "✗ UNBALANCED — check for errors!\n";

        return $out;
    }

    // ------------------------------------------------------------------
    // Additional
    // ------------------------------------------------------------------

    public function isBalanced(): bool
    {
        $this->build();
        return $this->totalDebit->equals($this->totalCredit);
    }

    public function getTotalDebits(): Money
    {
        $this->build();
        return $this->totalDebit;
    }

    public function getTotalCredits(): Money
    {
        $this->build();
        return $this->totalCredit;
    }
}
