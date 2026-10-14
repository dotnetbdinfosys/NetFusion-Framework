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

namespace NetFusion\Core\ServiceMesh\Resilience;

use JsonSerializable;

class MeshRateLimiter implements JsonSerializable
{
    protected float $ratePerSecond;
    protected int $capacity;
    /** @var array<string, array{tokens: float, lastRefill: float}> */
    protected array $buckets = [];

    public function __construct(float $ratePerSecond = 100.0, int $capacity = 100)
    {
        $this->ratePerSecond = max(0.1, $ratePerSecond);
        $this->capacity = max(1, $capacity);
    }

    public static function perSecond(int $limit, int $burst = 0): self
    {
        return new self((float) $limit, $burst > 0 ? $burst : $limit);
    }

    public function consume(string $key = 'default', int $tokens = 1): bool
    {
        $now = microtime(true);

        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'tokens' => (float) $this->capacity,
                'lastRefill' => $now,
            ];
        }

        $bucket = &$this->buckets[$key];
        $elapsed = $now - $bucket['lastRefill'];

        // Refill tokens
        $refill = $elapsed * $this->ratePerSecond;
        $bucket['tokens'] = min((float) $this->capacity, $bucket['tokens'] + $refill);
        $bucket['lastRefill'] = $now;

        if ($bucket['tokens'] >= $tokens) {
            $bucket['tokens'] -= $tokens;
            return true;
        }

        return false;
    }

    public function getAvailableTokens(string $key = 'default'): float
    {
        if (!isset($this->buckets[$key])) {
            return (float) $this->capacity;
        }

        $now = microtime(true);
        $bucket = $this->buckets[$key];
        $elapsed = $now - $bucket['lastRefill'];
        return min((float) $this->capacity, $bucket['tokens'] + ($elapsed * $this->ratePerSecond));
    }

    public function reset(?string $key = null): void
    {
        if ($key !== null) {
            unset($this->buckets[$key]);
        } else {
            $this->buckets = [];
        }
    }

    public function toArray(): array
    {
        return [
            'rate_per_second' => $this->ratePerSecond,
            'capacity' => $this->capacity,
            'active_keys' => count($this->buckets),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
