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

use NetFusion\Core\Payment\Contracts\SettlementEngineInterface;
use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\SplitRule;
use NetFusion\Core\Payment\Models\SettlementBatch;
use NetFusion\Core\Payment\Exceptions\SettlementException;

class SplitSettlementEngine implements SettlementEngineInterface
{
    /**
     * @var array<int, array{vendor_id: string, amount: float, currency: string, transaction_id: string, due_date: string, status: string}>
     */
    protected array $pendingPayouts = [];

    /**
     * @var array<string, SettlementBatch>
     */
    protected array $batches = [];

    public function __construct(
        protected LedgerJournal $ledger
    ) {
    }

    public function getLedger(): LedgerJournal
    {
        return $this->ledger;
    }

    /**
     * Compute and record split settlement allocations for a transaction.
     *
     * @param PaymentResult $result
     * @param array<SplitRule> $splitRules
     * @param float $platformFee Platform commission fee
     * @return array<string, float> VendorId => SplitAmount
     */
    public function calculateSplits(PaymentResult $result, array $splitRules, float $platformFee = 0.0): array
    {
        if (!$result->isSuccess()) {
            throw new SettlementException("Cannot calculate settlement splits on a failed transaction.");
        }

        $gross = $result->getAmount();
        $currency = $result->getCurrency();
        $txId = $result->getTransactionId();
        $splits = [];
        $allocated = 0.0;

        // 1. Record customer gross payment into escrow
        $this->ledger->post(
            debitAccount: 'escrow_holding',
            creditAccount: 'customer_clearing',
            amount: $gross,
            currency: $currency,
            partyId: 'platform',
            reference: $txId
        );

        // 2. Calculate vendor shares
        foreach ($splitRules as $rule) {
            $share = $rule->calculateAmount($gross);
            if ($share > 0.0) {
                $splits[$rule->getVendorId()] = $share;
                $allocated += $share;

                // Credit vendor payable account from escrow
                $this->ledger->post(
                    debitAccount: 'escrow_holding',
                    creditAccount: 'vendor_payable',
                    amount: $share,
                    currency: $currency,
                    partyId: $rule->getVendorId(),
                    reference: $txId
                );

                $dueDate = date('Y-m-d H:i:s', time() + ($rule->getDelayDays() * 86400));
                $this->pendingPayouts[] = [
                    'vendor_id' => $rule->getVendorId(),
                    'amount' => $share,
                    'currency' => $currency,
                    'transaction_id' => $txId,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ];
            }
        }

        // 3. Platform Revenue
        $platformNet = max(0.0, $gross - $allocated - $platformFee);
        if ($platformNet > 0.0) {
            $this->ledger->post(
                debitAccount: 'escrow_holding',
                creditAccount: 'platform_revenue',
                amount: $platformNet,
                currency: $currency,
                partyId: 'platform',
                reference: $txId
            );
        }

        // 4. Record Gateway Fee expense
        if ($result->getFee() > 0.0) {
            $this->ledger->post(
                debitAccount: 'processor_fee_expense',
                creditAccount: 'escrow_holding',
                amount: $result->getFee(),
                currency: $currency,
                partyId: $result->getProcessor(),
                reference: $txId
            );
        }

        $result->setSplits($splits);
        return $splits;
    }

    public function createBatch(string $currency): SettlementBatch
    {
        $currency = strtoupper($currency);
        $batch = new SettlementBatch($currency);
        $now = date('Y-m-d H:i:s');

        foreach ($this->pendingPayouts as $idx => $p) {
            if ($p['currency'] === $currency && $p['status'] === 'pending' && $p['due_date'] <= $now) {
                $batch->addPayout($p['vendor_id'], $p['amount'], $p['transaction_id'], $p['due_date']);
                $this->pendingPayouts[$idx]['status'] = 'batched';
            }
        }

        $this->batches[$batch->getBatchId()] = $batch;
        return $batch;
    }

    public function processBatch(SettlementBatch $batch): bool
    {
        $batch->setStatus(SettlementBatch::STATUS_PROCESSING);

        foreach ($batch->getPayouts() as $payout) {
            // Settle vendor payable to cash/bank
            $this->ledger->post(
                debitAccount: 'vendor_payable',
                creditAccount: 'bank_cash_settled',
                amount: $payout['amount'],
                currency: $batch->getCurrency(),
                partyId: $payout['vendor_id'],
                reference: 'payout_' . $payout['transaction_id']
            );
        }

        $batch->setStatus(SettlementBatch::STATUS_COMPLETED);
        return true;
    }

    public function getPendingPayouts(): array
    {
        return $this->pendingPayouts;
    }

    public function getBatches(): array
    {
        return $this->batches;
    }
}
