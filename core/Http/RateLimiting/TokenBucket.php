<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RateLimiting/TokenBucket.php
| Module    : Http\RateLimiting
| Namespace : NetFusion\Core\Http\RateLimiting
| Purpose   : High-performance Token Bucket rate limiting algorithm implementation.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Http\RateLimiting;

/**
 * Class TokenBucket
 *
 * Implements the Token Bucket algorithm which allows short bursts of traffic
 * up to capacity while enforcing a smooth steady-state replenishment rate.
 *
 * @package NetFusion\Core\Http\RateLimiting
 */
class TokenBucket
{
    protected float $tokens;
    protected float $lastRefill;

    /**
     * @param int        $capacity Maximum number of tokens the bucket can hold.
     * @param float      $refillRate Tokens added per second.
     * @param float|null $initialTokens Initial token count (defaults to capacity).
     * @param float|null $lastRefill Unix timestamp in seconds of last replenishment.
     */
    public function __construct(
        protected int $capacity,
        protected float $refillRate,
        ?float $initialTokens = null,
        ?float $lastRefill = null
    ) {
        $this->tokens = $initialTokens ?? (float) $capacity;
        $this->lastRefill = $lastRefill ?? microtime(true);
    }

    /**
     * Attempt to consume tokens from the bucket.
     *
     * @param int $amount Number of tokens to consume.
     * @return bool True if tokens were consumed; false if not enough tokens.
     */
    public function consume(int $amount = 1): bool
    {
        $this->refill();

        if ($this->tokens >= $amount) {
            $this->tokens -= $amount;
            return true;
        }

        return false;
    }

    /**
     * Get remaining available tokens in the bucket.
     */
    public function getTokens(): int
    {
        $this->refill();
        return (int) floor($this->tokens);
    }

    /**
     * Get the bucket's maximum capacity.
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Get the refill rate in tokens per second.
     */
    public function getRefillRate(): float
    {
        return $this->refillRate;
    }

    /**
     * Calculate seconds until the requested token amount becomes available.
     */
    public function timeUntilAvailable(int $amount = 1): int
    {
        $this->refill();

        if ($this->tokens >= $amount) {
            return 0;
        }

        $needed = $amount - $this->tokens;
        if ($this->refillRate <= 0) {
            return 60;
        }

        return (int) ceil($needed / $this->refillRate);
    }

    /**
     * Export bucket state for storage serialization.
     *
     * @return array{capacity: int, refill_rate: float, tokens: float, last_refill: float}
     */
    public function getState(): array
    {
        $this->refill();
        return [
            'capacity'    => $this->capacity,
            'refill_rate' => $this->refillRate,
            'tokens'      => $this->tokens,
            'last_refill' => $this->lastRefill,
        ];
    }

    /**
     * Restore a TokenBucket instance from serialized state.
     *
     * @param array{capacity: int, refill_rate: float, tokens: float, last_refill: float} $state
     */
    public static function fromState(array $state): self
    {
        return new self(
            capacity     : (int) $state['capacity'],
            refillRate   : (float) $state['refill_rate'],
            initialTokens: (float) $state['tokens'],
            lastRefill   : (float) $state['last_refill']
        );
    }

    /**
     * Replenish tokens based on elapsed time since last refill.
     */
    protected function refill(): void
    {
        $now = microtime(true);
        $elapsed = max(0.0, $now - $this->lastRefill);

        if ($elapsed > 0) {
            $added = $elapsed * $this->refillRate;
            $this->tokens = min((float) $this->capacity, $this->tokens + $added);
            $this->lastRefill = $now;
        }
    }
}
