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

namespace NetFusion\Core\Payment\Reconciliation;

use NetFusion\Core\Payment\Contracts\ReconciliationEngineInterface;
use NetFusion\Core\Payment\Settlement\LedgerJournal;

class ReconciliationEngine implements ReconciliationEngineInterface
{
    /**
     * @var array<string, array<string, array{amount: float, fee: float, status: string}>> Processor => [TxId => Data]
     */
    protected array $internalLedger = [];

    public function __construct(
        protected ?LedgerJournal $ledger = null,
        protected ?\PDO $pdo = null
    ) {
    }

    public function recordInternalTransaction(string $processor, string $transactionId, float $amount, float $fee = 0.0, string $status = 'succeeded'): void
    {
        $this->internalLedger[$processor][$transactionId] = [
            'amount' => $amount,
            'fee' => $fee,
            'status' => $status,
        ];
    }

    /**
     * Ingest bank/processor settlement statement rows and reconcile against internal records.
     *
     * @param string $processor
     * @param array<int, array{transaction_id: string, amount: float, fee: float, status: string, date: string}> $statementRows
     * @return array{matched: int, unmatched: int, fee_variance: float, discrepancies: array}
     */
    public function reconcile(string $processor, array $statementRows): array
    {
        $matched = 0;
        $unmatched = 0;
        $feeVariance = 0.0;
        $discrepancies = [];

        $internal = $this->internalLedger[$processor] ?? [];

        foreach ($statementRows as $row) {
            $txId = $row['transaction_id'];
            $stAmount = (float)$row['amount'];
            $stFee = (float)$row['fee'];
            $stStatus = strtolower($row['status']);

            if (!isset($internal[$txId])) {
                $unmatched++;
                $discrepancies[] = [
                    'type' => 'missing_in_internal_ledger',
                    'transaction_id' => $txId,
                    'processor' => $processor,
                    'statement_amount' => $stAmount,
                ];
                continue;
            }

            $intRecord = $internal[$txId];
            $intAmount = (float)$intRecord['amount'];
            $intFee = (float)$intRecord['fee'];

            $isAmountMatched = (abs($intAmount - $stAmount) < 0.001);
            $isFeeMatched = (abs($intFee - $stFee) < 0.001);

            if (!$isAmountMatched) {
                $unmatched++;
                $discrepancies[] = [
                    'type' => 'amount_mismatch',
                    'transaction_id' => $txId,
                    'internal_amount' => $intAmount,
                    'statement_amount' => $stAmount,
                ];
            } else {
                $matched++;
            }

            if (!$isFeeMatched) {
                $diff = round($stFee - $intFee, 4);
                $feeVariance += $diff;
                $discrepancies[] = [
                    'type' => 'fee_variance',
                    'transaction_id' => $txId,
                    'internal_fee' => $intFee,
                    'statement_fee' => $stFee,
                    'difference' => $diff,
                ];
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'fee_variance' => round($feeVariance, 4),
            'discrepancies' => $discrepancies,
        ];
    }
}
