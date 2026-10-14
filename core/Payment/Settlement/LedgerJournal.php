<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Payment\Settlement;

class LedgerJournal
{
    /**
     * @var array<int, array{entry_id: string, account: string, party_id: string, debit: float, credit: float, currency: string, reference: string, created_at: string}>
     */
    protected array $entries = [];

    public function __construct(
        protected ?\PDO $pdo = null
    ) {
    }

    /**
     * Post a double-entry balanced transaction.
     */
    public function post(
        string $debitAccount,
        string $creditAccount,
        float $amount,
        string $currency,
        string $partyId = 'platform',
        string $reference = ''
    ): string {
        $amount = round($amount, 4);
        if ($amount <= 0.0) {
            return '';
        }

        $now = date('Y-m-d H:i:s');
        $entryId = 'ent_' . bin2hex(random_bytes(10));

        // Debit entry
        $this->entries[] = [
            'entry_id' => $entryId . '_dr',
            'account' => $debitAccount,
            'party_id' => $partyId,
            'debit' => $amount,
            'credit' => 0.0,
            'currency' => strtoupper($currency),
            'reference' => $reference,
            'created_at' => $now,
        ];

        // Credit entry
        $this->entries[] = [
            'entry_id' => $entryId . '_cr',
            'account' => $creditAccount,
            'party_id' => $partyId,
            'debit' => 0.0,
            'credit' => $amount,
            'currency' => strtoupper($currency),
            'reference' => $reference,
            'created_at' => $now,
        ];

        if ($this->pdo !== null) {
            $this->persistEntry($this->entries[count($this->entries) - 2]);
            $this->persistEntry($this->entries[count($this->entries) - 1]);
        }

        return $entryId;
    }

    public function getBalance(string $account, string $partyId = '', string $currency = 'USD'): float
    {
        $balance = 0.0;
        foreach ($this->entries as $entry) {
            if ($entry['account'] !== $account) {
                continue;
            }
            if ($partyId !== '' && $entry['party_id'] !== $partyId) {
                continue;
            }
            if ($entry['currency'] !== strtoupper($currency)) {
                continue;
            }

            // Standard asset/expense accounts vs liability/equity/revenue accounts
            $balance += ($entry['credit'] - $entry['debit']);
        }

        return round($balance, 4);
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function isBalanced(): bool
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($this->entries as $e) {
            $totalDebit += (float)$e['debit'];
            $totalCredit += (float)$e['credit'];
        }
        return abs($totalDebit - $totalCredit) < 0.0001;
    }

    protected function persistEntry(array $e): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO nf_payment_ledger (entry_id, account, party_id, debit, credit, currency, reference, created_at)
                VALUES (:entry_id, :account, :party_id, :debit, :credit, :currency, :reference, :created_at)");
            $stmt->execute([
                ':entry_id' => $e['entry_id'],
                ':account' => $e['account'],
                ':party_id' => $e['party_id'],
                ':debit' => $e['debit'],
                ':credit' => $e['credit'],
                ':currency' => $e['currency'],
                ':reference' => $e['reference'],
                ':created_at' => $e['created_at'],
            ]);
        } catch (\Throwable) {
        }
    }
}
