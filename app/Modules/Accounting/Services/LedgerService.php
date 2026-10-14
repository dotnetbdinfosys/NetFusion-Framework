<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Services/LedgerService.php
| Module    : Accounting
| Namespace : NetFusion\Modules\Accounting\Services
| Purpose   : Core double-entry ledger calculation, journal posting, and financial statements.
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

namespace NetFusion\Modules\Accounting\Services;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Modules\Accounting\Models\Account;
use NetFusion\Modules\Accounting\Models\JournalEntry;
use NetFusion\Modules\Accounting\Models\JournalItem;

/**
 * Class LedgerService
 *
 * Implements strict double-entry ledger accounting, Trial Balance, Income Statement, and Balance Sheet.
 *
 * @package NetFusion\Modules\Accounting\Services
 */
class LedgerService
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Create and validate a double-entry journal voucher.
     *
     * @param array<string, mixed> $entryData
     * @param array<int, array{account_id: int, debit: float, credit: float, description?: string}> $items
     * @return JournalEntry
     *
     * @throws InvalidArgumentException
     */
    public function createEntry(array $entryData, array $items): JournalEntry
    {
        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $totalDebit  += (float) ($item['debit'] ?? 0.0);
            $totalCredit += (float) ($item['credit'] ?? 0.0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001 || $totalDebit <= 0.0) {
            throw new InvalidArgumentException(
                sprintf('Journal entry is unbalanced: Total Debits (%.2f) must equal Total Credits (%.2f).', $totalDebit, $totalCredit)
            );
        }

        $db = $this->app->make(DatabaseManager::class)->connection();
        $db->beginTransaction();

        try {
            $entry = JournalEntry::create([
                'entry_number' => $entryData['entry_number'] ?? ('JV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5))),
                'entry_date'   => $entryData['entry_date'] ?? date('Y-m-d'),
                'reference'    => $entryData['reference'] ?? null,
                'description'  => $entryData['description'] ?? 'General Journal Entry',
                'status'       => $entryData['status'] ?? JournalEntry::STATUS_DRAFT,
                'total_amount' => $totalDebit,
                'posted_at'    => ($entryData['status'] ?? '') === JournalEntry::STATUS_POSTED ? date('Y-m-d H:i:s') : null,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            foreach ($items as $item) {
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => (int) $item['account_id'],
                    'debit'            => (float) ($item['debit'] ?? 0.0),
                    'credit'           => (float) ($item['credit'] ?? 0.0),
                    'description'      => $item['description'] ?? null,
                ]);
            }

            $db->commit();
            return $entry;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Post a draft journal entry to the General Ledger.
     */
    public function postEntry(int|JournalEntry $entry): bool
    {
        $model = $entry instanceof JournalEntry ? $entry : JournalEntry::find($entry);
        if (!$model) {
            return false;
        }

        if (!$model->isBalanced()) {
            throw new InvalidArgumentException('Cannot post unbalanced journal entry.');
        }

        $model->status = JournalEntry::STATUS_POSTED;
        $model->posted_at = date('Y-m-d H:i:s');
        return $model->save();
    }

    /**
     * Generate Trial Balance.
     *
     * @return array{accounts: array<int, array<string, mixed>>, total_debit: float, total_credit: float, is_balanced: bool}
     */
    public function getTrialBalance(): array
    {
        $db = $this->app->make(DatabaseManager::class)->connection();

        $rows = $db->select("
            SELECT 
                a.id, a.code, a.name, a.type, a.normal_balance,
                COALESCE(SUM(ji.debit), 0.0) AS total_debit,
                COALESCE(SUM(ji.credit), 0.0) AS total_credit
            FROM acc_chart_of_accounts a
            LEFT JOIN acc_journal_items ji ON a.id = ji.account_id
            LEFT JOIN acc_journal_entries je ON ji.journal_entry_id = je.id AND je.status = 'posted'
            WHERE a.is_active = 1
            GROUP BY a.id, a.code, a.name, a.type, a.normal_balance
            ORDER BY a.code ASC
        ");

        $accounts = [];
        $totalDr  = 0.0;
        $totalCr  = 0.0;

        foreach ($rows as $row) {
            $dr = (float) $row['total_debit'];
            $cr = (float) $row['total_credit'];
            $normal = $row['normal_balance'];

            $balanceDr = 0.0;
            $balanceCr = 0.0;

            if ($normal === Account::BALANCE_DEBIT) {
                $net = $dr - $cr;
                if ($net >= 0) {
                    $balanceDr = $net;
                } else {
                    $balanceCr = abs($net);
                }
            } else {
                $net = $cr - $dr;
                if ($net >= 0) {
                    $balanceCr = $net;
                } else {
                    $balanceDr = abs($net);
                }
            }

            $totalDr += $balanceDr;
            $totalCr += $balanceCr;

            $accounts[] = [
                'id'             => $row['id'],
                'code'           => $row['code'],
                'name'           => $row['name'],
                'type'           => $row['type'],
                'normal_balance' => $normal,
                'debit'          => round($balanceDr, 2),
                'credit'         => round($balanceCr, 2),
            ];
        }

        $totalDr = round($totalDr, 2);
        $totalCr = round($totalCr, 2);

        return [
            'accounts'     => $accounts,
            'total_debit'  => $totalDr,
            'total_credit' => $totalCr,
            'is_balanced'  => abs($totalDr - $totalCr) < 0.01,
        ];
    }

    /**
     * Generate Income Statement (Profit & Loss Statement).
     *
     * Net Income = Total Revenue - Total Expenses
     *
     * @return array{revenue: array<string, mixed>, expenses: array<string, mixed>, net_income: float}
     */
    public function getIncomeStatement(): array
    {
        $trial = $this->getTrialBalance();

        $revenues = [];
        $expenses = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($trial['accounts'] as $acc) {
            if ($acc['type'] === Account::TYPE_REVENUE) {
                $amount = $acc['credit'] - $acc['debit'];
                $revenues[] = ['code' => $acc['code'], 'name' => $acc['name'], 'amount' => $amount];
                $totalRevenue += $amount;
            } elseif ($acc['type'] === Account::TYPE_EXPENSE) {
                $amount = $acc['debit'] - $acc['credit'];
                $expenses[] = ['code' => $acc['code'], 'name' => $acc['name'], 'amount' => $amount];
                $totalExpenses += $amount;
            }
        }

        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'revenue'        => ['items' => $revenues, 'total' => round($totalRevenue, 2)],
            'expenses'       => ['items' => $expenses, 'total' => round($totalExpenses, 2)],
            'net_income'     => round($netIncome, 2),
        ];
    }

    /**
     * Generate Balance Sheet.
     *
     * Assets = Liabilities + Equity (including Net Income)
     *
     * @return array{assets: array<string, mixed>, liabilities: array<string, mixed>, equity: array<string, mixed>, is_balanced: bool}
     */
    public function getBalanceSheet(): array
    {
        $trial = $this->getTrialBalance();
        $incomeStatement = $this->getIncomeStatement();

        $assets = [];
        $liabilities = [];
        $equities = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($trial['accounts'] as $acc) {
            if ($acc['type'] === Account::TYPE_ASSET) {
                $val = $acc['debit'] - $acc['credit'];
                $assets[] = ['code' => $acc['code'], 'name' => $acc['name'], 'amount' => $val];
                $totalAssets += $val;
            } elseif ($acc['type'] === Account::TYPE_LIABILITY) {
                $val = $acc['credit'] - $acc['debit'];
                $liabilities[] = ['code' => $acc['code'], 'name' => $acc['name'], 'amount' => $val];
                $totalLiabilities += $val;
            } elseif ($acc['type'] === Account::TYPE_EQUITY) {
                $val = $acc['credit'] - $acc['debit'];
                $equities[] = ['code' => $acc['code'], 'name' => $acc['name'], 'amount' => $val];
                $totalEquity += $val;
            }
        }

        // Retained earnings includes current period net income
        $netIncome = $incomeStatement['net_income'];
        $equities[] = [
            'code'   => 'RE-CURRENT',
            'name'   => 'Current Period Net Earnings',
            'amount' => $netIncome,
        ];
        $totalEquity += $netIncome;

        $totalAssets = round($totalAssets, 2);
        $totalLiabilities = round($totalLiabilities, 2);
        $totalEquity = round($totalEquity, 2);
        $totalLiabAndEquity = round($totalLiabilities + $totalEquity, 2);

        return [
            'assets'               => ['items' => $assets, 'total' => $totalAssets],
            'liabilities'          => ['items' => $liabilities, 'total' => $totalLiabilities],
            'equity'               => ['items' => $equities, 'total' => $totalEquity],
            'total_liab_and_equity'=> $totalLiabAndEquity,
            'is_balanced'          => abs($totalAssets - $totalLiabAndEquity) < 0.01,
        ];
    }
}
