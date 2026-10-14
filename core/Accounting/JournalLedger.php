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

use NetFusion\Core\Accounting\Contracts\LedgerInterface;
use NetFusion\Core\Accounting\Exceptions\AccountNotFoundException;
use NetFusion\Core\Accounting\Models\JournalEntry;
use NetFusion\Core\Accounting\Models\JournalLine;
use NetFusion\Core\Accounting\Models\Money;

/**
 * Class JournalLedger
 *
 * Combines the General Journal (chronological transaction log) with
 * the General Ledger (per-account T-account balance computation).
 *
 * Posting rules for account balance impact:
 *   Normal-balance == debit  → debit increases, credit decreases
 *   Normal-balance == credit → credit increases, debit decreases
 */
class JournalLedger implements LedgerInterface
{
    /** @var JournalEntry[] chronological journal */
    private array $journal = [];

    public function __construct(
        private readonly ChartOfAccounts $coa,
        private readonly string $currency = 'USD'
    ) {}

    // ------------------------------------------------------------------
    // LedgerInterface
    // ------------------------------------------------------------------

    public function post(JournalEntry $entry): void
    {
        // Validate account codes exist in CoA
        foreach ($entry->getLines() as $line) {
            if (!$this->coa->has($line->getAccountCode())) {
                throw new AccountNotFoundException($line->getAccountCode());
            }
        }

        $entry->post();           // validates balance, stamps postedAt
        $this->journal[] = $entry;
    }

    public function getEntries(?string $from = null, ?string $to = null): array
    {
        if ($from === null && $to === null) {
            return $this->journal;
        }
        return array_values(array_filter(
            $this->journal,
            function (JournalEntry $e) use ($from, $to): bool {
                $d = $e->getDate();
                if ($from !== null && $d < $from) return false;
                if ($to   !== null && $d > $to)   return false;
                return true;
            }
        ));
    }

    public function getBalance(string $accountCode): Money
    {
        $account = $this->coa->get($accountCode);
        $normalBalance = $account->getNormalBalance();

        $balance = Money::zero($this->currency);

        foreach ($this->journal as $entry) {
            foreach ($entry->getLines() as $line) {
                if ($line->getAccountCode() !== $accountCode) {
                    continue;
                }
                // If line side matches normal balance → increase
                // Otherwise → decrease
                if ($line->getSide() === $normalBalance) {
                    $balance = $balance->add($line->getAmount());
                } else {
                    $balance = $balance->subtract($line->getAmount());
                }
            }
        }

        return $balance;
    }

    public function getAccountEntries(string $accountCode): array
    {
        return array_values(array_filter(
            $this->journal,
            fn(JournalEntry $e) => $e->touchesAccount($accountCode)
        ));
    }

    // ------------------------------------------------------------------
    // Additional helpers
    // ------------------------------------------------------------------

    /**
     * Return the raw debit/credit totals for an account (T-account view).
     * Returns ['debit' => Money, 'credit' => Money]
     */
    public function getTAccountTotals(string $accountCode): array
    {
        $debit  = Money::zero($this->currency);
        $credit = Money::zero($this->currency);

        foreach ($this->journal as $entry) {
            foreach ($entry->getLines() as $line) {
                if ($line->getAccountCode() !== $accountCode) continue;
                if ($line->isDebit())  { $debit  = $debit->add($line->getAmount()); }
                else                   { $credit = $credit->add($line->getAmount()); }
            }
        }
        return ['debit' => $debit, 'credit' => $credit];
    }

    public function countEntries(): int { return count($this->journal); }

    public function getCoa(): ChartOfAccounts { return $this->coa; }

    public function getCurrency(): string { return $this->currency; }
}
