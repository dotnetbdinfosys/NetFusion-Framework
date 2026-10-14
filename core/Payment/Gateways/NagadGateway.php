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

class NagadGateway extends AbstractGateway
{
    public function __construct(array $config = [])
    {
        parent::__construct(
            name: 'nagad',
            config: $config,
            supportedCurrencies: ['BDT']
        );
    }

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $amount = $intent->getAmount();
        $currency = $intent->getCurrency();

        if ($currency !== 'BDT') {
            return PaymentResult::failure('nagad', "Nagad gateway only supports BDT currency, given: {$currency}.", 'unsupported_currency');
        }

        // Nagad fee: 1.5%
        $fee = round($amount * 0.015, 2);
        $issuerTrxId = 'NGD' . strtoupper(bin2hex(random_bytes(6)));

        return PaymentResult::success(
            transactionId: $issuerTrxId,
            processor: 'nagad',
            amount: $amount,
            currency: 'BDT',
            fee: $fee,
            rawResponse: [
                'issuerPaymentRefNo' => $issuerTrxId,
                'paymentRefId' => 'NAGAD_REF_' . bin2hex(random_bytes(6)),
                'status' => 'Success',
                'amount' => $amount,
            ]
        );
    }

    public function authorize(PaymentIntent $intent): PaymentResult
    {
        return $this->charge($intent);
    }

    public function capture(string $transactionId, float $amount): PaymentResult
    {
        return PaymentResult::success($transactionId, 'nagad', $amount, 'BDT');
    }

    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $refId = 'NGD_REFUND_' . strtoupper(bin2hex(random_bytes(4)));
        return PaymentResult::success($refId, 'nagad', $amount, 'BDT', 0.0, ['original_trx' => $transactionId]);
    }

    public function verifyWebhook(array|string $payload, array $headers): array
    {
        $data = is_array($payload) ? $payload : json_decode((string)$payload, true);
        return [
            'event' => 'nagad.payment.verified',
            'transaction_id' => $data['issuerPaymentRefNo'] ?? null,
            'status' => 'verified',
            'data' => $data,
        ];
    }
}
