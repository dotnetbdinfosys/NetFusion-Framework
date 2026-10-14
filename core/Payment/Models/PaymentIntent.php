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

namespace NetFusion\Core\Payment\Models;

class PaymentIntent
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REQUIRES_ACTION = 'requires_action';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';

    protected string $id;
    protected string $status = self::STATUS_PENDING;
    protected ?string $paymentMethodToken = null;
    protected ?PaymentMethod $paymentMethod = null;
    protected array $splitRules = [];
    protected ?string $preferredGateway = null;

    /**
     * @param float $amount Amount in primary currency unit (e.g. 100.50 USD)
     * @param string $currency ISO-4217 Currency Code (e.g. 'USD', 'BDT', 'EUR')
     * @param string $customerId
     * @param string $idempotencyKey
     * @param array<string, mixed> $metadata
     * @param string $description
     */
    public function __construct(
        protected float $amount,
        protected string $currency = 'USD',
        protected string $customerId = '',
        protected string $idempotencyKey = '',
        protected array $metadata = [],
        protected string $description = ''
    ) {
        $this->id = 'pi_' . bin2hex(random_bytes(12));
        if ($this->idempotencyKey === '') {
            $this->idempotencyKey = 'idemp_' . bin2hex(random_bytes(16));
        }
    }

    public static function create(float $amount, string $currency = 'USD', string $customerId = ''): static
    {
        return new static($amount, strtoupper($currency), $customerId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return strtoupper($this->currency);
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function withIdempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPaymentMethodToken(): ?string
    {
        return $this->paymentMethodToken;
    }

    public function withPaymentMethodToken(string $token): static
    {
        $this->paymentMethodToken = $token;
        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function withPaymentMethod(PaymentMethod $method): static
    {
        $this->paymentMethod = $method;
        return $this;
    }

    public function getPreferredGateway(): ?string
    {
        return $this->preferredGateway;
    }

    public function viaGateway(string $gatewayName): static
    {
        $this->preferredGateway = $gatewayName;
        return $this;
    }

    /**
     * @return array<SplitRule>
     */
    public function getSplitRules(): array
    {
        return $this->splitRules;
    }

    public function addSplit(SplitRule $rule): static
    {
        $this->splitRules[] = $rule;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function withDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }
}
