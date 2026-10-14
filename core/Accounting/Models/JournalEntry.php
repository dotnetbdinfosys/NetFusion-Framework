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

use NetFusion\Core\Accounting\Exceptions\UnbalancedJournalEntryException;

/**
 * Class JournalEntry
 *
 * An immutable double-entry accounting transaction.
 *
 * Every valid entry satisfies:
 *   Σ debits == Σ credits (in the same currency)
 *
 * Built with a fluent builder pattern via JournalEntry::create().
 */
final class JournalEntry
{
    /** @var JournalLine[] */
    private array $lines;

    private string $id;
    private string $date;
    private string $reference;
    private string $description;
    private string $currency;
    private bool   $posted = false;
    private string $postedAt = '';

    private function __construct(
        string $date,
        string $description,
        string $reference = '',
        string $currency  = 'USD'
    ) {
        $this->id          = uniqid('je_', true);
        $this->date        = $date;
        $this->description = $description;
        $this->reference   = $reference;
        $this->currency    = strtoupper($currency);
        $this->lines       = [];
    }

    // ------------------------------------------------------------------
    // Builder
    // ------------------------------------------------------------------

    public static function create(
        string $date,
        string $description,
        string $reference = '',
        string $currency  = 'USD'
    ): self {
        return new self($date, $description, $reference, $currency);
    }

    public function debit(string $accountCode, Money $amount, string $desc = ''): self
    {
        $this->lines[] = JournalLine::debit($accountCode, $amount, $desc);
        return $this;
    }

    public function credit(string $accountCode, Money $amount, string $desc = ''): self
    {
        $this->lines[] = JournalLine::credit($accountCode, $amount, $desc);
        return $this;
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate that debits == credits.
     *
     * @throws UnbalancedJournalEntryException
     */
    public function validate(): void
    {
        $debit  = Money::zero($this->currency);
        $credit = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            if ($line->isDebit()) {
                $debit = $debit->add($line->getAmount());
            } else {
                $credit = $credit->add($line->getAmount());
            }
        }

        if (!$debit->equals($credit)) {
            throw new UnbalancedJournalEntryException(
                $debit->getDisplayAmount(),
                $credit->getDisplayAmount(),
                $this->currency
            );
        }

        if (empty($this->lines)) {
            throw new \LogicException('Journal entry has no lines');
        }
    }

    /**
     * Mark this entry as posted (immutably records posting timestamp).
     *
     * @throws UnbalancedJournalEntryException
     */
    public function post(): void
    {
        $this->validate();
        $this->posted   = true;
        $this->postedAt = date('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getId(): string          { return $this->id; }
    public function getDate(): string        { return $this->date; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string   { return $this->reference; }
    public function getCurrency(): string    { return $this->currency; }
    public function isPosted(): bool         { return $this->posted; }
    public function getPostedAt(): string    { return $this->postedAt; }

    /** @return JournalLine[] */
    public function getLines(): array { return $this->lines; }

    /** @return JournalLine[] Only debit lines */
    public function getDebitLines(): array
    {
        return array_values(array_filter($this->lines, fn($l) => $l->isDebit()));
    }

    /** @return JournalLine[] Only credit lines */
    public function getCreditLines(): array
    {
        return array_values(array_filter($this->lines, fn($l) => $l->isCredit()));
    }

    public function getTotalDebits(): Money
    {
        return array_reduce(
            $this->getDebitLines(),
            fn(Money $carry, JournalLine $l) => $carry->add($l->getAmount()),
            Money::zero($this->currency)
        );
    }

    public function getTotalCredits(): Money
    {
        return array_reduce(
            $this->getCreditLines(),
            fn(Money $carry, JournalLine $l) => $carry->add($l->getAmount()),
            Money::zero($this->currency)
        );
    }

    /** True if this entry touches the given account code */
    public function touchesAccount(string $code): bool
    {
        foreach ($this->lines as $line) {
            if ($line->getAccountCode() === $code) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'date'        => $this->date,
            'reference'   => $this->reference,
            'description' => $this->description,
            'currency'    => $this->currency,
            'posted'      => $this->posted,
            'posted_at'   => $this->postedAt,
            'lines'       => array_map(fn($l) => $l->toArray(), $this->lines),
            'total_debit' => $this->getTotalDebits()->getDisplayAmount(),
            'total_credit'=> $this->getTotalCredits()->getDisplayAmount(),
        ];
    }
}
