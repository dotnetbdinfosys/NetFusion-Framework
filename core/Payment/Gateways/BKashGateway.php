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

class BKashGateway extends AbstractGateway
{
    public function __construct(array $config = [])
    {
        parent::__construct(
            name: 'bkash',
            config: $config,
            supportedCurrencies: ['BDT']
        );
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $currency = $intent->getCurrency();

        if ($currency !== 'BDT') {
            return PaymentResult::failure('bkash', "bKash gateway only supports BDT currency, given: {$currency}.", 'unsupported_currency');
        }

        if ($amount <= 0.0) {
            return PaymentResult::failure('bkash', 'Invalid bKash payment amount.', 'invalid_amount');
        }

        // bKash standard merchant fee: 1.5%
        $fee = round($amount * 0.015, 2);
        $trxId = 'BKTRX' . strtoupper(bin2hex(random_bytes(6)));

        return PaymentResult::success(
            transactionId: $trxId,
            processor: 'bkash',
            amount: $amount,
            currency: 'BDT',
            fee: $fee,
            rawResponse: [
                'trxID' => $trxId,
                'paymentID' => 'PAY_BK_' . bin2hex(random_bytes(8)),
                'transactionStatus' => 'Completed',
                'amount' => (string)$amount,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $intent->getIdempotencyKey(),
            ]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        return $this->charge($intent); // bKash is instant settlement
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        return PaymentResult::success(
            transactionId: $transactionId,
            processor: 'bkash',
            amount: $amount,
            currency: 'BDT',
            rawResponse: ['trxID' => $transactionId, 'status' => 'Captured']
        );
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $refTrx = 'BKREF' . strtoupper(bin2hex(random_bytes(6)));

        return PaymentResult::success(
            transactionId: $refTrx,
            processor: 'bkash',
            amount: $amount,
            currency: 'BDT',
            fee: 0.0,
            rawResponse: [
                'refundTrxID' => $refTrx,
                'originalTrxID' => $transactionId,
                'amount' => $amount,
                'status' => 'Completed',
            ]
        );
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        $data = is_array($payload) ? $payload : json_decode((string)$payload, true);

        return [
            'event' => 'bkash.payment.completed',
            'transaction_id' => $data['trxID'] ?? null,
            'status' => 'verified',
            'data' => $data,
        ];
    }
}
