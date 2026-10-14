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

interface PaymentRouterInterface
{
    /**
     * Resolve the optimal gateway for a payment intent.
     */
    public function route(PaymentIntent $intent): PaymentGatewayInterface;

    /**
     * Get fallback gateways in preference order if the primary gateway fails.
     *
     * @return array<PaymentGatewayInterface>
     */
    public function getFallbacks(PaymentIntent $intent, string $failedGatewayName): array;
}
