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

namespace NetFusion\Core\Payment;

use NetFusion\Core\Payment\Contracts\PaymentGatewayInterface;
use NetFusion\Core\Payment\Contracts\PaymentRouterInterface;
use NetFusion\Core\Payment\Contracts\TokenizationVaultInterface;
use NetFusion\Core\Payment\Contracts\SettlementEngineInterface;
use NetFusion\Core\Payment\Contracts\ReconciliationEngineInterface;
use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\PaymentMethod;
use NetFusion\Core\Payment\Models\SplitRule;
use NetFusion\Core\Payment\Routing\SmartPaymentRouter;
use NetFusion\Core\Payment\Vault\PCIConfidenceVault;
use NetFusion\Core\Payment\Settlement\SplitSettlementEngine;
use NetFusion\Core\Payment\Settlement\LedgerJournal;
use NetFusion\Core\Payment\Reconciliation\ReconciliationEngine;
use NetFusion\Core\Payment\Exceptions\PaymentException;
use NetFusion\Core\Payment\Exceptions\IdempotencyException;

class PaymentOrchestrator
{
    /**
     * @var array<string, PaymentResult> IdempotencyKey => PaymentResult
     */
    protected array $idempotencyCache = [];

    /**
     * @param array<string, mixed> $config
     * @param SmartPaymentRouter|null $router
     * @param TokenizationVaultInterface|null $vault
     * @param SettlementEngineInterface|null $settlement
     * @param ReconciliationEngineInterface|null $reconciliation
     * @param \PDO|null $pdo
     */
    public function __construct(
        protected array $config = [],
        protected ?SmartPaymentRouter $router = null,
        protected ?TokenizationVaultInterface $vault = null,
        protected ?SettlementEngineInterface $settlement = null,
        protected ?ReconciliationEngineInterface $reconciliation = null,
        protected ?\PDO $pdo = null
    ) {
        $this->router = $this->router ?? new SmartPaymentRouter();
        $this->vault = $this->vault ?? new PCIConfidenceVault(pdo: $this->pdo);

        if ($this->settlement === null) {
            $ledger = new LedgerJournal(pdo: $this->pdo);
            $this->settlement = new SplitSettlementEngine($ledger);
        }

        $this->reconciliation = $this->reconciliation ?? new ReconciliationEngine(pdo: $this->pdo);
    }

    public function getRouter(): SmartPaymentRouter
    {
        return $this->router;
    }

    public function getVault(): TokenizationVaultInterface
    {
        return $this->vault;
    }

    public function getSettlement(): SettlementEngineInterface
    {
        return $this->settlement;
    }

    public function getReconciliation(): ReconciliationEngineInterface
    {
        return $this->reconciliation;
    }

    public function registerGateway(PaymentGatewayInterface $gateway, int $priority = 10): static
    {
        $this->router->registerGateway($gateway, $priority);
        return $this;
    }

    /**
     * Tokenize raw payment method into PCI-compliant vault token.
     */
    public function tokenize(PaymentMethod $method, string $customerId = ''): string
    {
        return $this->vault->tokenize($method, $customerId);
    }

