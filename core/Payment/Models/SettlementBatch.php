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

namespace NetFusion\Core\Payment\Models;

class SettlementBatch
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected string $batchId;
    protected string $status = self::STATUS_PENDING;

    /**
     * @param string $currency
     * @param array<int, array{vendor_id: string, amount: float, transaction_id: string, due_date: string}> $payouts
     * @param float $totalAmount
     */
    public function __construct(
        protected string $currency = 'USD',
        protected array $payouts = [],
        protected float $totalAmount = 0.0
    ) {
        $this->batchId = 'bat_' . bin2hex(random_bytes(10));
        if ($this->totalAmount === 0.0 && !empty($this->payouts)) {
            $this->totalAmount = array_sum(array_column($this->payouts, 'amount'));
        }
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPayouts(): array
    {
        return $this->payouts;
    }

    public function addPayout(string $vendorId, float $amount, string $transactionId, string $dueDate = ''): static
    {
        $this->payouts[] = [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'due_date' => $dueDate ?: date('Y-m-d H:i:s'),
        ];
        $this->totalAmount += $amount;
        return $this;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }
}
