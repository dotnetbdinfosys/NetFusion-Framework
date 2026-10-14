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

namespace NetFusion\Core\Accounting\Exceptions;

/**
 * Class UnbalancedJournalEntryException
 *
 * Thrown when a journal entry's total debits ≠ total credits.
 * The GAAP fundamental equation requires the entry to balance.
 */
class UnbalancedJournalEntryException extends AccountingException
{
    public function __construct(
        private readonly string $debitTotal,
        private readonly string $creditTotal,
        private readonly string $currency = 'USD'
    ) {
        parent::__construct(
            "Journal entry is unbalanced: debits={$this->currency} {$debitTotal}" .
            " vs credits={$this->currency} {$creditTotal}",
            422
        );
    }

    public function getDebitTotal(): string
    {
        return $this->debitTotal;
    }

    public function getCreditTotal(): string
    {
        return $this->creditTotal;
    }
}