    /**
     * Execute full payment charge workflow with smart routing, automatic failover retry, and split settlements.
     */
    public function charge(PaymentIntent $intent): PaymentResult
    {
        // 1. Idempotency Check
        $idempKey = $intent->getIdempotencyKey();
        if (isset($this->idempotencyCache[$idempKey])) {
            return $this->idempotencyCache[$idempKey];
        }

        // 2. Resolve Payment Method from Vault if token was provided
        if ($intent->getPaymentMethodToken() !== null && $intent->getPaymentMethod() === null) {
            $resolvedMethod = $this->vault->detokenize($intent->getPaymentMethodToken());
            if ($resolvedMethod === null) {
                return PaymentResult::failure('orchestrator', 'Invalid or expired payment token.', 'invalid_token');
            }
            $intent->withPaymentMethod($resolvedMethod);
        }

        // 3. Resolve Optimal Gateway
        $primaryGw = $this->router->route($intent);
        $result = null;

        try {
            $result = $primaryGw->charge($intent);
            if ($result->isSuccess()) {
                $this->router->getHealthTracker()->recordSuccess($primaryGw->getName());
            } else {
                $this->router->getHealthTracker()->recordFailure($primaryGw->getName());
            }
        } catch (\Throwable $e) {
            $this->router->getHealthTracker()->recordFailure($primaryGw->getName());
            $result = PaymentResult::failure($primaryGw->getName(), $e->getMessage(), 'exception');
        }

        // 4. Automatic Failover to fallback gateways if primary failed
        if (!$result->isSuccess()) {
            $fallbacks = $this->router->getFallbacks($intent, $primaryGw->getName());
            foreach ($fallbacks as $fallbackGw) {
                try {
                    $fbResult = $fallbackGw->charge($intent);
                    if ($fbResult->isSuccess()) {
                        $this->router->getHealthTracker()->recordSuccess($fallbackGw->getName());
                        $result = $fbResult;
                        break;
                    } else {
                        $this->router->getHealthTracker()->recordFailure($fallbackGw->getName());
                    }
                } catch (\Throwable) {
                    $this->router->getHealthTracker()->recordFailure($fallbackGw->getName());
                }
            }
        }

        // 5. Process Marketplace Split Settlements if configured and successful
        if ($result->isSuccess()) {
            $intent->setStatus(PaymentIntent::STATUS_SUCCEEDED);
            if (!empty($intent->getSplitRules())) {
                $this->settlement->calculateSplits($result, $intent->getSplitRules());
            }

            // Record in internal ledger for reconciliation
            if ($this->reconciliation instanceof ReconciliationEngine) {
                $this->reconciliation->recordInternalTransaction(
                    processor: $result->getProcessor(),
                    transactionId: $result->getTransactionId(),
                    amount: $result->getAmount(),
                    fee: $result->getFee(),
                    status: 'succeeded'
                );
            }

            $this->persistTransaction($intent, $result);
        } else {
            $intent->setStatus(PaymentIntent::STATUS_FAILED);
        }

        // Cache for idempotency
        $this->idempotencyCache[$idempKey] = $result;

        return $result;
    }

    /**
     * Authorize payment without immediate capture.
     */
    public function authorize(PaymentIntent $intent): PaymentResult
    {
        $gateway = $this->router->route($intent);
        return $gateway->authorize($intent);
    }

    /**
     * Capture previously authorized transaction.
     */
    public function capture(string $transactionId, float $amount, ?string $gatewayName = null): PaymentResult
    {
        $gw = $gatewayName !== null ? $this->router->getGateway($gatewayName) : null;
        if ($gw === null) {
            // Pick first available gateway that supports capture
            $gw = reset($this->router->getFallbacks(PaymentIntent::create($amount), ''));
        }
        if ($gw === null) {
            throw new PaymentException("No gateway available to capture transaction [{$transactionId}].");
        }
        return $gw->capture($transactionId, $amount);
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $transactionId, float $amount, string $gatewayName = 'stripe', string $reason = ''): PaymentResult
    {
        $gw = $this->router->getGateway($gatewayName);
        if ($gw === null) {
            throw new PaymentException("Gateway [{$gatewayName}] not found for refund.");
        }
        return $gw->refund($transactionId, $amount, $reason);
    }

    /**
     * Handle incoming webhooks across any registered gateway.
     *
     * @param string $gatewayName
     * @param array|string $payload
     * @param array<string, string> $headers
     * @return array
     */
    public function handleWebhook(string $gatewayName, array|string $payload, array $headers): array
    {
        $gw = $this->router->getGateway($gatewayName);
        if ($gw === null) {
            throw new PaymentException("Gateway [{$gatewayName}] not registered for webhook handling.");
        }

        return $gw->verifyWebhook($payload, $headers);
    }

    protected function persistTransaction(PaymentIntent $intent, PaymentResult $res): void
    {
        if ($this->pdo === null) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO nf_payment_transactions 
                (intent_id, transaction_id, processor, amount, currency, fee, status, customer_id, metadata, created_at)
                VALUES (:intent_id, :tx_id, :processor, :amount, :currency, :fee, :status, :customer_id, :metadata, :created_at)");
            $stmt->execute([
                ':intent_id' => $intent->getId(),
                ':tx_id' => $res->getTransactionId(),
                ':processor' => $res->getProcessor(),
                ':amount' => $res->getAmount(),
                ':currency' => $res->getCurrency(),
                ':fee' => $res->getFee(),
                ':status' => $res->getStatus(),
                ':customer_id' => $intent->getCustomerId(),
                ':metadata' => json_encode($intent->getMetadata()),
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
