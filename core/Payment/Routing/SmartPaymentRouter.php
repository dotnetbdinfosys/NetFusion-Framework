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

namespace NetFusion\Core\Payment\Routing;

use NetFusion\Core\Payment\Contracts\PaymentGatewayInterface;
use NetFusion\Core\Payment\Contracts\PaymentRouterInterface;
use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentMethod;
use NetFusion\Core\Payment\Exceptions\PaymentException;

class SmartPaymentRouter implements PaymentRouterInterface
{
    /**
     * @var array<string, PaymentGatewayInterface> Gateway name => Gateway instance
     */
    protected array $gateways = [];

    /**
     * @var array<string, int> Gateway name => Priority (lower number = higher priority)
     */
    protected array $priorities = [];

    protected GatewayHealthTracker $healthTracker;

    public function __construct(?GatewayHealthTracker $healthTracker = null)
    {
        $this->healthTracker = $healthTracker ?? new GatewayHealthTracker();
    }

    public function registerGateway(PaymentGatewayInterface $gateway, int $priority = 10): static
    {
        $this->gateways[$gateway->getName()] = $gateway;
        $this->priorities[$gateway->getName()] = $priority;
        return $this;
    }

    public function getGateway(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    public function getHealthTracker(): GatewayHealthTracker
    {
        return $this->healthTracker;
    }

    /**
     * Route intent to the optimal processor based on preferences, currency, method and health.
     */
    public function route(PaymentIntent $intent): PaymentGatewayInterface
    {
        // 1. Explicit preference check
        $pref = $intent->getPreferredGateway();
        if ($pref !== null && isset($this->gateways[$pref])) {
            $gw = $this->gateways[$pref];
            if ($gw->supportsCurrency($intent->getCurrency()) && $this->healthTracker->isHealthy($pref)) {
                return $gw;
            }
        }

        // 2. Filter available gateways by currency and health
        $currency = $intent->getCurrency();
        $candidates = [];

        foreach ($this->gateways as $name => $gw) {
            if (!$gw->supportsCurrency($currency)) {
                continue;
            }
            if (!$this->healthTracker->isHealthy($name)) {
                continue;
            }

            // Check payment method compatibility (e.g. MFS bKash/Nagad vs Cards)
            $pm = $intent->getPaymentMethod();
            if ($pm !== null && $pm->getType() === PaymentMethod::TYPE_MFS) {
                $brand = strtolower($pm->getBrand() ?? '');
                if ($brand === 'bkash' && $name !== 'bkash' && $name !== 'sslcommerz') {
                    continue;
                }
                if ($brand === 'nagad' && $name !== 'nagad' && $name !== 'sslcommerz') {
                    continue;
                }
            }

            $candidates[$name] = $gw;
        }

        if (empty($candidates)) {
            // If all healthy gateways failed, try any gateway supporting currency (degraded mode)
            foreach ($this->gateways as $name => $gw) {
                if ($gw->supportsCurrency($currency)) {
                    return $gw;
                }
            }
            throw new PaymentException("No payment gateway available to process currency [{$currency}].");
        }

        // 3. Sort candidates by priority score (descending: higher priority number takes precedence)
        uasort($candidates, function (PaymentGatewayInterface $a, PaymentGatewayInterface $b) {
            $pA = $this->priorities[$a->getName()] ?? 10;
            $pB = $this->priorities[$b->getName()] ?? 10;
            return $pB <=> $pA;
        });

        return reset($candidates);
    }

    /**
     * Get fallback gateways in preference order if primary gateway fails.
     *
     * @return array<PaymentGatewayInterface>
     */
    public function getFallbacks(PaymentIntent $intent, string $failedGatewayName): array
    {
        $currency = $intent->getCurrency();
        $fallbacks = [];

        foreach ($this->gateways as $name => $gw) {
            if ($name === $failedGatewayName) {
                continue;
            }
            if (!$gw->supportsCurrency($currency)) {
                continue;
            }
            if (!$this->healthTracker->isHealthy($name)) {
                continue;
            }
            $fallbacks[$name] = $gw;
        }

        uasort($fallbacks, function (PaymentGatewayInterface $a, PaymentGatewayInterface $b) {
            $pA = $this->priorities[$a->getName()] ?? 10;
            $pB = $this->priorities[$b->getName()] ?? 10;
            return $pB <=> $pA;
        });

        return array_values($fallbacks);
    }
}
