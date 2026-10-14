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

use NetFusion\Core\Payment\Contracts\PaymentGatewayInterface;
use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\PaymentMethod;
use NetFusion\Core\Payment\Exceptions\GatewayException;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    /**
     * @param string $name
     * @param array<string, mixed> $config
     * @param array<string> $supportedCurrencies
     */
    public function __construct(
        protected string $name,
        protected array $config = [],
        protected array $supportedCurrencies = ['USD', 'EUR', 'GBP', 'BDT', 'CAD', 'AUD']
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', $this->supportedCurrencies), true);
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function tokenize(PaymentMethod $method): string
    {
        $brand = $method->getBrand() ?? 'card';
        return 'tok_' . strtolower($this->name) . '_' . strtolower($brand) . '_' . bin2hex(random_bytes(8));
    }

    public function void(string $transactionId): PaymentResult
    {
        // Default implementation translates void to 0-amount or full release
        return PaymentResult::success(
            transactionId: 'void_' . $transactionId,
            processor: $this->name,
            amount: 0.0,
            rawResponse: ['action' => 'voided', 'original_id' => $transactionId]
        );
    }
}
