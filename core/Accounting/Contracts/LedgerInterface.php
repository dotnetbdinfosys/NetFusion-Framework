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

namespace NetFusion\Core\Accounting\Contracts;

use NetFusion\Core\Accounting\Models\JournalEntry;
use NetFusion\Core\Accounting\Models\Money;

/**
 * Interface LedgerInterface
 *
 * Contract for the combined Journal + General Ledger.
 * Records double-entry transactions and computes account balances.
 */
interface LedgerInterface
{
    /**
     * Post a validated journal entry to both the journal and the general ledger.
     *
     * @throws \NetFusion\Core\Accounting\Exceptions\UnbalancedJournalEntryException
     */
    public function post(JournalEntry $entry): void;

    /**
     * Retrieve all posted journal entries, optionally filtered by date range.
     *
     * @return JournalEntry[]
     */
    public function getEntries(?string $from = null, ?string $to = null): array;

    /**
     * Compute the running balance of an account code.
     * Balance follows the account's normal-balance convention.
     */
    public function getBalance(string $accountCode): Money;

    /**
     * Return all entries affecting a specific account (its T-account).
     *
     * @return JournalEntry[]
     */
    public function getAccountEntries(string $accountCode): array;
}
