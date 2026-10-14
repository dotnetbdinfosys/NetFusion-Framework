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

namespace NetFusion\Core\Payment\Gateways;

use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentResult;

class MockGateway extends AbstractGateway
{
    protected bool $shouldFail = false;
    protected string $failureMessage = 'Mock gateway simulated error';
    protected string $failureCode = 'simulated_failure';
    protected float $simulatedFee = 1.0;

    public function __construct(string $name = 'mock', array $config = [])
    {
        parent::__construct(
            name: $name,
            config: $config,
            supportedCurrencies: ['USD', 'EUR', 'GBP', 'BDT', 'CAD', 'AUD', 'JPY']
        );
    }

    public function setShouldFail(bool $fail, string $msg = 'Mock gateway error', string $code = 'simulated_failure'): static
    {
        $this->shouldFail = $fail;
        $this->failureMessage = $msg;
        $this->failureCode = $code;
        return $this;
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        if ($this->shouldFail) {
            return PaymentResult::failure($this->name, $this->failureMessage, $this->failureCode);
        }

        $txId = 'mock_tx_' . bin2hex(random_bytes(8));
        return PaymentResult::success(
            transactionId: $txId,
            processor: $this->name,
            amount: $intent->getAmount(),
            currency: $intent->getCurrency(),
            fee: $this->simulatedFee,
            rawResponse: ['id' => $txId, 'status' => 'succeeded', 'mock' => true]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        if ($this->shouldFail) {
            return PaymentResult::failure($this->name, $this->failureMessage, $this->failureCode);
        }

        $authId = 'mock_auth_' . bin2hex(random_bytes(8));
        return PaymentResult::success(
            transactionId: $authId,
            processor: $this->name,
            amount: $intent->getAmount(),
            currency: $intent->getCurrency(),
            fee: 0.0,
            rawResponse: ['id' => $authId, 'status' => 'authorized']
        );
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        return PaymentResult::success('cap_' . $transactionId, $this->name, $amount);
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        return PaymentResult::success('ref_' . $transactionId, $this->name, $amount);
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        return [
            'event' => 'mock.payment.success',
            'transaction_id' => 'mock_tx_webhook',
            'status' => 'verified',
            'data' => (array)$payload,
        ];
    }
}
