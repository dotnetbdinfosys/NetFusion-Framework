<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Atomic state transition (transaction) specification.
 *
 * @package    NetFusion\Core\Rollup\Execution
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\Execution;

use NetFusion\Core\Rollup\Enums\StateTransitionType;

class StateTransition
{
    public function __construct(
        protected readonly string $transitionId,
        protected readonly StateTransitionType $type,
        protected readonly string $from,
        protected readonly string $to,
        protected readonly float $amount,
        protected readonly int $nonce,
        protected ?string $signature = null,
        protected readonly array $extraData = [],
        protected readonly int $timestamp = 0
    ) {
    }

    public function getTransitionId(): string
    {
        return $this->transitionId;
    }

    public function getType(): StateTransitionType
    {
        return $this->type;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getNonce(): int
    {
        return $this->nonce;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function getExtraData(): array
    {
        return $this->extraData;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp > 0 ? $this->timestamp : time();
    }

    /**
     * Compute payload hash to be signed or tracked.
     */
    public function getPayloadHash(): string
    {
        $payload = sprintf(
            '%s:%s:%s:%s:%.8f:%d:%s',
            $this->transitionId,
            $this->type->value,
            $this->from,
            $this->to,
            $this->amount,
            $this->nonce,
            json_encode($this->extraData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
        return hash('sha256', $payload);
    }

    /**
     * Attach cryptographic signature to this transition.
     */
    public function withSignature(string $signature): self
    {
        $clone = clone $this;
        $clone->signature = $signature;
        return $clone;
    }

    /**
     * Sign transition using HMAC secret key.
     */
    public function signWithSecret(string $secret): self
    {
        $sig = hash_hmac('sha256', $this->getPayloadHash(), $secret);
        return $this->withSignature($sig);
    }

    /**
     * Verify transition signature against secret or public key.
     */
    public function verifySignature(?string $secretOrKey = null): bool
    {
        if ($this->signature === null) {
            return false;
        }

        if ($secretOrKey === null) {
            // Unsigned/internal transitions can be verified with non-empty signature
            return strlen($this->signature) > 0;
        }

        $expected = hash_hmac('sha256', $this->getPayloadHash(), $secretOrKey);
        return hash_equals($expected, $this->signature);
    }

    public function toArray(): array
    {
        return [
            'transition_id' => $this->transitionId,
            'type'          => $this->type->value,
            'from'          => $this->from,
            'to'            => $this->to,
            'amount'        => $this->amount,
            'nonce'         => $this->nonce,
            'signature'     => $this->signature,
            'extra_data'    => $this->extraData,
            'timestamp'     => $this->getTimestamp(),
            'payload_hash'  => $this->getPayloadHash(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['transition_id'] ?? bin2hex(random_bytes(16))),
            StateTransitionType::from((string) ($data['type'] ?? 'transfer')),
            (string) ($data['from'] ?? ''),
            (string) ($data['to'] ?? ''),
            (float) ($data['amount'] ?? 0.0),
            (int) ($data['nonce'] ?? 0),
            isset($data['signature']) ? (string) $data['signature'] : null,
            (array) ($data['extra_data'] ?? []),
            (int) ($data['timestamp'] ?? time())
        );
    }
}
