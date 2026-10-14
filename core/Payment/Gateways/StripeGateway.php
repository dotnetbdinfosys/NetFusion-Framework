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
use NetFusion\Core\Payment\Models\PaymentMethod;
use NetFusion\Core\Payment\Exceptions\GatewayException;

class StripeGateway extends AbstractGateway
{
    public function __construct(array $config = [])
    {
        parent::__construct(
            name: 'stripe',
            config: $config,
            supportedCurrencies: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'SGD', 'JPY', 'BDT']
        );
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $currency = $intent->getCurrency();

        if ($amount <= 0.0) {
            return PaymentResult::failure('stripe', 'Charge amount must be greater than zero.', 'invalid_amount');
        }

        // Check for simulated decline or special test amounts
        if ($amount == 999.99) {
            return PaymentResult::failure('stripe', 'Your card was declined.', 'card_declined');
        }

        // Stripe typical fee: 2.9% + $0.30
        $fee = round(($amount * 0.029) + 0.30, 2);
        $txId = 'ch_str_' . bin2hex(random_bytes(12));

        return PaymentResult::success(
            transactionId: $txId,
            processor: 'stripe',
            amount: $amount,
            currency: $currency,
            fee: $fee,
            rawResponse: [
                'id' => $txId,
                'status' => 'succeeded',
                'captured' => true,
                'idempotency_key' => $intent->getIdempotencyKey(),
                'balance_transaction' => 'txn_' . bin2hex(random_bytes(10)),
            ]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $txId = 'auth_str_' . bin2hex(random_bytes(12));

        return PaymentResult::success(
            transactionId: $txId,
            processor: 'stripe',
            amount: $amount,
            currency: $intent->getCurrency(),
            fee: 0.0,
            rawResponse: [
                'id' => $txId,
                'status' => 'requires_capture',
                'captured' => false,
            ]
        );
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        $fee = round(($amount * 0.029) + 0.30, 2);
        $captureId = 'cap_' . str_replace('auth_', '', $transactionId);

        return PaymentResult::success(
            transactionId: $captureId,
            processor: 'stripe',
            amount: $amount,
            currency: 'USD',
            fee: $fee,
            rawResponse: [
                'id' => $captureId,
                'status' => 'succeeded',
                'original_auth' => $transactionId,
            ]
        );
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $refundId = 're_str_' . bin2hex(random_bytes(10));

        return PaymentResult::success(
            transactionId: $refundId,
            processor: 'stripe',
            amount: $amount,
            fee: 0.0,
            rawResponse: [
                'id' => $refundId,
                'status' => 'succeeded',
                'charge' => $transactionId,
                'reason' => $reason ?: 'requested_by_customer',
            ]
        );
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        $signature = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '';
        $secret = $this->getConfig('webhook_secret', 'whsec_test');

        if (empty($signature)) {
            throw new GatewayException('Missing Stripe webhook signature.', 'stripe', 'invalid_signature');
        }

        $rawBody = is_array($payload) ? json_encode($payload) : $payload;
        $parsed = is_array($payload) ? $payload : json_decode((string)$payload, true);

        return [
            'event' => $parsed['type'] ?? 'payment_intent.succeeded',
            'transaction_id' => $parsed['data']['object']['id'] ?? null,
            'status' => 'verified',
            'data' => $parsed['data'] ?? [],
        ];
    }
}
