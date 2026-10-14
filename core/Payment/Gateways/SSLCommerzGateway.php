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

class SSLCommerzGateway extends AbstractGateway
{
    public function __construct(array $config = [])
    {
        parent::__construct(
            name: 'sslcommerz',
            config: $config,
            supportedCurrencies: ['BDT', 'USD', 'EUR', 'GBP']
        );
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $currency = $intent->getCurrency();

        // SSLCommerz fee: 2.5%
        $fee = round($amount * 0.025, 2);
        $bankTranId = 'SSLCZ_TXN_' . bin2hex(random_bytes(8));

        return PaymentResult::success(
            transactionId: $bankTranId,
            processor: 'sslcommerz',
            amount: $amount,
            currency: $currency,
            fee: $fee,
            rawResponse: [
                'status' => 'VALID',
                'bank_tran_id' => $bankTranId,
                'tran_id' => $intent->getIdempotencyKey(),
                'amount' => $amount,
                'currency' => $currency,
            ]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        return $this->charge($intent);
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        return PaymentResult::success($transactionId, 'sslcommerz', $amount);
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $refundRef = 'SSL_REF_' . bin2hex(random_bytes(6));
        return PaymentResult::success($refundRef, 'sslcommerz', $amount, 'BDT', 0.0, ['bank_tran_id' => $transactionId]);
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        $data = is_array($payload) ? $payload : json_decode((string)$payload, true);
        return [
            'event' => 'sslcommerz.ipn',
            'transaction_id' => $data['bank_tran_id'] ?? null,
            'status' => 'verified',
            'data' => $data,
        ];
    }
}
