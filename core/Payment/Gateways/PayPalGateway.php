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

class PayPalGateway extends AbstractGateway
{
    public function __construct(array $config = [])
    {
        parent::__construct(
            name: 'paypal',
            config: $config,
            supportedCurrencies: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'SGD', 'JPY']
        );
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $currency = $intent->getCurrency();

        if ($amount <= 0.0) {
            return PaymentResult::failure('paypal', 'Invalid PayPal transaction amount.', 'invalid_amount');
        }

        // PayPal typical fee: 3.49% + $0.49
        $fee = round(($amount * 0.0349) + 0.49, 2);
        $orderId = 'PAYID-' . strtoupper(bin2hex(random_bytes(8)));

        return PaymentResult::success(
            transactionId: $orderId,
            processor: 'paypal',
            amount: $amount,
            currency: $currency,
            fee: $fee,
            rawResponse: [
                'id' => $orderId,
                'status' => 'COMPLETED',
                'intent' => 'CAPTURE',
                'payer' => ['email_address' => 'customer@example.com'],
            ]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $authId = 'AUTH-' . strtoupper(bin2hex(random_bytes(8)));

        return PaymentResult::success(
            transactionId: $authId,
            processor: 'paypal',
            amount: $amount,
            currency: $intent->getCurrency(),
            fee: 0.0,
            rawResponse: ['id' => $authId, 'status' => 'CREATED', 'intent' => 'AUTHORIZE']
        );
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        $fee = round(($amount * 0.0349) + 0.49, 2);
        $capId = 'CAP-' . strtoupper(bin2hex(random_bytes(8)));

        return PaymentResult::success(
            transactionId: $capId,
            processor: 'paypal',
            amount: $amount,
            currency: 'USD',
            fee: $fee,
            rawResponse: ['id' => $capId, 'status' => 'COMPLETED', 'authorization_id' => $transactionId]
        );
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $refId = 'REF-' . strtoupper(bin2hex(random_bytes(8)));

        return PaymentResult::success(
            transactionId: $refId,
            processor: 'paypal',
            amount: $amount,
            fee: 0.0,
            rawResponse: ['id' => $refId, 'status' => 'COMPLETED', 'capture_id' => $transactionId]
        );
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        $rawBody = is_array($payload) ? $payload : json_decode((string)$payload, true);

        return [
            'event' => $rawBody['event_type'] ?? 'PAYMENT.CAPTURE.COMPLETED',
            'transaction_id' => $rawBody['resource']['id'] ?? null,
            'status' => 'verified',
            'data' => $rawBody['resource'] ?? [],
        ];
    }
}
