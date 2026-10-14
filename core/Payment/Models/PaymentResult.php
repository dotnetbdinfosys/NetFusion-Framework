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

class PaymentResult
{
    /**
     * @param bool $success
     * @param string $transactionId External gateway reference ID
     * @param string $processor Gateway name (e.g. 'stripe', 'paypal', 'bkash')
     * @param float $amount
     * @param string $currency
     * @param float $fee Processor fee charged
     * @param string $status ('succeeded', 'pending', 'failed', 'authorized')
     * @param string $rawErrorCode
     * @param string $errorMessage
     * @param array<string, mixed> $rawResponse
     * @param array<string, float> $splits VendorId => SplitAmount
     */
    public function __construct(
        protected bool $success,
        protected string $transactionId,
        protected string $processor,
        protected float $amount,
        protected string $currency = 'USD',
        protected float $fee = 0.0,
        protected string $status = 'succeeded',
        protected string $rawErrorCode = '',
        protected string $errorMessage = '',
        protected array $rawResponse = [],
        protected array $splits = []
    ) {
    }

    public static function success(
        string $transactionId,
        string $processor,
        float $amount,
        string $currency = 'USD',
        float $fee = 0.0,
        array $rawResponse = []
    ): static {
        return new static(
            success: true,
            transactionId: $transactionId,
            processor: $processor,
            amount: $amount,
            currency: strtoupper($currency),
            fee: $fee,
            status: 'succeeded',
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $processor,
        string $errorMessage,
        string $rawErrorCode = '',
        array $rawResponse = []
    ): static {
        return new static(
            success: false,
            transactionId: '',
            processor: $processor,
            amount: 0.0,
            status: 'failed',
            rawErrorCode: $rawErrorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getProcessor(): string
    {
        return $this->processor;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFee(): float
    {
        return $this->fee;
    }

    public function getNet(): float
    {
        return max(0.0, $this->amount - $this->fee);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRawErrorCode(): string
    {
        return $this->rawErrorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function getSplits(): array
    {
        return $this->splits;
    }

    public function setSplits(array $splits): static
    {
        $this->splits = $splits;
        return $this;
    }
}
