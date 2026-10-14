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

namespace NetFusion\Core\Payment\Contracts;

use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\PaymentMethod;

interface PaymentGatewayInterface
{
    /**
     * Get unique identifier for the gateway (e.g. 'stripe', 'paypal', 'bkash').
     */
    public function getName(): string;

    /**
     * Check if gateway supports the given currency.
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Direct purchase/charge transaction.
     */
    public function charge(PaymentIntent $intent): PaymentResult;

    /**
     * Authorize payment without immediate capture.
     */
    public function authorize(PaymentIntent $intent): PaymentResult;

    /**
     * Capture previously authorized transaction.
     */
    public function capture(string $transactionId, float $amount): PaymentResult;

    /**
     * Refund full or partial payment amount.
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): PaymentResult;

    /**
     * Void authorized transaction before capture.
     */
    public function void(string $transactionId): PaymentResult;

    /**
     * Create tokenized payment method for customer.
     */
    public function tokenize(PaymentMethod $method): string;

    /**
     * Verify incoming webhook signature and parse event payload.
     *
     * @param array<string, mixed>|string $payload
     * @param array<string, string> $headers
     * @return array{event: string, transaction_id: ?string, status: string, data: array}
     */
    public function verifyWebhook(array|string $payload, array $headers): array;
}
