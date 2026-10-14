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
 * Class ClosingEntries
 *
 * Period-end closing procedure (GAAP).
 *
 * Closing transfers all temporary account balances (income & expense)
 * to the Income Summary account, then transfers the net income/loss
 * to Retained Earnings, resetting temporary accounts to zero.
 *
 * Steps:
 *   1. Close revenue accounts → debit each revenue, credit Income Summary
 *   2. Close expense accounts → credit each expense, debit Income Summary
 *   3. Close Income Summary → transfer net to Retained Earnings
 */
class ClosingEntries
{
    /** Default Income Summary account code */
    private string $incomeSummaryCode  = '3003';
    /** Default Retained Earnings account code */
    private string $retainedEarningsCode = '3002';

    public function __construct(
        private readonly JournalLedger $ledger,
        private readonly string        $closingDate,
        string $incomeSummaryCode     = '3003',
        string $retainedEarningsCode  = '3002'
    ) {
        $this->incomeSummaryCode    = $incomeSummaryCode;
        $this->retainedEarningsCode = $retainedEarningsCode;
    }

    /**
     * Generate and post all three closing entries.
     * Returns the JournalEntry objects for audit / inspection.
     *
     * @return JournalEntry[]
     */
    public function close(): array
    {
        $currency = $this->ledger->getCurrency();
        $entries  = [];

        // ---- Step 1: Close revenue to Income Summary ----
        $revenueEntry = JournalEntry::create(
            $this->closingDate,
            'Closing entry — transfer revenue to Income Summary',
            'CLOSE-REV'
        );

        $totalRevenue = Money::zero($currency);
        foreach ($this->ledger->getCoa()->income() as $account) {
            $balance = $this->ledger->getBalance($account->getCode());
            if ($balance->isZero()) continue;
            // Debit income account (close it to zero)
            $revenueEntry->debit($account->getCode(), $balance);
            $totalRevenue = $totalRevenue->add($balance);
        }

        if (!$totalRevenue->isZero()) {
            $revenueEntry->credit($this->incomeSummaryCode, $totalRevenue);
            $this->ledger->post($revenueEntry);
            $entries[] = $revenueEntry;
        }

        // ---- Step 2: Close expenses to Income Summary ----
        $expenseEntry = JournalEntry::create(
            $this->closingDate,
            'Closing entry — transfer expenses to Income Summary',
            'CLOSE-EXP'
        );

        $totalExpense = Money::zero($currency);
        foreach ($this->ledger->getCoa()->expenses() as $account) {
            $balance = $this->ledger->getBalance($account->getCode());
            if ($balance->isZero()) continue;
            // Credit expense account (close it to zero)
            $expenseEntry->credit($account->getCode(), $balance);
            $totalExpense = $totalExpense->add($balance);
        }

        if (!$totalExpense->isZero()) {
            $expenseEntry->debit($this->incomeSummaryCode, $totalExpense);
            $this->ledger->post($expenseEntry);
            $entries[] = $expenseEntry;
        }

        // ---- Step 3: Close Income Summary to Retained Earnings ----
        $netIncome = $totalRevenue->subtract($totalExpense);

        if (!$netIncome->isZero()) {
            $summaryEntry = JournalEntry::create(
                $this->closingDate,
                'Closing entry — transfer Income Summary to Retained Earnings',
                'CLOSE-NET'
            );

            if ($netIncome->isPositive()) {
                // Net income: debit Income Summary, credit Retained Earnings
                $summaryEntry->debit($this->incomeSummaryCode, $netIncome);
                $summaryEntry->credit($this->retainedEarningsCode, $netIncome);
            } else {
                // Net loss: credit Income Summary, debit Retained Earnings
                $loss = $netIncome->negate();
                $summaryEntry->credit($this->incomeSummaryCode, $loss);
                $summaryEntry->debit($this->retainedEarningsCode, $loss);
            }

            $this->ledger->post($summaryEntry);
            $entries[] = $summaryEntry;
        }

        return $entries;
    }
}
