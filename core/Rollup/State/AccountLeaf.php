<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Account state representation within the Sparse Merkle Tree state leaves.
 *
 * @package    NetFusion\Core\Rollup\State
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\State;

class AccountLeaf
{
    public function __construct(
        protected readonly string $accountId,
        protected readonly string $publicKey,
        protected float $balance = 0.0,
        protected int $nonce = 0,
        protected array $metadata = []
    ) {
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getNonce(): int
    {
        return $this->nonce;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Return a cloned instance with updated balance.
     */
    public function withBalance(float $balance): self
    {
        $clone = clone $this;
        $clone->balance = $balance;
        return $clone;
    }

    /**
     * Return a cloned instance with incremented transaction nonce.
     */
    public function incrementNonce(): self
    {
        $clone = clone $this;
        $clone->nonce++;
        return $clone;
    }

    /**
     * Compute cryptographic deterministic leaf hash.
     */
    public function getHash(): string
    {
        $payload = sprintf(
            '%s:%s:%.8f:%d:%s',
            $this->accountId,
            $this->publicKey,
            $this->balance,
            $this->nonce,
            json_encode($this->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
        return hash('sha256', $payload);
    }

    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'public_key' => $this->publicKey,
            'balance'    => $this->balance,
            'nonce'      => $this->nonce,
            'metadata'   => $this->metadata,
            'hash'       => $this->getHash(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['account_id'] ?? ''),
            (string) ($data['public_key'] ?? ''),
            (float) ($data['balance'] ?? 0.0),
            (int) ($data['nonce'] ?? 0),
            (array) ($data['metadata'] ?? [])
        );
    }
}
